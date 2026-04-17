<?php

namespace App\Services;

use App\Models\CosmeticScore;
use App\Models\FoodNutrition;
use App\Models\FoodScore;
use App\Models\Ingredient;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class ProductAnalysisService
{
    public function __construct(
        protected ProductCatalogService $productCatalogService,
        protected ProductFamilyResolver $productFamilyResolver,
        protected ScoreCalculationService $scoreCalculationService
    ) {
    }

    /**
     * Analyze product by barcode.
     */
    public function analyzeByBarcode(string $barcode, ?string $userId = null): Product
    {
        return DB::transaction(function () use ($barcode) {
            $product = Product::with(['ingredients', 'nutrition', 'foodScore', 'cosmeticScore'])
                ->where('barcode', $barcode)
                ->first();

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

        $product = Product::create([
            'barcode' => $catalogData['barcode'],
            'name' => $catalogData['name'],
            'brand' => $catalogData['brand'],
            'category_id' => $categoryId,
            'image_url' => $catalogData['image_url'],
            'source' => $catalogData['source'],
            'raw_data' => $this->buildStoredRawData($catalogData, $productFamily),
        ]);

        if (!empty($catalogData['ingredients'])) {
            $this->processIngredients($product, $catalogData['ingredients']);
        }

        if (
            $this->productFamilyResolver->supportsFoodScore($productFamily)
            && $this->hasMeaningfulNutrition($catalogData['nutrition'] ?? [])
        ) {
            $this->addNutritionData($product, $catalogData['nutrition']);
        }

        $this->calculateScores($product);

        return $product->load(['ingredients', 'nutrition', 'foodScore', 'cosmeticScore']);
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

            $product->update([
                'name' => $catalogData['name'],
                'brand' => $catalogData['brand'],
                'category_id' => $categoryId,
                'image_url' => $catalogData['image_url'],
                'source' => $catalogData['source'],
                'raw_data' => $this->buildStoredRawData($catalogData, $productFamily),
            ]);

            DB::table('product_ingredients')->where('product_id', $product->id)->delete();
            $this->processIngredients($product, $catalogData['ingredients'] ?? []);

            if ($this->productFamilyResolver->supportsFoodScore($productFamily)) {
                $this->updateNutritionData($product, $catalogData['nutrition'] ?? []);
            } elseif ($product->nutrition) {
                $product->nutrition()->delete();
            }

            $this->calculateScores($product->fresh(['ingredients', 'nutrition']));
        } catch (\Throwable $exception) {
            Log::error('Failed to update product from catalog', [
                'product_id' => $product->id,
                'barcode' => $product->barcode,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    protected function processIngredients(Product $product, array $ingredientsData): void
    {
        $syncPayload = [];

        foreach ($ingredientsData as $ingredientData) {
            $ingredientName = trim((string) ($ingredientData['name'] ?? ''));

            if ($ingredientName === '') {
                continue;
            }

            $ingredient = Ingredient::firstOrCreate(
                ['name' => $ingredientName],
                [
                    'category' => $this->determineIngredientCategory($ingredientName),
                    'risk_level' => $this->determineRiskLevel($ingredientName),
                ]
            );

            $syncPayload[$ingredient->id] = [
                'percentage' => $ingredientData['percent'] ?? $ingredientData['percentage'] ?? null,
                'is_additive' => $this->looksLikeAdditive($ingredientName),
                'origin' => $ingredientData['origin'] ?? null,
            ];
        }

        if ($syncPayload !== []) {
            $product->ingredients()->syncWithoutDetaching($syncPayload);
        }
    }

    protected function addNutritionData(Product $product, array $nutritionData): void
    {
        FoodNutrition::updateOrCreate(
            ['product_id' => $product->id],
            [
                'calories' => $nutritionData['calories'] ?? null,
                'fat' => $nutritionData['fat'] ?? null,
                'saturated_fat' => $nutritionData['saturated_fat'] ?? null,
                'sugars' => $nutritionData['sugars'] ?? null,
                'carbohydrates' => $nutritionData['carbohydrates'] ?? null,
                'protein' => $nutritionData['protein'] ?? null,
                'fiber' => $nutritionData['fiber'] ?? null,
                'sodium' => $nutritionData['sodium'] ?? null,
                'serving_size' => $nutritionData['serving_size'] ?? null,
            ]
        );
    }

    protected function updateNutritionData(Product $product, array $nutritionData): void
    {
        if (!$this->hasMeaningfulNutrition($nutritionData)) {
            if ($product->nutrition) {
                $product->nutrition()->delete();
            }

            return;
        }

        $this->addNutritionData($product, $nutritionData);
    }

    protected function calculateScores(Product $product): void
    {
        $productFamily = $this->productFamilyResolver->resolveFromProduct($product);

        if ($this->productFamilyResolver->supportsFoodScore($productFamily)) {
            $scores = $this->scoreCalculationService->calculateFoodScores($product);

            FoodScore::updateOrCreate(
                ['product_id' => $product->id],
                [
                    'overall_score' => $scores['overall'],
                    'nutrition_score' => $scores['nutrition'],
                    'ingredient_score' => $scores['ingredient'],
                    'additive_score' => $scores['additive'],
                    'processing_score' => $scores['processing'],
                    'nova_group' => $scores['nova_group'],
                    'nutriscore_grade' => $scores['nutriscore_grade'],
                    'score_breakdown' => $scores['score_breakdown'],
                    'explanation_text' => $scores['explanation'],
                    'warnings' => $scores['warnings'],
                    'benefits' => $scores['benefits'],
                    'calculated_at' => now(),
                ]
            );

            $product->cosmeticScore()->delete();

            return;
        }

        if ($this->productFamilyResolver->supportsCosmeticScore($productFamily)) {
            $scores = $this->scoreCalculationService->calculateCosmeticScores($product);

            CosmeticScore::updateOrCreate(
                ['product_id' => $product->id],
                [
                    'overall_score' => $scores['overall'],
                    'irritant_score' => $scores['irritant'],
                    'endocrine_score' => $scores['endocrine'],
                    'allergen_score' => $scores['allergen'],
                    'environmental_score' => $scores['environmental'],
                    'score_breakdown' => $scores['score_breakdown'],
                    'explanation_text' => $scores['explanation'],
                    'warnings' => $scores['warnings'],
                    'benefits' => $scores['benefits'],
                    'skin_types_suitable' => $scores['skin_types_suitable'],
                    'calculated_at' => now(),
                ]
            );

            $product->foodScore()->delete();

            return;
        }

        $product->foodScore()->delete();
        $product->cosmeticScore()->delete();
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

    protected function determineIngredientCategory(string $ingredientName): string
    {
        $ingredientName = strtolower($ingredientName);

        if (str_contains($ingredientName, 'sugar') || str_contains($ingredientName, 'syrup') || str_contains($ingredientName, 'sweetener')) {
            return 'sweetener';
        }

        if (str_contains($ingredientName, 'oil') || str_contains($ingredientName, 'fat') || str_contains($ingredientName, 'butter')) {
            return 'fat';
        }

        if (str_contains($ingredientName, 'preserv')) {
            return 'preservative';
        }

        if (str_contains($ingredientName, 'color') || preg_match('/\be\d{3}\b/i', $ingredientName)) {
            return 'additive';
        }

        return 'other';
    }

    protected function determineRiskLevel(string $ingredientName): string
    {
        $ingredientName = strtolower($ingredientName);

        $highRiskKeywords = [
            'aspartame', 'saccharin', 'msg', 'monosodium glutamate', 'sodium nitrite',
            'potassium bromate', 'bha', 'bht', 'red 40', 'yellow 5', 'blue 1',
            'triclosan', 'phthalate', 'paraben',
        ];

        foreach ($highRiskKeywords as $keyword) {
            if (str_contains($ingredientName, $keyword)) {
                return 'high';
            }
        }

        $mediumRiskKeywords = [
            'high fructose corn syrup', 'partially hydrogenated', 'carrageenan',
            'sodium benzoate', 'potassium sorbate', 'fragrance', 'parfum',
        ];

        foreach ($mediumRiskKeywords as $keyword) {
            if (str_contains($ingredientName, $keyword)) {
                return 'medium';
            }
        }

        return 'low';
    }

    protected function looksLikeAdditive(string $ingredientName): bool
    {
        $ingredientName = strtolower($ingredientName);

        foreach (['lecithin', 'emulsifier', 'preserv', 'color', 'flavor', 'flavour', 'stabilizer'] as $keyword) {
            if (str_contains($ingredientName, $keyword)) {
                return true;
            }
        }

        return (bool) preg_match('/\be\d{3}\b/i', $ingredientName);
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
