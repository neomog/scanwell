<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Scan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class ProductAnalysisService
{
    public function __construct(
        protected ProductCatalogService $productCatalogService,
        protected ProductFamilyResolver $productFamilyResolver,
        protected ProductWorkflowService $productWorkflowService
    ) {
    }

    /**
     * Analyze product by barcode.
     */
    public function analyzeByBarcode(string $barcode, ?string $userId = null, ?Scan $scan = null): Product
    {
        return DB::transaction(function () use ($barcode, $scan) {
            $product = $this->productWorkflowService->findByBarcode($barcode);

            if ($product) {
                if ($this->shouldRefreshProductDuringInteractiveScan($product)) {
                    $this->updateProductFromApi($product, $scan);
                }
            } else {
                $product = $this->createProductFromApi($barcode, $scan);
            }

            return $product->load(['ingredients', 'nutrition', 'foodScore', 'cosmeticScore']);
        });
    }

    /**
     * Create new product from external catalog data.
     */
    public function createProductFromApi(string $barcode, ?Scan $scan = null): Product
    {
        $catalogData = $this->productCatalogService->findByBarcode($barcode, null, $scan);

        if (!$catalogData) {
            throw new Exception("Product not found with barcode: {$barcode}", 404);
        }

        $productFamily = $this->productFamilyResolver->resolveFromNormalized($catalogData);
        $categoryId = $catalogData['category_id']
            ?? $this->productFamilyResolver->categoryIdForFamily($productFamily, $catalogData);

        $payload = [
            'barcode' => $catalogData['barcode'],
            'name' => $catalogData['name'],
            'brand' => $catalogData['brand'],
            'category_id' => $categoryId,
            'category_name' => data_get($catalogData, 'raw_data.categories'),
            'product_family' => $productFamily,
            'image_url' => $catalogData['image_url'],
            'ingredients' => $catalogData['ingredients'] ?? [],
            'nutrition' => $this->productFamilyResolver->supportsFoodScore($productFamily)
                && $this->hasMeaningfulNutrition($catalogData['nutrition'] ?? [])
                ? ($catalogData['nutrition'] ?? [])
                : [],
            'source' => $catalogData['source'],
            'raw_data' => $this->buildStoredRawData($catalogData, $productFamily),
        ];

        return $this->productWorkflowService->createProduct($payload, [
            'source' => $catalogData['source'],
            'audit' => false,
        ]);
    }

    /**
     * Update existing product with fresh vendor data.
     */
    protected function updateProductFromApi(Product $product, ?Scan $scan = null): void
    {
        try {
            $catalogData = $this->productCatalogService->findByBarcode(
                $product->barcode,
                $product->resolved_product_family,
                $scan
            );

            if (!$catalogData) {
                return;
            }

            $productFamily = $this->productFamilyResolver->resolveFromNormalized($catalogData);
            $categoryId = $catalogData['category_id']
                ?? $this->productFamilyResolver->categoryIdForFamily($productFamily, $catalogData);

            $payload = [
                'barcode' => $catalogData['barcode'],
                'name' => $catalogData['name'],
                'brand' => $catalogData['brand'],
                'category_id' => $categoryId,
                'category_name' => data_get($catalogData, 'raw_data.categories'),
                'product_family' => $productFamily,
                'image_url' => $catalogData['image_url'],
                'ingredients' => $catalogData['ingredients'] ?? [],
                'nutrition' => $this->productFamilyResolver->supportsFoodScore($productFamily)
                    ? ($catalogData['nutrition'] ?? [])
                    : [],
                'source' => $catalogData['source'],
                'raw_data' => $this->buildStoredRawData($catalogData, $productFamily),
            ];

            $this->productWorkflowService->updateProduct($product, $payload, [
                'source' => $catalogData['source'],
                'preserve_manual_overrides' => true,
                'audit' => false,
            ]);
        } catch (\Throwable $exception) {
            Log::error('Failed to update product from catalog', [
                'product_id' => $product->id,
                'barcode' => $product->barcode,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    protected function shouldRefreshProduct(Product $product): bool
    {
        if (!$product->updated_at) {
            return false;
        }

        if (!filled(data_get($product->raw_data, '_scanwell.provider'))) {
            return false;
        }

        return $product->updated_at->diffInDays(now()) > config('scanning.stale_after_days', 30);
    }

    protected function shouldRefreshProductDuringInteractiveScan(Product $product): bool
    {
        if (!$this->shouldRefreshProduct($product)) {
            return false;
        }

        $hasIngredients = $product->ingredients()->exists() || filled($product->ingredients_text);
        $hasNutrition = $product->nutrition()->exists();
        $hasImage = filled($product->primary_image_url);

        return !$hasIngredients || !$hasNutrition || !$hasImage;
    }

    protected function buildStoredRawData(array $catalogData, string $productFamily): array
    {
        $rawData = $catalogData['raw_data'] ?? [];
        $rawData['_scanwell'] = [
            'provider' => $catalogData['provider'] ?? null,
            'source' => $catalogData['source'] ?? null,
            'product_family' => $productFamily,
            'confidence' => $catalogData['confidence'] ?? null,
            'completeness' => $catalogData['completeness'] ?? null,
            'matched_by' => 'barcode_exact',
            'packaging' => $catalogData['packaging'] ?? [],
            'warnings' => $catalogData['warnings'] ?? [],
            'lookup_summary' => $catalogData['lookup_summary'] ?? $this->productCatalogService->lastLookupSummary(),
            'resolved_at' => now()->toIso8601String(),
        ];

        return $rawData;
    }

    protected function hasMeaningfulNutrition(array $nutritionData): bool
    {
        foreach ($nutritionData as $value) {
            if ($value !== null && $value !== '') {
                return true;
            }
        }

        return false;
    }

    public function lastLookupSummary(): array
    {
        return $this->productCatalogService->lastLookupSummary();
    }
}
