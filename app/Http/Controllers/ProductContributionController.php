<?php

namespace App\Http\Controllers;

use App\Http\Requests\IngredientImageExtractRequest;
use App\Http\Requests\NutritionImageExtractRequest;
use App\Http\Resources\ProductContributionResource;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Models\ProductContribution;
use App\Services\ContributionReputationService;
use App\Services\IngredientImageExtractionService;
use App\Services\NutritionImageExtractionService;
use App\Services\ProductContributionService;
use App\Services\ProductPayloadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ProductContributionController extends Controller
{
    public function __construct(
        protected ProductContributionService $productContributionService,
        protected ProductPayloadService $productPayloadService,
        protected ContributionReputationService $contributionReputationService,
        protected IngredientImageExtractionService $ingredientImageExtractionService,
        protected NutritionImageExtractionService $nutritionImageExtractionService
    ) {
    }

    public function extractIngredients(IngredientImageExtractRequest $request): JsonResponse
    {
        $result = $this->ingredientImageExtractionService->extractFromImage($request->file('image'));

        if ($result === null) {
            return response()->json([
                'success' => false,
                'message' => 'We could not extract a readable ingredient list from the image. Please retake the photo with the ingredients panel clearly visible.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Ingredients extracted successfully',
            'data' => $result,
        ]);
    }

    public function extractNutrition(NutritionImageExtractRequest $request): JsonResponse
    {
        $result = $this->nutritionImageExtractionService->extractFromImage($request->file('image'));

        if ($result === null) {
            return response()->json([
                'success' => false,
                'message' => 'We could not extract readable nutrition facts from the image. Please retake the photo with the nutrition panel clearly visible.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Nutrition facts extracted successfully',
            'data' => $result,
        ]);
    }

    public function store(Request $request, string $barcode): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'change_type' => 'required|string|in:add,update,correct,report_issue',
            'field_name' => 'nullable|string|max:255',
            'reason' => 'required|string|min:10|max:1000',
            'evidence' => 'nullable|array',
            'product_name' => 'nullable|string|max:255',
            'name' => 'nullable|string|max:255',
            'brand' => 'nullable|string|max:255',
            'category_id' => 'nullable|integer',
            'category_name' => 'nullable|string|max:255',
            'product_family' => 'nullable|string|in:food,cosmetic,pet_food,household,general',
            'image_url' => 'nullable|url',
            'ingredients_text' => 'nullable|string',
            'nutrition_text' => 'nullable|string',
            'ingredients' => 'nullable|array',
            'nutrition' => 'nullable|array',
            'additives' => 'nullable|array',
            'allergens' => 'nullable|array',
            'region_availability' => 'nullable|array',
            'barcodes' => 'nullable|array',
            'images' => 'nullable|array',
            'image_urls' => 'nullable|array',
            'image_urls.*' => 'url',
            'image_files' => 'nullable|array',
            'image_files.*' => 'image|max:5120',
            'new_value' => 'nullable',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $input = $this->buildContributionInput($request, $barcode);

        if (($input['change_type'] ?? null) === 'add' && Product::where('barcode', $barcode)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'This product already exists. Submit a correction or update instead.',
            ], 409);
        }

        if (($input['change_type'] ?? null) === 'add' && empty($input['name'] ?? null)) {
            return response()->json([
                'success' => false,
                'errors' => [
                    'product_name' => ['Product name is required for a new product submission.'],
                ],
            ], 422);
        }

        if (
            in_array($input['change_type'] ?? null, ['update', 'correct', 'report_issue'], true)
            && empty(array_diff(array_keys($input), ['change_type', 'reason', 'evidence', 'submitted_via']))
        ) {
            return response()->json([
                'success' => false,
                'errors' => [
                    'new_value' => ['Provide a field change or updated product data.'],
                ],
            ], 422);
        }

        $contribution = $this->productContributionService->submit(Auth::user(), $barcode, $input);

        return response()->json([
            'success' => true,
            'message' => 'Contribution submitted successfully. It will become a product only after admin approval.',
            'data' => [
                'contribution' => new ProductContributionResource($contribution),
            ],
        ], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $contribution = ProductContribution::with(['user', 'product', 'reviewer'])->findOrFail($id);

        if ($contribution->user_id !== Auth::id()) {
            abort(403);
        }

        if (!$contribution->isEditableByUser()) {
            return response()->json([
                'success' => false,
                'message' => 'Only pending or changes-requested contributions can be edited.',
            ], 409);
        }

        $validator = Validator::make($request->all(), [
            'field_name' => 'nullable|string|max:255',
            'reason' => 'sometimes|string|min:10|max:1000',
            'evidence' => 'nullable|array',
            'product_name' => 'nullable|string|max:255',
            'name' => 'nullable|string|max:255',
            'brand' => 'nullable|string|max:255',
            'category_id' => 'nullable|integer',
            'category_name' => 'nullable|string|max:255',
            'product_family' => 'nullable|string|in:food,cosmetic,pet_food,household,general',
            'image_url' => 'nullable|url',
            'ingredients_text' => 'nullable|string',
            'nutrition_text' => 'nullable|string',
            'ingredients' => 'nullable|array',
            'nutrition' => 'nullable|array',
            'additives' => 'nullable|array',
            'allergens' => 'nullable|array',
            'region_availability' => 'nullable|array',
            'barcodes' => 'nullable|array',
            'images' => 'nullable|array',
            'image_urls' => 'nullable|array',
            'image_urls.*' => 'url',
            'image_files' => 'nullable|array',
            'image_files.*' => 'image|max:5120',
            'new_value' => 'nullable',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $updatedContribution = $this->productContributionService->updateUserContribution(
            $contribution,
            $this->buildContributionInput($request, $contribution->barcode)
        );

        return response()->json([
            'success' => true,
            'message' => 'Contribution updated successfully',
            'data' => new ProductContributionResource($updatedContribution),
        ]);
    }

    public function userContributions(Request $request): JsonResponse
    {
        $query = ProductContribution::where('user_id', Auth::id())
            ->with(['product.images', 'product.barcodes', 'reviewer'])
            ->orderBy('created_at', 'desc');

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        if ($changeType = $request->get('change_type')) {
            $query->where('change_type', $changeType);
        }

        $perPage = min(max((int) $request->get('limit', 20), 1), 50);
        $contributions = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => ProductContributionResource::collection($contributions),
            'meta' => [
                'total' => $contributions->total(),
                'per_page' => $contributions->perPage(),
                'current_page' => $contributions->currentPage(),
                'last_page' => $contributions->lastPage(),
            ],
        ]);
    }

    public function leaderboard(Request $request): JsonResponse
    {
        $perPage = min((int) $request->get('per_page', config('contributions.leaderboard_limit', 20)), 50);
        $leaders = $this->productContributionService->leaderboard($perPage);

        $data = $leaders->getCollection()->map(function ($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'avatar' => $user->avatar,
                'reputation_points' => $user->reputation_points,
                'approved_contributions_count' => $user->approved_contributions_count,
                'rejected_contributions_count' => $user->rejected_contributions_count,
                'level' => $this->contributionReputationService->levelForPoints($user->reputation_points),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data,
            'meta' => [
                'total' => $leaders->total(),
                'per_page' => $leaders->perPage(),
                'current_page' => $leaders->currentPage(),
                'last_page' => $leaders->lastPage(),
            ],
        ]);
    }

    public function pending(Request $request): JsonResponse
    {
        $this->authorize('viewPending', ProductContribution::class);

        $query = ProductContribution::with(['user', 'product.images', 'product.barcodes', 'reviewer'])
            ->orderBy('created_at', 'asc');

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        } else {
            $query->where('status', 'pending');
        }

        if ($changeType = $request->get('change_type')) {
            $query->where('change_type', $changeType);
        }

        $contributions = $query->paginate(20);

        return response()->json([
            'success' => true,
            'data' => ProductContributionResource::collection($contributions),
            'meta' => [
                'total' => $contributions->total(),
                'pending_count' => ProductContribution::where('status', 'pending')->count(),
            ],
        ]);
    }

    public function moderateUpdate(Request $request, string $id): JsonResponse
    {
        $this->authorize('approve', ProductContribution::class);

        $contribution = ProductContribution::with(['user', 'product', 'reviewer'])->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'review_notes' => 'nullable|string|max:1000',
            'barcode' => 'nullable|string|max:50',
            'product_name' => 'nullable|string|max:255',
            'name' => 'nullable|string|max:255',
            'brand' => 'nullable|string|max:255',
            'category_id' => 'nullable|integer',
            'category_name' => 'nullable|string|max:255',
            'product_family' => 'nullable|string|in:food,cosmetic,pet_food,household,general',
            'image_url' => 'nullable|url',
            'ingredients_text' => 'nullable|string',
            'nutrition_text' => 'nullable|string',
            'ingredients' => 'nullable|array',
            'nutrition' => 'nullable|array',
            'additives' => 'nullable|array',
            'allergens' => 'nullable|array',
            'region_availability' => 'nullable|array',
            'barcodes' => 'nullable|array',
            'images' => 'nullable|array',
            'image_urls' => 'nullable|array',
            'image_urls.*' => 'url',
            'image_files' => 'nullable|array',
            'image_files.*' => 'image|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $updatedContribution = $this->productContributionService->updateForModeration(
            $contribution,
            Auth::user(),
            $this->buildContributionInput($request, $contribution->barcode)
        );

        return response()->json([
            'success' => true,
            'message' => 'Contribution updated for moderation',
            'data' => new ProductContributionResource($updatedContribution),
        ]);
    }

    public function approve(Request $request, string $id): JsonResponse
    {
        $this->authorize('approve', ProductContribution::class);

        $contribution = ProductContribution::with(['user', 'product'])->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'notes' => 'nullable|string|max:1000',
            'barcode' => 'nullable|string|max:50',
            'product_name' => 'nullable|string|max:255',
            'name' => 'nullable|string|max:255',
            'brand' => 'nullable|string|max:255',
            'category_id' => 'nullable|integer',
            'category_name' => 'nullable|string|max:255',
            'product_family' => 'nullable|string|in:food,cosmetic,pet_food,household,general',
            'image_url' => 'nullable|url',
            'ingredients_text' => 'nullable|string',
            'nutrition_text' => 'nullable|string',
            'ingredients' => 'nullable|array',
            'nutrition' => 'nullable|array',
            'additives' => 'nullable|array',
            'allergens' => 'nullable|array',
            'region_availability' => 'nullable|array',
            'barcodes' => 'nullable|array',
            'images' => 'nullable|array',
            'image_urls' => 'nullable|array',
            'image_urls.*' => 'url',
            'image_files' => 'nullable|array',
            'image_files.*' => 'image|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $result = $this->productContributionService->approve(
            $contribution,
            Auth::user(),
            $this->buildContributionInput($request, $contribution->barcode),
            $request->input('notes')
        );

        return response()->json([
            'success' => true,
            'message' => 'Contribution approved successfully',
            'data' => [
                'contribution' => new ProductContributionResource($result['contribution']),
                'product' => $result['product'] ? new ProductResource($result['product']) : null,
                'points_awarded' => $result['points_awarded'],
            ],
        ]);
    }

    public function reject(Request $request, string $id): JsonResponse
    {
        $this->authorize('reject', ProductContribution::class);

        $contribution = ProductContribution::with(['user', 'product'])->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|min:10|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $rejectedContribution = $this->productContributionService->reject(
            $contribution,
            Auth::user(),
            $request->input('reason')
        );

        return response()->json([
            'success' => true,
            'message' => 'Contribution rejected',
            'data' => new ProductContributionResource($rejectedContribution),
        ]);
    }

    public function flag(Request $request, string $id): JsonResponse
    {
        $this->authorize('reject', ProductContribution::class);

        $contribution = ProductContribution::with(['user', 'product'])->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|min:10|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $flaggedContribution = $this->productContributionService->flag(
            $contribution,
            Auth::user(),
            $request->input('reason')
        );

        return response()->json([
            'success' => true,
            'message' => 'Contribution flagged',
            'data' => new ProductContributionResource($flaggedContribution),
        ]);
    }

    protected function buildContributionInput(Request $request, string $defaultBarcode): array
    {
        $input = $request->all();
        $fieldName = $input['field_name'] ?? null;

        if ($request->hasFile('image_files')) {
            $uploadedImages = [];

            foreach ($request->file('image_files') as $index => $file) {
                $path = $file->store('product-contributions', 'public');
                $uploadedImages[] = [
                    'disk' => 'public',
                    'path' => $path,
                    'url' => Storage::disk('public')->url($path),
                    'source' => 'contribution_upload',
                    'is_primary' => $index === 0,
                    'sort_order' => $index,
                ];
            }

            $input['images'] = array_merge($input['images'] ?? [], $uploadedImages);

            if (!isset($input['image_url']) && count($uploadedImages) > 0) {
                $input['image_url'] = Storage::disk('public')->url($uploadedImages[0]['path']);
            }
        }

        if ($fieldName && array_key_exists('new_value', $input)) {
            $canonicalField = $fieldName === 'product_name' ? 'name' : $fieldName;
            $input[$canonicalField] = $input['new_value'];
        }

        $payload = $this->productPayloadService->fromInput(array_merge($input, ['barcode' => $input['barcode'] ?? $defaultBarcode]));
        $payload['change_type'] = $input['change_type'] ?? 'update';
        $payload['field_name'] = $fieldName;
        $payload['reason'] = $input['reason'] ?? null;
        $payload['evidence'] = $input['evidence'] ?? null;
        $payload['submitted_via'] = $input['submitted_via'] ?? 'api';

        return $payload;
    }
}
