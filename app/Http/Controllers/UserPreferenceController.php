<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserPreferenceResource;
use App\Models\UserPreference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class UserPreferenceController extends Controller
{
    /**
     * Get user preferences
     */
    public function show(): JsonResponse
    {
        $preferences = UserPreference::firstOrCreate(
            ['user_id' => Auth::id()],
            [
                'diet_type' => null,
                'allergies' => [],
                'skin_type' => null,
                'health_goals' => [],
                'avoid_ingredients' => [],
                'min_score_threshold' => 50,
            ]
        );

        return response()->json([
            'success' => true,
            'data' => new UserPreferenceResource($preferences),
        ]);
    }

    /**
     * Update user preferences
     */
    public function update(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'diet_type' => 'nullable|string|max:50|in:omnivore,vegetarian,vegan,keto,paleo,halal,kosher',
            'allergies' => 'nullable|array',
            'allergies.*' => 'string|max:100',
            'skin_type' => 'nullable|string|max:50|in:dry,oily,combination,sensitive,normal',
            'health_goals' => 'nullable|array',
            'health_goals.*' => 'string|max:100',
            'avoid_ingredients' => 'nullable|array',
            'avoid_ingredients.*' => 'string|max:100',
            'min_score_threshold' => 'nullable|integer|min:0|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $preferences = UserPreference::updateOrCreate(
            ['user_id' => Auth::id()],
            $validator->validated()
        );

        return response()->json([
            'success' => true,
            'message' => 'Preferences updated successfully',
            'data' => new UserPreferenceResource($preferences),
        ]);
    }

    /**
     * Get personalized product recommendations
     */
    public function recommendations(): JsonResponse
    {
        $user = Auth::user();
        $preferences = $user->preferences;

        if (!$preferences) {
            return response()->json([
                'success' => true,
                'data' => [],
                'message' => 'Set your preferences to get personalized recommendations',
            ]);
        }

        // Build query based on preferences
        $query = Product::with(['foodScore', 'cosmeticScore', 'ingredients']);

        // Filter by minimum score
        if ($preferences->min_score_threshold) {
            $query->whereHas('foodScore', function ($q) use ($preferences) {
                $q->where('overall_score', '>=', $preferences->min_score_threshold);
            })->orWhereHas('cosmeticScore', function ($q) use ($preferences) {
                $q->where('overall_score', '>=', $preferences->min_score_threshold);
            });
        }

        // Exclude products with ingredients user avoids
        if (!empty($preferences->avoid_ingredients)) {
            $query->whereDoesntHave('ingredients', function ($q) use ($preferences) {
                $q->whereIn('name', $preferences->avoid_ingredients);
            });
        }

        // Filter by diet type
        if ($preferences->diet_type === 'vegan') {
            $query->whereDoesntHave('ingredients', function ($q) {
                $q->whereIn('category', ['animal_product', 'dairy', 'egg']);
            });
        } elseif ($preferences->diet_type === 'vegetarian') {
            $query->whereDoesntHave('ingredients', function ($q) {
                $q->whereIn('category', ['meat', 'fish']);
            });
        }

        $recommendations = $query->inRandomOrder()->limit(10)->get();

        return response()->json([
            'success' => true,
            'data' => [
                'preferences' => new UserPreferenceResource($preferences),
                'recommendations' => \App\Http\Resources\ProductResource::collection($recommendations),
            ],
        ]);
    }
}
