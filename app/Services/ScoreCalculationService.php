<?php

namespace App\Services;

use App\Models\Product;

class ScoreCalculationService
{
    public function __construct(
        protected ProductFamilyResolver $productFamilyResolver
    ) {
    }

    /**
     * Calculate food product scores on a conservative 0-100 scale.
     */
    public function calculateFoodScores(Product $product): array
    {
        $nutritionScore = $this->calculateNutritionScore($product);
        $ingredientScore = $this->calculateIngredientScore($product);
        $additiveScore = $this->calculateAdditiveScore($product);
        $processingScore = $this->calculateProcessingScore($product);
        $packagingScore = $this->calculatePackagingScore($product);

        $overallScore = round(
            ($nutritionScore * 0.35) +
            ($ingredientScore * 0.25) +
            ($additiveScore * 0.15) +
            ($processingScore * 0.10) +
            ($packagingScore * 0.15)
        );

        $warnings = $this->buildFoodWarnings(
            $product,
            $nutritionScore,
            $ingredientScore,
            $additiveScore,
            $processingScore,
            $packagingScore
        );

        $benefits = $this->buildFoodBenefits($product, $nutritionScore, $ingredientScore);

        if ($this->hasSparseFoodData($product)) {
            $overallScore = min($overallScore, config('scanning.sparse_food_score_cap', 65));
            $warnings[] = 'Score capped because verified nutrition or ingredient data is incomplete.';
        }

        if ($this->productFamilyResolver->isWater($product) && $this->productFamilyResolver->hasPlasticPackaging($product)) {
            $overallScore = min($overallScore, config('scanning.water_plastic_score_cap', 89));
            $warnings[] = 'Plastic bottled water is capped below a perfect score because packaging impact matters.';
        }

        $overallScore = min($overallScore, config('scanning.perfect_score_cap', 95));

        $scoreBreakdown = [
            'nutrition' => $nutritionScore,
            'ingredient' => $ingredientScore,
            'additive' => $additiveScore,
            'processing' => $processingScore,
            'packaging' => $packagingScore,
            'confidence' => data_get($product->raw_data, '_scanwell.confidence'),
            'completeness' => data_get($product->raw_data, '_scanwell.completeness'),
        ];

        return [
            'overall' => max(0, min(100, $overallScore)),
            'nutrition' => $nutritionScore,
            'ingredient' => $ingredientScore,
            'additive' => $additiveScore,
            'processing' => $processingScore,
            'nova_group' => data_get($product->raw_data, 'nova_group'),
            'nutriscore_grade' => strtoupper((string) data_get($product->raw_data, 'nutriscore_grade')) ?: null,
            'score_breakdown' => $scoreBreakdown,
            'warnings' => array_values(array_unique($warnings)),
            'benefits' => array_values(array_unique($benefits)),
            'explanation' => $this->generateFoodExplanation($product, $scoreBreakdown, $warnings, $benefits),
        ];
    }

