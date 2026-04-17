<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProductSearchRequest;
use App\Http\Resources\ProductResource;
use App\Http\Resources\ProductContributionResource;
use App\Models\Product;
use App\Models\UserPreference;
use App\Services\ProductAnalysisService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ProductController extends Controller
{
    protected ProductAnalysisService $analysisService;

    public function __construct(ProductAnalysisService $analysisService)
    {
        $this->analysisService = $analysisService;
    }

    /**
     * Search products by barcode, name, or brand.
     */
    public function search(ProductSearchRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $query = trim($validated['query']);
        $page = (int) ($validated['page'] ?? 1);
        $perPage = (int) ($validated['per_page'] ?? 20);

        $products = Product::with(['ingredients', 'nutrition', 'foodScore', 'cosmeticScore'])
            ->where('barcode', $query)
            ->orWhere('barcode', 'like', "{$query}%")
            ->orWhere('name', 'like', "%{$query}%")
            ->orWhere('brand', 'like', "%{$query}%")
            ->orderByRaw('CASE WHEN barcode = ? THEN 0 ELSE 1 END', [$query])
            ->paginate($perPage, ['*'], 'page', $page);

        if ($products->total() === 0 && preg_match('/^[0-9]{8,13}$/', $query)) {
            try {
                $product = $this->analysisService
                    ->analyzeByBarcode($query)
                    ->load(['ingredients', 'nutrition', 'foodScore', 'cosmeticScore']);

                return response()->json([
                    'success' => true,
                    'data' => ProductResource::collection(collect([$product])),
                    'meta' => [
                        'total' => 1,
                        'per_page' => 1,
                        'current_page' => 1,
                        'last_page' => 1,
                    ],
                ]);
            } catch (\Exception $e) {
                if ($e->getCode() !== 404) {
                    throw $e;
                }
            }
        }

        return response()->json([
            'success' => true,
            'data' => ProductResource::collection($products),
            'meta' => [
                'total' => $products->total(),
                'per_page' => $products->perPage(),
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
            ],
        ]);
    }

    /**
     * Find product by barcode
     */
    public function findByBarcode(string $barcode): JsonResponse
    {
        try {
            $product = $this->analysisService->analyzeByBarcode($barcode)->load([
                'ingredients',
                'nutrition',
                'foodScore',
                'cosmeticScore',
                'alternatives' => function ($query) {
                    $query->with(['foodScore', 'cosmeticScore'])->limit(3);
                }
            ]);

            // Get personalized warnings based on user preferences
            $personalizedWarnings = [];
            if (Auth::check()) {
                $preferences = UserPreference::where('user_id', Auth::id())->first();
                if ($preferences) {
                    $personalizedWarnings = $preferences->getPersonalizedWarnings($product);
                }
            }

            return response()->json([
                'success' => true,
                'data' => new ProductResource($product),
                'personalized_warnings' => $personalizedWarnings,
            ]);

        } catch (\Exception $e) {
            if ($e->getCode() === 404) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product not found',
                    'data' => null,
                ], 404);
            }

            return response()->json([
                'success' => false,
                'message' => 'Error fetching product',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get alternative products
     */
    public function alternatives(string $id): JsonResponse
    {
        $product = Product::with(['foodScore', 'cosmeticScore'])->findOrFail($id);

        // Find alternatives in same category with better scores
        $alternatives = Product::with(['foodScore', 'cosmeticScore', 'ingredients'])
            ->where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->where(function ($query) use ($product) {
                if ($product->foodScore) {
                    $query->whereHas('foodScore', function ($q) use ($product) {
                        $q->where('overall_score', '>', $product->foodScore->overall_score);
                    });
                }
                if ($product->cosmeticScore) {
                    $query->orWhereHas('cosmeticScore', function ($q) use ($product) {
                        $q->where('overall_score', '>', $product->cosmeticScore->overall_score);
                    });
                }
            })
            ->limit(5)
            ->get();

        // Calculate score improvements
        $alternativesWithComparison = $alternatives->map(function ($alt) use ($product) {
            $altScore = $alt->foodScore->overall_score ?? $alt->cosmeticScore->overall_score ?? 0;
            $productScore = $product->foodScore->overall_score ?? $product->cosmeticScore->overall_score ?? 0;

            return [
                'product' => new ProductResource($alt),
                'score_improvement' => round($altScore - $productScore, 2),
                'reasons' => $this->generateAlternativeReasons($product, $alt),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $alternativesWithComparison,
            'original_product' => new ProductResource($product),
        ]);
    }

    /**
     * Get user's scanned products
     */
    public function userProducts(): JsonResponse
    {
        $products = Product::whereHas('scans', function ($query) {
            $query->where('user_id', Auth::id());
        })
            ->with(['scans' => function ($query) {
                $query->where('user_id', Auth::id())->latest();
            }, 'foodScore', 'cosmeticScore'])
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => ProductResource::collection($products),
            'meta' => [
                'total' => $products->total(),
                'per_page' => $products->perPage(),
                'current_page' => $products->currentPage(),
            ],
        ]);
    }

    /**
     * Toggle favorite status for a product
     */
    public function toggleFavorite(string $id): JsonResponse
    {
        $user = Auth::user();
        $product = Product::findOrFail($id);

        // Assuming you have a favorites pivot table
        // If not, you'll need to create a user_favorites migration
        if ($user->favoriteProducts()->where('product_id', $product->id)->exists()) {
            $user->favoriteProducts()->detach($product->id);
            $message = 'Product removed from favorites';
            $isFavorite = false;
        } else {
            $user->favoriteProducts()->attach($product->id);
            $message = 'Product added to favorites';
            $isFavorite = true;
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'is_favorite' => $isFavorite,
        ]);
    }

    /**
     * Store new product manually (admin only)
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Product::class);

        $validator = Validator::make($request->all(), [
            'barcode' => 'required|string|max:50|unique:products',
            'name' => 'required|string|max:255',
            'brand' => 'nullable|string|max:255',
            'category_id' => 'nullable|integer',
            'image_url' => 'nullable|url',
            'ingredients' => 'nullable|array',
            'ingredients.*.name' => 'required|string',
            'ingredients.*.percentage' => 'nullable|numeric|min:0|max:100',
            'nutrition' => 'nullable|array',
            'nutrition.calories' => 'nullable|numeric',
            'nutrition.fat' => 'nullable|numeric',
            'nutrition.sugars' => 'nullable|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();
        try {
            $product = Product::create([
                'barcode' => $request->barcode,
                'name' => $request->name,
                'brand' => $request->brand,
                'category_id' => $request->category_id,
                'image_url' => $request->image_url,
                'source' => 'manual',
            ]);

            // Add ingredients if provided
            if ($request->has('ingredients')) {
                foreach ($request->ingredients as $ingredientData) {
                    $ingredient = \App\Models\Ingredient::firstOrCreate(
                        ['name' => $ingredientData['name']],
                        ['category' => 'unknown', 'risk_level' => 'unknown']
                    );

                    $product->ingredients()->attach($ingredient->id, [
                        'percentage' => $ingredientData['percentage'] ?? null,
                    ]);
                }
            }

            // Add nutrition if provided
            if ($request->has('nutrition')) {
                $product->nutrition()->create($request->nutrition);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Product created successfully',
                'data' => new ProductResource($product->load(['ingredients', 'nutrition'])),
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error creating product',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update product (admin only)
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $this->authorize('update', Product::class);

        $product = Product::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'brand' => 'nullable|string|max:255',
            'category_id' => 'nullable|integer',
            'image_url' => 'nullable|url',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $product->update($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Product updated successfully',
            'data' => new ProductResource($product->fresh(['ingredients', 'nutrition'])),
        ]);
    }

    /**
     * Delete product (admin only)
     */
    public function destroy(string $id): JsonResponse
    {
        $this->authorize('delete', Product::class);

        $product = Product::findOrFail($id);
        $product->delete();

        return response()->json([
            'success' => true,
            'message' => 'Product deleted successfully',
        ]);
    }

    /**
     * Generate reasons why a product is a good alternative
     */
    protected function generateAlternativeReasons(Product $original, Product $alternative): array
    {
        $reasons = [];

        $originalScore = $original->foodScore->overall_score ?? $original->cosmeticScore->overall_score ?? 0;
        $alternativeScore = $alternative->foodScore->overall_score ?? $alternative->cosmeticScore->overall_score ?? 0;

        if ($alternativeScore > $originalScore) {
            $reasons[] = "Overall score is " . round($alternativeScore - $originalScore, 1) . " points higher";
        }

        // Compare ingredients
        $originalIngredients = $original->ingredients->pluck('name')->toArray();
        $alternativeIngredients = $alternative->ingredients->pluck('name')->toArray();

        $betterIngredients = array_diff($alternativeIngredients, $originalIngredients);
        if (count($betterIngredients) > 0) {
            $reasons[] = "Contains better quality ingredients: " . implode(', ', array_slice($betterIngredients, 0, 3));
        }

        return $reasons;
    }
}
