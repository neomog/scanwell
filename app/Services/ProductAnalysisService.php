<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Ingredient;
use App\Models\FoodNutrition;
use App\Models\FoodScore;
use App\Models\CosmeticScore;
use App\Models\Scan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class ProductAnalysisService
{
    protected OpenFoodFactsService $openFoodFactsService;
    protected ScoreCalculationService $scoreCalculationService;

    public function __construct(
        OpenFoodFactsService $openFoodFactsService,
        ScoreCalculationService $scoreCalculationService
    ) {
        $this->openFoodFactsService = $openFoodFactsService;
        $this->scoreCalculationService = $scoreCalculationService;
    }

    /**
     * Analyze product by barcode
     */
    public function analyzeByBarcode(string $barcode, ?string $userId = null): Product
    {
        return DB::transaction(function () use ($barcode, $userId) {
            // Check if product exists in database
            $product = Product::with(['ingredients', 'nutrition', 'foodScore', 'cosmeticScore'])
                ->where('barcode', $barcode)
                ->first();

            if ($product) {
                // Update existing product if data is stale (e.g., older than 30 days)
                if ($product->updated_at->diffInDays(now()) > 30) {
                    $this->updateProductFromApi($product);
                }
            } else {
                // Fetch from API and create new product
                $product = $this->createProductFromApi($barcode);
            }

            // Record scan if user is authenticated
            if ($userId) {
                $this->recordScan($userId, $product);
            }

            return $product->load(['ingredients', 'nutrition', 'foodScore', 'cosmeticScore']);
        });
    }

    /**
     * Create new product from API data
     */
    protected function createProductFromApi(string $barcode): Product
{
    $apiData = $this->openFoodFactsService->getProductByBarcode($barcode);

    if (!$apiData) {
        throw new Exception("Product not found with barcode: {$barcode}");
    }

    // Determine product type and adjust category if needed
    $productType = $apiData['product_type'] ?? 'food';
    $categoryId = $apiData['category_id'];

    // Ensure beauty products get higher category IDs
    if ($productType === 'beauty' && $categoryId < 100) {
        $categoryId = 100 + ($categoryId % 100); // Map to 100+ range
    }

    // Create product
    $product = Product::create([
        'barcode' => $apiData['barcode'],
        'name' => $apiData['name'],
        'brand' => $apiData['brand'],
        'category_id' => $categoryId,
        'image_url' => $apiData['image_url'],
        'source' => $apiData['source'],
        'raw_data' => $apiData['raw_data'],
    ]);

    // Process ingredients (if any)
    if (!empty($apiData['ingredients'])) {
        $this->processIngredients($product, $apiData['ingredients']);
    }

    // Add nutrition data only for food products
    if ($productType === 'food' && !empty($apiData['nutrition'])) {
        $this->addNutritionData($product, $apiData['nutrition']);
    }

    // Calculate scores based on product type
    $this->calculateScores($product);

    return $product;
}

    /**
     * Update existing product with fresh API data
     */
    protected function updateProductFromApi(Product $product): void
    {
        try {
            $apiData = $this->openFoodFactsService->getProductByBarcode($product->barcode);

            if ($apiData) {
                $product->update([
                    'name' => $apiData['name'],
                    'brand' => $apiData['brand'],
                    'category_id' => $apiData['category_id'],
                    'image_url' => $apiData['image_url'],
                    'raw_data' => $apiData['raw_data'],
                ]);

                // Update ingredients
                DB::table('product_ingredients')->where('product_id', $product->id)->delete();
                $this->processIngredients($product, $apiData['ingredients'] ?? []);

                // Update nutrition
                if (!empty($apiData['nutrition'])) {
                    $this->updateNutritionData($product, $apiData['nutrition']);
                }

                // Recalculate scores
                $this->calculateScores($product);
            }
        } catch (Exception $e) {
            Log::error('Failed to update product from API', [
                'product_id' => $product->id,
                'barcode' => $product->barcode,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Process and link ingredients
     */
    protected function processIngredients(Product $product, array $ingredientsData): void
    {
        foreach ($ingredientsData as $ingredientData) {
            $ingredient = Ingredient::firstOrCreate(
                ['name' => $ingredientData['name']],
                [
                    'category' => $this->determineIngredientCategory($ingredientData['name']),
                    'risk_level' => $this->determineRiskLevel($ingredientData['name']),
                ]
            );

            $product->ingredients()->attach($ingredient->id);
        }
    }

    /**
     * Add nutrition data
     */
    protected function addNutritionData(Product $product, array $nutritionData): void
    {
        FoodNutrition::create([
            'product_id' => $product->id,
            'calories' => $nutritionData['calories'],
            'fat' => $nutritionData['fat'],
            'saturated_fat' => $nutritionData['saturated_fat'],
            'sugars' => $nutritionData['sugars'],
            'carbohydrates' => $nutritionData['carbohydrates'],
//            'salt' => $nutritionData['salt'],
            'protein' => $nutritionData['protein'],
            'fiber' => $nutritionData['fiber'],
            'sodium' => $nutritionData['sodium'],
        ]);
    }

    /**
     * Update nutrition data
     */
    protected function updateNutritionData(Product $product, array $nutritionData): void
    {
        if ($product->nutrition) {
            $product->nutrition->update($nutritionData);
        } else {
            $this->addNutritionData($product, $nutritionData);
        }
    }

    /**
     * Calculate and save scores
     */
    protected function calculateScores(Product $product): void
{
    $rawData = $product->raw_data;
    $productType = $rawData['product_type'] ?? 'food';

    if ($productType === 'food' || $product->isFood()) {
        $scores = $this->scoreCalculationService->calculateFoodScores($product);

        FoodScore::updateOrCreate(
            ['product_id' => $product->id],
            [
                'overall_score' => $scores['overall'],
                'nutrition_score' => $scores['nutrition'],
                'ingredient_score' => $scores['ingredient'] ?? 50,
                'additive_score' => $scores['additive'],
                'processing_score' => $scores['processing'],
                'explanation_text' => $scores['explanation'],
                'calculated_at' => now(),
            ]
        );
    } elseif ($productType === 'beauty' || $product->isCosmetic()) {
        $scores = $this->scoreCalculationService->calculateCosmeticScores($product);

        CosmeticScore::updateOrCreate(
            ['product_id' => $product->id],
            [
                'overall_score' => $scores['overall'],
                'irritant_score' => $scores['irritant'],
                'endocrine_score' => $scores['endocrine'],
                'allergen_score' => $scores['allergen'] ?? 50,
                'environmental_score' => $scores['environmental'] ?? 50,
                'explanation_text' => $scores['explanation'],
                'calculated_at' => now(),
            ]
        );
    }
}

    /**
     * Record scan in database
     */
    protected function recordScan(string $userId, Product $product): void
    {
        Scan::create([
            'user_id' => $userId,
            'product_id' => $product->id,
            'barcode' => $product->barcode,
            'scan_timestamp' => now(),
            'status' => 'completed',
            'scan_metadata' => [
                'source' => $product->source,
                'device_type' => request()->userAgent(),
            ],
        ]);
    }

    /**
     * Determine ingredient category (simplified)
     */
    protected function determineIngredientCategory(string $ingredientName): string
    {
        $ingredientName = strtolower($ingredientName);

        if (str_contains($ingredientName, 'sugar') || str_contains($ingredientName, 'syrup')) {
            return 'sweetener';
        } elseif (str_contains($ingredientName, 'oil') || str_contains($ingredientName, 'fat')) {
            return 'fat';
        } elseif (str_contains($ingredientName, 'preservative')) {
            return 'preservative';
        }

        return 'other';
    }

    /**
     * Determine risk level (simplified)
     */
    protected function determineRiskLevel(string $ingredientName): string
    {
        $ingredientName = strtolower($ingredientName);

        $highRisk = ['aspartame', 'saccharin', 'msg', 'sodium nitrite'];
        $mediumRisk = ['high fructose corn syrup', 'partially hydrogenated'];

        if (in_array($ingredientName, $highRisk)) {
            return 'high';
        } elseif (in_array($ingredientName, $mediumRisk)) {
            return 'medium';
        }

        return 'low';
    }
}