    /**
     * Calculate cosmetic product scores.
     */
    public function calculateCosmeticScores(Product $product): array
    {
        $ingredientNames = $product->ingredients->pluck('name')->map(
            fn ($name) => strtolower((string) $name)
        );

        if ($ingredientNames->isEmpty()) {
            $warnings = [
                'Ingredient list is missing, so the cosmetic score is capped conservatively.',
            ];

            return [
                'overall' => config('scanning.sparse_cosmetic_score_cap', 55),
                'irritant' => 45,
                'endocrine' => 45,
                'allergen' => 45,
                'environmental' => 50,
                'score_breakdown' => [
                    'irritant' => 45,
                    'endocrine' => 45,
                    'allergen' => 45,
                    'environmental' => 50,
                    'confidence' => data_get($product->raw_data, '_scanwell.confidence'),
                    'completeness' => data_get($product->raw_data, '_scanwell.completeness'),
                ],
                'warnings' => $warnings,
                'benefits' => [],
                'skin_types_suitable' => ['normal'],
                'explanation' => 'Ingredient data is incomplete, so this cosmetic score is intentionally conservative.',
            ];
        }

        $irritantScore = $this->calculateIrritantScore($ingredientNames);
        $endocrineScore = $this->calculateEndocrineScore($ingredientNames);
        $allergenScore = $this->calculateAllergenScore($ingredientNames);
        $environmentalScore = $this->calculateEnvironmentalScore($ingredientNames);

        $overallScore = round(($irritantScore + $endocrineScore + $allergenScore + $environmentalScore) / 4);
        $overallScore = min($overallScore, config('scanning.perfect_score_cap', 95));

        $warnings = $this->buildCosmeticWarnings(
            $ingredientNames->all(),
            $irritantScore,
            $endocrineScore,
            $allergenScore
        );

        $benefits = $this->buildCosmeticBenefits(
            $ingredientNames->all(),
            $irritantScore,
            $endocrineScore,
            $allergenScore
        );

        $scoreBreakdown = [
            'irritant' => $irritantScore,
            'endocrine' => $endocrineScore,
            'allergen' => $allergenScore,
            'environmental' => $environmentalScore,
            'confidence' => data_get($product->raw_data, '_scanwell.confidence'),
            'completeness' => data_get($product->raw_data, '_scanwell.completeness'),
        ];

        return [
            'overall' => max(0, min(100, $overallScore)),
            'irritant' => $irritantScore,
            'endocrine' => $endocrineScore,
            'allergen' => $allergenScore,
            'environmental' => $environmentalScore,
            'score_breakdown' => $scoreBreakdown,
            'warnings' => array_values(array_unique($warnings)),
            'benefits' => array_values(array_unique($benefits)),
            'skin_types_suitable' => $this->determineSuitableSkinTypes($ingredientNames->all(), $warnings),
            'explanation' => $this->generateCosmeticExplanation($scoreBreakdown, $warnings, $benefits),
        ];
    }

    protected function calculateNutritionScore(Product $product): float
    {
        if (!$product->nutrition || !$this->nutritionHasValues($product)) {
            return $this->productFamilyResolver->isWater($product) ? 90 : 45;
        }

        if ($this->productFamilyResolver->isWater($product)) {
            return 95;
        }

        $nutrition = $product->nutrition;
        $score = 85;

        if (($nutrition->sugars ?? 0) > 5) {
            $score -= (($nutrition->sugars ?? 0) - 5) * 2;
        }

        if (($nutrition->saturated_fat ?? 0) > 5) {
            $score -= (($nutrition->saturated_fat ?? 0) - 5) * 2.5;
        }

        if (($nutrition->fat ?? 0) > 17) {
            $score -= (($nutrition->fat ?? 0) - 17) * 1.2;
        }

        if (($nutrition->sodium ?? 0) > 400) {
            $score -= (($nutrition->sodium ?? 0) - 400) / 75;
        }

        if (($nutrition->fiber ?? 0) >= 3) {
            $score += min(8, ($nutrition->fiber ?? 0) * 1.5);
        }

        if (($nutrition->protein ?? 0) >= 10) {
            $score += min(6, (($nutrition->protein ?? 0) - 10) * 0.75);
        }

        return max(0, min(100, round($score)));
    }

    protected function calculateIngredientScore(Product $product): float
    {
        $ingredients = $product->ingredients;

        if ($ingredients->isEmpty()) {
            return $this->productFamilyResolver->isWater($product) ? 90 : 45;
        }

        $totalScore = 0;

        foreach ($ingredients as $ingredient) {
            $totalScore += match ($ingredient->risk_level) {
                'low' => 90,
                'medium' => 55,
                'high' => 20,
                default => 45,
            };
        }

        $score = $totalScore / max(1, $ingredients->count());

        if ($ingredients->count() <= 5) {
            $score += 5;
        } elseif ($ingredients->count() >= 15) {
            $score -= 10;
        }

        return max(0, min(100, round($score)));
    }

    protected function calculateAdditiveScore(Product $product): float
    {
        $ingredients = $product->ingredients;

        if ($ingredients->isEmpty()) {
            return $this->productFamilyResolver->isWater($product) ? 90 : 45;
        }

        $riskCounts = [
            'high' => 0,
            'medium' => 0,
            'low' => 0,
        ];

        foreach ($ingredients as $ingredient) {
            $riskCounts[$ingredient->risk_level ?? 'low']++;
        }

        $score = (
            ($riskCounts['low'] * 90) +
            ($riskCounts['medium'] * 55) +
            ($riskCounts['high'] * 20)
        ) / max(1, $ingredients->count());

        return max(0, min(100, round($score)));
    }

