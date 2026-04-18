<?php

namespace App\Services;

use App\Models\Product;
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
    public function analyzeByBarcode(string $barcode, ?string $userId = null): Product
    {
        return DB::transaction(function () use ($barcode) {
            $product = $this->productWorkflowService->findByBarcode($barcode);

            if ($product) {
                if ($this->shouldRefreshProduct($product)) {
                    $this->updateProductFromApi($product);
                }
            } else {
                $product = $this->createProductFromApi($barcode);
            }

            return $product->load(['ingredients', 'nutrition', 'foodScore', 'cosmeticScore']);
        });
    }

    /**
     * Create new product from external catalog data.
     */
    public function createProductFromApi(string $barcode): Product
    {
        $catalogData = $this->productCatalogService->findByBarcode($barcode);

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
    protected function updateProductFromApi(Product $product): void
    {
        try {
            $catalogData = $this->productCatalogService->findByBarcode($product->barcode);

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

        $externalSources = array_map(
            fn (array $source): string => $source['key'],
            config('scanning.open_food_facts.sources', [])
        );

        if (!in_array($product->source, $externalSources, true)) {
            return false;
        }

        return $product->updated_at->diffInDays(now()) > config('scanning.stale_after_days', 30);
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
}
