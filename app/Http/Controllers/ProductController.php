<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductSearchRequest;
use App\Http\Resources\ProductContributionResource;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Models\UserPreference;
use App\Services\ProductAnalysisService;
use App\Services\ProductContributionService;
use App\Services\ProductPayloadService;
use App\Services\ProductWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ProductController extends Controller
{
    public function __construct(
        protected ProductAnalysisService $analysisService,
        protected ProductWorkflowService $productWorkflowService,
        protected ProductPayloadService $productPayloadService,
        protected ProductContributionService $productContributionService
    ) {
    }

    public function search(ProductSearchRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $query = trim($validated['query']);
        $page = (int) ($validated['page'] ?? 1);
        $perPage = (int) ($validated['per_page'] ?? 20);

        $products = Product::with(['ingredients', 'nutrition', 'foodScore', 'cosmeticScore', 'images', 'barcodes'])
            ->where('barcode', $query)
            ->orWhereHas('barcodes', fn ($barcodeQuery) => $barcodeQuery->where('barcode', $query))
            ->orWhere('barcode', 'like', "{$query}%")
            ->orWhere('name', 'like', "%{$query}%")
            ->orWhere('brand', 'like', "%{$query}%")
            ->orWhereHas('barcodes', fn ($barcodeQuery) => $barcodeQuery->where('barcode', 'like', "{$query}%"))
            ->orderByRaw('CASE WHEN barcode = ? THEN 0 ELSE 1 END', [$query])
            ->paginate($perPage, ['*'], 'page', $page);

        $pendingContribution = null;

        if ($products->total() === 0 && preg_match('/^[0-9]{8,13}$/', $query)) {
            try {
                $product = $this->analysisService
                    ->analyzeByBarcode($query)
                    ->load(['ingredients', 'nutrition', 'foodScore', 'cosmeticScore', 'images', 'barcodes']);

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

                $pendingContribution = $this->productContributionService->pendingSummaryForBarcode($query);
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
            'pending_contribution' => $pendingContribution
                ? new ProductContributionResource($pendingContribution)
                : null,
        ]);
    }

    public function findByBarcode(string $barcode): JsonResponse
    {
        try {
            $product = $this->analysisService->analyzeByBarcode($barcode)->load([
                'ingredients',
                'nutrition',
                'foodScore',
                'cosmeticScore',
                'images',
                'barcodes',
                'alternatives' => function ($query) {
                    $query->with(['foodScore', 'cosmeticScore', 'images', 'barcodes'])->limit(3);
                },
            ]);

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
                $pendingContribution = $this->productContributionService->pendingSummaryForBarcode($barcode);

                return response()->json([
                    'success' => false,
                    'message' => $pendingContribution
                        ? 'Product is not in the catalogue yet. A community submission is pending review.'
                        : 'Product not found',
                    'data' => null,
                    'pending_contribution' => $pendingContribution
                        ? new ProductContributionResource($pendingContribution)
                        : null,
                ], 404);
            }

            return response()->json([
                'success' => false,
                'message' => 'Error fetching product',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function show(string $id): JsonResponse
    {
        $product = Product::with([
            'ingredients',
            'nutrition',
            'foodScore',
            'cosmeticScore',
            'images',
            'barcodes',
            'alternatives' => fn ($query) => $query->with(['foodScore', 'cosmeticScore', 'images', 'barcodes'])->limit(5),
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => new ProductResource($product),
        ]);
    }

    public function alternatives(string $id): JsonResponse
    {
        $product = Product::with(['foodScore', 'cosmeticScore', 'images', 'barcodes'])->findOrFail($id);

        $alternatives = Product::with(['foodScore', 'cosmeticScore', 'ingredients', 'images', 'barcodes'])
            ->where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->where(function ($query) use ($product) {
                if ($product->foodScore) {
                    $query->whereHas('foodScore', function ($scoreQuery) use ($product) {
                        $scoreQuery->where('overall_score', '>', $product->foodScore->overall_score);
                    });
                }

                if ($product->cosmeticScore) {
                    $query->orWhereHas('cosmeticScore', function ($scoreQuery) use ($product) {
                        $scoreQuery->where('overall_score', '>', $product->cosmeticScore->overall_score);
                    });
                }
            })
            ->limit(5)
            ->get();

        $alternativesWithComparison = $alternatives->map(function ($alternative) use ($product) {
            $alternativeScore = $alternative->foodScore->overall_score ?? $alternative->cosmeticScore->overall_score ?? 0;
            $productScore = $product->foodScore->overall_score ?? $product->cosmeticScore->overall_score ?? 0;

            return [
                'product' => new ProductResource($alternative),
                'score_improvement' => round($alternativeScore - $productScore, 2),
                'reasons' => $this->generateAlternativeReasons($product, $alternative),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $alternativesWithComparison,
            'original_product' => new ProductResource($product),
        ]);
    }

    public function userProducts(): JsonResponse
    {
        $products = Product::whereHas('scans', function ($query) {
            $query->where('user_id', Auth::id());
        })
            ->with([
                'scans' => function ($query) {
                    $query->where('user_id', Auth::id())->latest();
                },
                'foodScore',
                'cosmeticScore',
                'images',
                'barcodes',
            ])
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

    public function toggleFavorite(string $id): JsonResponse
    {
        $user = Auth::user();
        $product = Product::findOrFail($id);

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

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Product::class);

        $validator = Validator::make($request->all(), [
            'barcode' => 'required|string|max:50|unique:products,barcode',
            'name' => 'required|string|max:255',
            'brand' => 'nullable|string|max:255',
            'category_id' => 'nullable|integer',
            'category_name' => 'nullable|string|max:255',
            'product_family' => 'nullable|string|in:food,cosmetic,pet_food,household,general',
            'image_url' => 'nullable|url',
            'ingredients_text' => 'nullable|string',
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

        $payload = $this->buildPayloadFromRequest($request);
        $product = $this->productWorkflowService->createProduct($payload, [
            'actor' => Auth::user(),
            'source' => 'admin_manual',
            'mark_approved' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Product created successfully',
            'data' => new ProductResource($product),
        ], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $this->authorize('update', Product::class);

        $product = Product::with(['ingredients', 'nutrition', 'barcodes', 'images'])->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'barcode' => 'sometimes|string|max:50|unique:products,barcode,' . $product->id,
            'name' => 'sometimes|string|max:255',
            'brand' => 'nullable|string|max:255',
            'category_id' => 'nullable|integer',
            'category_name' => 'nullable|string|max:255',
            'product_family' => 'nullable|string|in:food,cosmetic,pet_food,household,general',
            'image_url' => 'nullable|url',
            'ingredients_text' => 'nullable|string',
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

        $payload = $this->buildPayloadFromRequest($request);
        $updatedProduct = $this->productWorkflowService->updateProduct($product, $payload, [
            'actor' => Auth::user(),
            'track_manual_overrides' => true,
            'mark_approved' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Product updated successfully',
            'data' => new ProductResource($updatedProduct),
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $this->authorize('delete', Product::class);

        $product = Product::findOrFail($id);
        $this->productWorkflowService->deleteProduct($product, Auth::user());

        return response()->json([
            'success' => true,
            'message' => 'Product deleted successfully',
        ]);
    }

    protected function generateAlternativeReasons(Product $original, Product $alternative): array
    {
        $reasons = [];

        $originalScore = $original->foodScore->overall_score ?? $original->cosmeticScore->overall_score ?? 0;
        $alternativeScore = $alternative->foodScore->overall_score ?? $alternative->cosmeticScore->overall_score ?? 0;

        if ($alternativeScore > $originalScore) {
            $reasons[] = 'Overall score is ' . round($alternativeScore - $originalScore, 1) . ' points higher';
        }

        $originalIngredients = $original->ingredients->pluck('name')->toArray();
        $alternativeIngredients = $alternative->ingredients->pluck('name')->toArray();

        $betterIngredients = array_diff($alternativeIngredients, $originalIngredients);

        if (count($betterIngredients) > 0) {
            $reasons[] = 'Contains better quality ingredients: ' . implode(', ', array_slice($betterIngredients, 0, 3));
        }

        return $reasons;
    }

    protected function buildPayloadFromRequest(Request $request): array
    {
        $input = $request->all();

        if ($request->hasFile('image_files')) {
            $uploadedImages = [];

            foreach ($request->file('image_files') as $index => $file) {
                $path = $file->store('products', 'public');
                $uploadedImages[] = [
                    'disk' => 'public',
                    'path' => $path,
                    'source' => 'upload',
                    'is_primary' => $index === 0,
                    'sort_order' => $index,
                ];
            }

            $input['images'] = array_merge($input['images'] ?? [], $uploadedImages);

            if (!isset($input['image_url']) && count($uploadedImages) > 0) {
                $input['image_url'] = Storage::disk('public')->url($uploadedImages[0]['path']);
            }
        }

        return $this->productPayloadService->fromInput($input);
    }
}