    protected function calculateProcessingScore(Product $product): float
    {
        $novaGroup = data_get($product->raw_data, 'nova_group');

        if ($novaGroup !== null) {
            return match ((int) $novaGroup) {
                1 => 90,
                2 => 75,
                3 => 50,
                4 => 25,
                default => 50,
            };
        }

        $ingredientCount = $product->ingredients->count();

        if ($this->productFamilyResolver->isWater($product) && $ingredientCount <= 1) {
            return 85;
        }

        return match (true) {
            $ingredientCount <= 5 => 80,
            $ingredientCount <= 10 => 65,
            $ingredientCount <= 15 => 45,
            default => 30,
        };
    }

    protected function calculatePackagingScore(Product $product): float
    {
        $materials = $this->productFamilyResolver->packagingMaterials($product);

        if ($materials === []) {
            return $this->productFamilyResolver->isWater($product) ? 80 : 75;
        }

        $score = 85;

        if (in_array('plastic', $materials, true)) {
            $score = 65;
        }

        if (in_array('glass', $materials, true)) {
            $score = max($score, 88);
        }

        if (in_array('metal', $materials, true)) {
            $score = max($score, 78);
        }

        if (in_array('paper', $materials, true)) {
            $score = max($score, 75);
        }

        if ($this->productFamilyResolver->isWater($product) && in_array('plastic', $materials, true)) {
            $score = 60;
        }

        return max(0, min(100, round($score)));
    }

    protected function calculateIrritantScore(iterable $ingredientNames): float
    {
        $irritants = ['alcohol', 'fragrance', 'parfum', 'essential oil', 'menthol'];
        $irritantCount = $this->countKeywordMatches($ingredientNames, $irritants);

        return max(0, 90 - ($irritantCount * 18));
    }

    protected function calculateEndocrineScore(iterable $ingredientNames): float
    {
        $disruptors = ['paraben', 'phthalate', 'bpa', 'triclosan'];
        $disruptorCount = $this->countKeywordMatches($ingredientNames, $disruptors);

        return max(0, 90 - ($disruptorCount * 25));
    }

    protected function calculateAllergenScore(iterable $ingredientNames): float
    {
        $allergens = ['fragrance', 'parfum', 'limonene', 'linalool', 'citral', 'geraniol'];
        $allergenCount = $this->countKeywordMatches($ingredientNames, $allergens);

        return max(0, 90 - ($allergenCount * 15));
    }

    protected function calculateEnvironmentalScore(iterable $ingredientNames): float
    {
        $environmentalRisks = ['microplastic', 'polyethylene', 'siloxane', 'triclosan'];
        $riskCount = $this->countKeywordMatches($ingredientNames, $environmentalRisks);

        return max(0, 85 - ($riskCount * 18));
    }

    protected function hasSparseFoodData(Product $product): bool
    {
        if ($this->productFamilyResolver->isWater($product)) {
            return false;
        }

        return !$product->nutrition && $product->ingredients->count() < 2;
    }

    protected function buildFoodWarnings(
        Product $product,
        float $nutritionScore,
        float $ingredientScore,
        float $additiveScore,
        float $processingScore,
        float $packagingScore
    ): array {
        $warnings = [];
        $nutrition = $product->nutrition;

        if ($nutritionScore < 50) {
            $warnings[] = 'Nutritional profile is weak for this product category.';
        }

        if ($ingredientScore < 50) {
            $warnings[] = 'Ingredient quality contains moderate or high-risk items.';
        }

        if ($additiveScore < 50) {
            $warnings[] = 'Multiple additive or processing concerns were detected.';
        }

        if ($processingScore < 45) {
            $warnings[] = 'Product appears to be highly processed.';
        }

        if ($packagingScore < 70) {
            $warnings[] = 'Packaging reduces the product score.';
        }

        if (($nutrition?->sugars ?? 0) > 10) {
            $warnings[] = 'Sugar content is above the preferred threshold.';
        }

        if (($nutrition?->sodium ?? 0) > 400) {
            $warnings[] = 'Sodium content is above the preferred threshold.';
        }

        return $warnings;
    }

