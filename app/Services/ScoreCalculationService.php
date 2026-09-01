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

        $analysis = $this->buildFoodAnalysis($product, $nutritionScore, $ingredientScore, $additiveScore, $processingScore, $packagingScore);

        $scoreBreakdown = [
            'nutrition' => $nutritionScore,
            'ingredient' => $ingredientScore,
            'additive' => $additiveScore,
            'processing' => $processingScore,
            'packaging' => $packagingScore,
            'confidence' => data_get($product->raw_data, '_scanwell.confidence'),
            'completeness' => data_get($product->raw_data, '_scanwell.completeness'),
            'analysis' => $analysis,
        ];

        return [
            'overall' => max(0, min(100, $overallScore)),
            'nutrition' => $nutritionScore,
            'ingredient' => $ingredientScore,
            'additive' => $additiveScore,
            'processing' => $processingScore,
            'nova_group' => data_get($product->raw_data, 'nova_group'),
            'nutriscore_grade' => $this->normalizeNutriScoreGrade(data_get($product->raw_data, 'nutriscore_grade')),
            'score_breakdown' => $scoreBreakdown,
            'warnings' => array_values(array_unique($warnings)),
            'benefits' => array_values(array_unique($benefits)),
            'explanation' => $this->generateFoodExplanation($product, $scoreBreakdown, $warnings, $benefits),
            'analysis' => $analysis,
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

        $analysis = $this->buildCosmeticAnalysis($product, $irritantScore, $endocrineScore, $allergenScore, $environmentalScore);

        $scoreBreakdown = [
            'irritant' => $irritantScore,
            'endocrine' => $endocrineScore,
            'allergen' => $allergenScore,
            'environmental' => $environmentalScore,
            'confidence' => data_get($product->raw_data, '_scanwell.confidence'),
            'completeness' => data_get($product->raw_data, '_scanwell.completeness'),
            'analysis' => $analysis,
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
            'analysis' => $analysis,
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

    protected function buildFoodAnalysis(
        Product $product,
        float $nutritionScore,
        float $ingredientScore,
        float $additiveScore,
        float $processingScore,
        float $packagingScore
    ): array {
        $negatives = [];
        $positives = [];
        $nutrition = $product->nutrition;

        if ($nutrition) {
            if (($nutrition->sugars ?? 0) > 10) {
                $negatives[] = $this->insight('sugar', 'nutrition', 'Sugar', $nutrition->sugars . 'g per 100g', 'high', 'Very high sugar content.', 'Sugar is above the preferred threshold for this product category.', 'nutrition_label');
            } elseif (($nutrition->sugars ?? 0) <= 5) {
                $positives[] = $this->insight('sugar', 'nutrition', 'Sugar', $nutrition->sugars . 'g per 100g', 'low', 'Low sugar content.', 'Sugar is within the preferred threshold used by the scoring engine.', 'nutrition_label');
            }

            if (($nutrition->saturated_fat ?? 0) > 5) {
                $negatives[] = $this->insight('saturated-fat', 'nutrition', 'Saturated fat', $nutrition->saturated_fat . 'g per 100g', 'high', 'High saturated fat content.', 'This reduces the nutrition component of the score.', 'nutrition_label');
            }

            if (($nutrition->sodium ?? 0) > 400) {
                $negatives[] = $this->insight('sodium', 'nutrition', 'Sodium', $nutrition->sodium . 'mg per 100g', 'medium', 'High sodium content.', 'Sodium is above the preferred threshold used by the scoring engine.', 'nutrition_label');
            } elseif (($nutrition->sodium ?? 0) <= 150) {
                $positives[] = $this->insight('sodium', 'nutrition', 'Sodium', $nutrition->sodium . 'mg per 100g', 'low', 'Relatively low sodium.', 'Sodium is within the preferred range used by the scoring engine.', 'nutrition_label');
            }

            if (($nutrition->fiber ?? 0) >= 3) {
                $positives[] = $this->insight('fiber', 'nutrition', 'Fiber', $nutrition->fiber . 'g per 100g', 'low', 'Good fiber content.', 'Fiber contributes positively to the nutrition score.', 'nutrition_label');
            }
        }

        foreach ($product->ingredients as $ingredient) {
            $risk = (string) ($ingredient->risk_level ?? 'unknown');
            $name = trim((string) $ingredient->name);
            $healthEffects = is_array($ingredient->health_effects)
                ? implode(', ', array_map('strval', $ingredient->health_effects))
                : (string) ($ingredient->health_effects ?? '');
            $details = (string) ($ingredient->description ?: ($healthEffects ?: 'Ingredient information is based on the current ingredient reference data.'));
            $isAdditive = (bool) ($ingredient->pivot?->is_additive ?? false);

            if (in_array($risk, ['high', 'medium'], true) || $isAdditive) {
                $negatives[] = $this->insight(
                    'ingredient-' . $ingredient->id,
                    'ingredient',
                    $name,
                    $isAdditive ? 'Additive' : ucfirst($risk) . ' concern',
                    $risk === 'high' ? 'high' : 'medium',
                    $isAdditive ? 'Additive or processing concern detected.' : ucfirst($risk) . ' ingredient concern detected.',
                    $details,
                    'ingredient_reference',
                    $ingredient->id
                );
            } elseif ($risk === 'low') {
                $positives[] = $this->insight(
                    'ingredient-' . $ingredient->id,
                    'ingredient',
                    $name,
                    'Low concern',
                    'low',
                    'No significant concern found in the current ingredient reference data.',
                    $details,
                    'ingredient_reference',
                    $ingredient->id
                );
            }
        }

        if ($processingScore < 45) {
            $negatives[] = $this->insight('processing', 'processing', 'Processing', (string) $processingScore . '/100', 'high', 'Product appears highly processed.', 'Processing score is based on available NOVA data and ingredient complexity.', 'scoring_rules');
        }

        if ($packagingScore < 70) {
            $negatives[] = $this->insight('packaging', 'packaging', 'Packaging', (string) $packagingScore . '/100', 'medium', 'Packaging reduces the score.', 'Packaging impact is based on the verified packaging materials available for this product.', 'packaging_data');
        }

        return ['negatives' => array_values($negatives), 'positives' => array_values($positives)];
    }

    protected function buildCosmeticAnalysis(Product $product, float $irritantScore, float $endocrineScore, float $allergenScore, float $environmentalScore): array
    {
        $negatives = [];
        $positives = [];

        foreach ($product->ingredients as $ingredient) {
            $risk = (string) ($ingredient->risk_level ?? 'unknown');
            $name = trim((string) $ingredient->name);
            $healthEffects = is_array($ingredient->health_effects)
                ? implode(', ', array_map('strval', $ingredient->health_effects))
                : (string) ($ingredient->health_effects ?? '');
            $details = (string) ($ingredient->description ?: ($healthEffects ?: 'Ingredient information is based on the current ingredient reference data.'));

            if (in_array($risk, ['high', 'medium'], true)) {
                $negatives[] = $this->insight('ingredient-' . $ingredient->id, 'ingredient', $name, ucfirst($risk) . ' concern', $risk === 'high' ? 'high' : 'medium', 'Potential cosmetic ingredient concern detected.', $details, 'ingredient_reference', $ingredient->id);
            } elseif ($risk === 'low') {
                $positives[] = $this->insight('ingredient-' . $ingredient->id, 'ingredient', $name, 'Low concern', 'low', 'No significant concern found in the current ingredient reference data.', $details, 'ingredient_reference', $ingredient->id);
            }
        }

        foreach ([
            ['irritant', 'Irritants', $irritantScore],
            ['endocrine', 'Endocrine disruptors', $endocrineScore],
            ['allergen', 'Fragrance allergens', $allergenScore],
            ['environmental', 'Environmental impact', $environmentalScore],
        ] as [$key, $title, $score]) {
            if ($score < 60) {
                $negatives[] = $this->insight($key, 'cosmetic', $title, (string) $score . '/100', 'medium', $title . ' concern detected.', 'This component was reduced by the ingredient rules used for cosmetic analysis.', 'scoring_rules');
            } elseif ($score >= 75) {
                $positives[] = $this->insight($key, 'cosmetic', $title, (string) $score . '/100', 'low', 'Low apparent concern.', 'This component scored well under the current cosmetic rules.', 'scoring_rules');
            }
        }

        return ['negatives' => array_values($negatives), 'positives' => array_values($positives)];
    }

    protected function insight(string $id, string $type, string $title, ?string $value, string $severity, string $summary, string $details, string $source, ?string $ingredientId = null): array
    {
        return array_filter([
            'id' => $id,
            'type' => $type,
            'title' => $title,
            'value' => $value,
            'severity' => $severity,
            'summary' => $summary,
            'details' => $details,
            'source' => $source,
            'ingredient_id' => $ingredientId,
        ], fn ($item): bool => $item !== null && $item !== '');
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

    protected function normalizeNutriScoreGrade(mixed $grade): ?string
    {
        $normalized = strtoupper(trim((string) $grade));

        if ($normalized === '') {
            return null;
        }

        return in_array($normalized, ['A', 'B', 'C', 'D', 'E'], true)
            ? $normalized
            : null;
    }
}