    protected function buildFoodBenefits(Product $product, float $nutritionScore, float $ingredientScore): array
    {
        $benefits = [];
        $nutrition = $product->nutrition;

        if ($this->productFamilyResolver->isWater($product)) {
            $benefits[] = 'Hydration-friendly product profile.';
        }

        if ($nutritionScore >= 75) {
            $benefits[] = 'Strong nutritional profile for its category.';
        }

        if (($nutrition?->sugars ?? 0) <= 5) {
            $benefits[] = 'Low sugar content.';
        }

        if (($nutrition?->sodium ?? 0) <= 150 && $nutrition) {
            $benefits[] = 'Relatively low sodium.';
        }

        if ($ingredientScore >= 75) {
            $benefits[] = 'Ingredient list is relatively clean.';
        }

        return $benefits;
    }

    protected function buildCosmeticWarnings(array $ingredientNames, float $irritantScore, float $endocrineScore, float $allergenScore): array
    {
        $warnings = [];

        if ($irritantScore < 60) {
            $warnings[] = 'Potential irritants were detected in the ingredient list.';
        }

        if ($endocrineScore < 60) {
            $warnings[] = 'Potential endocrine disruptors were detected.';
        }

        if ($allergenScore < 60) {
            $warnings[] = 'Common fragrance allergens were detected.';
        }

        if ($this->countKeywordMatches($ingredientNames, ['fragrance', 'parfum']) > 0) {
            $warnings[] = 'Fragrance is present and may affect sensitive users.';
        }

        return $warnings;
    }

    protected function buildCosmeticBenefits(array $ingredientNames, float $irritantScore, float $endocrineScore, float $allergenScore): array
    {
        $benefits = [];

        if ($irritantScore >= 75) {
            $benefits[] = 'Low apparent irritant load.';
        }

        if ($endocrineScore >= 75) {
            $benefits[] = 'No obvious endocrine disruptors detected.';
        }

        if ($allergenScore >= 75) {
            $benefits[] = 'Low apparent fragrance-allergen load.';
        }

        if ($this->countKeywordMatches($ingredientNames, ['fragrance', 'parfum']) === 0) {
            $benefits[] = 'Fragrance-free based on the ingredient list.';
        }

        return $benefits;
    }

    protected function determineSuitableSkinTypes(array $ingredientNames, array $warnings): array
    {
        $skinTypes = ['normal'];

        if ($this->countKeywordMatches($ingredientNames, ['fragrance', 'parfum']) === 0) {
            $skinTypes[] = 'sensitive';
        }

        if ($this->countKeywordMatches($ingredientNames, ['oil', 'butter', 'wax']) === 0) {
            $skinTypes[] = 'oily';
        } else {
            $skinTypes[] = 'dry';
        }

        if ($warnings === []) {
            $skinTypes[] = 'combination';
        }

        return array_values(array_unique($skinTypes));
    }

    protected function generateFoodExplanation(Product $product, array $scoreBreakdown, array $warnings, array $benefits): string
    {
        $familyLabel = $this->productFamilyResolver->isWater($product) ? 'bottled water' : 'food product';
        $headline = "This {$familyLabel} scored {$scoreBreakdown['nutrition']} for nutrition and {$scoreBreakdown['packaging']} for packaging.";

        if ($warnings !== []) {
            return $headline . ' Key concern: ' . $warnings[0];
        }

        if ($benefits !== []) {
            return $headline . ' Main positive: ' . $benefits[0];
        }

        return $headline;
    }

    protected function generateCosmeticExplanation(array $scoreBreakdown, array $warnings, array $benefits): string
    {
        $headline = "This cosmetic scored {$scoreBreakdown['irritant']} for irritant risk and {$scoreBreakdown['endocrine']} for endocrine risk.";

        if ($warnings !== []) {
            return $headline . ' Key concern: ' . $warnings[0];
        }

        if ($benefits !== []) {
            return $headline . ' Main positive: ' . $benefits[0];
        }

        return $headline;
    }

    protected function countKeywordMatches(iterable $ingredientNames, array $keywords): int
    {
        $count = 0;

        foreach ($keywords as $keyword) {
            foreach ($ingredientNames as $ingredientName) {
                if (str_contains((string) $ingredientName, $keyword)) {
                    $count++;
                    break;
                }
            }
        }

        return $count;
    }

    protected function nutritionHasValues(Product $product): bool
    {
        if (!$product->nutrition) {
            return false;
        }

        foreach ([
            'calories',
            'fat',
            'saturated_fat',
            'carbohydrates',
            'fiber',
            'sugars',
            'protein',
            'sodium',
        ] as $field) {
            if ($product->nutrition->{$field} !== null && $product->nutrition->{$field} !== '') {
                return true;
            }
        }

        return false;
    }
}
