<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Facades\Log;

class ScoreCalculationService
{
    /**
     * Calculate food product scores (0-100 scale, higher is better)
     */
    public function calculateFoodScores(Product $product): array
    {
        $nutritionScore = $this->calculateNutritionScore($product);
        $additiveScore = $this->calculateAdditiveScore($product);
        $processingScore = $this->calculateProcessingScore($product);
        $ingredientScore = $this->calculateIngredientScore($product); // Added this

        // Weighted average
        $overallScore = round(
            ($nutritionScore * 0.4) +
            ($ingredientScore * 0.3) +
            ($additiveScore * 0.2) +
            ($processingScore * 0.1)
        );

        $explanation = $this->generateFoodExplanation(
            $nutritionScore,
            $ingredientScore,
            $additiveScore,
            $processingScore
        );

        return [
            'overall' => $overallScore,
            'nutrition' => $nutritionScore,
            'ingredient' => $ingredientScore, // Added this
            'additive' => $additiveScore,
            'processing' => $processingScore,
            'explanation' => $explanation,
        ];
    }

    /**
     * Calculate ingredient quality score
     */
    protected function calculateIngredientScore(Product $product): float
    {
        $ingredients = $product->ingredients;

        if ($ingredients->isEmpty()) {
            return 50; // Default score if no ingredients data
        }

        $totalScore = 0;
        $count = 0;

        foreach ($ingredients as $ingredient) {
            $score = match($ingredient->risk_level) {
                'low' => 100,
                'medium' => 60,
                'high' => 20,
                default => 50,
            };
            $totalScore += $score;
            $count++;
        }

        return $count > 0 ? round($totalScore / $count) : 50;
    }

    /**
     * Calculate cosmetic product scores
     */
    public function calculateCosmeticScores(Product $product): array
    {
        $irritantScore = $this->calculateIrritantScore($product);
        $endocrineScore = $this->calculateEndocrineScore($product);

        $overallScore = round(($irritantScore + $endocrineScore) / 2);

        $explanation = $this->generateCosmeticExplanation(
            $irritantScore,
            $endocrineScore
        );

        return [
            'overall' => $overallScore,
            'irritant' => $irritantScore,
            'endocrine' => $endocrineScore,
            'explanation' => $explanation,
        ];
    }

    /**
     * Calculate nutrition score based on nutritional values
     */
    protected function calculateNutritionScore(Product $product): float
    {
        if (!$product->nutrition) {
            return 50; // Default score if no nutrition data
        }

        $nutrition = $product->nutrition;
        $score = 100;

        // Deduct for unhealthy components
        if ($nutrition->sugars > 10) {
            $score -= ($nutrition->sugars - 10) * 2;
        }

        if ($nutrition->fat > 15) {
            $score -= ($nutrition->fat - 15) * 1.5;
        }

        if ($nutrition->sodium > 400) {
            $score -= ($nutrition->sodium - 400) / 100;
        }

        if ($nutrition->salt > 1.5) {
            $score -= ($nutrition->salt - 1.5) * 5;
        }

        // Add for healthy components
        if ($nutrition->fiber > 3) {
            $score += $nutrition->fiber * 2;
        }

        if ($nutrition->protein > 10) {
            $score += ($nutrition->protein - 10) * 1.5;
        }

        return max(0, min(100, round($score)));
    }

    /**
     * Calculate additive score based on ingredients
     */
    protected function calculateAdditiveScore(Product $product): float
    {
        $ingredients = $product->ingredients;

        if ($ingredients->isEmpty()) {
            return 50;
        }

        $totalScore = 0;
        $riskCounts = [
            'high' => 0,
            'medium' => 0,
            'low' => 0,
        ];

        foreach ($ingredients as $ingredient) {
            $riskCounts[$ingredient->risk_level ?? 'low']++;
        }

        $totalIngredients = $ingredients->count();

        // Calculate weighted score
        $totalScore = (
                ($riskCounts['low'] * 100) +
                ($riskCounts['medium'] * 60) +
                ($riskCounts['high'] * 20)
            ) / $totalIngredients;

        return round($totalScore);
    }

    /**
     * Calculate processing score (NOVA classification)
     */
    protected function calculateProcessingScore(Product $product): float
    {
        $rawData = $product->raw_data;

        // Check if NOVA group is available from OpenFoodFacts
        if (isset($rawData['nova_group'])) {
            $novaMap = [
                1 => 100, // Unprocessed/minimally processed
                2 => 75,  // Processed culinary ingredients
                3 => 50,  // Processed foods
                4 => 25,  // Ultra-processed foods
            ];

            return $novaMap[$rawData['nova_group']] ?? 50;
        }

        // Calculate based on ingredients list length as fallback
        $ingredientCount = $product->ingredients->count();

        if ($ingredientCount <= 5) {
            return 80;
        } elseif ($ingredientCount <= 10) {
            return 60;
        } elseif ($ingredientCount <= 15) {
            return 40;
        } else {
            return 20;
        }
    }

    /**
     * Calculate irritant score for cosmetics
     */
    protected function calculateIrritantScore(Product $product): float
    {
        $irritants = ['alcohol', 'fragrance', 'parfum', 'essential oil'];
        $ingredients = $product->ingredients->pluck('name')->map('strtolower');

        $irritantCount = 0;
        foreach ($irritants as $irritant) {
            if ($ingredients->contains(function ($ingredient) use ($irritant) {
                return str_contains($ingredient, $irritant);
            })) {
                $irritantCount++;
            }
        }

        $score = 100 - ($irritantCount * 20);
        return max(0, $score);
    }

    /**
     * Calculate endocrine disruptor score for cosmetics
     */
    protected function calculateEndocrineScore(Product $product): float
    {
        $endocrineDisruptors = ['paraben', 'phthalate', 'bpa', 'triclosan'];
        $ingredients = $product->ingredients->pluck('name')->map('strtolower');

        $disruptorCount = 0;
        foreach ($endocrineDisruptors as $disruptor) {
            if ($ingredients->contains(function ($ingredient) use ($disruptor) {
                return str_contains($ingredient, $disruptor);
            })) {
                $disruptorCount++;
            }
        }

        $score = 100 - ($disruptorCount * 25);
        return max(0, $score);
    }

    /**
     * Generate explanation text for food products
     */
    protected function generateFoodExplanation(float $nutrition, float $additive, float $processing): string
    {
        $explanations = [];

        if ($nutrition >= 80) {
            $explanations[] = "Excellent nutritional profile";
        } elseif ($nutrition >= 60) {
            $explanations[] = "Good nutritional value";
        } elseif ($nutrition >= 40) {
            $explanations[] = "Moderate nutritional content";
        } else {
            $explanations[] = "Poor nutritional quality";
        }

        if ($additive >= 80) {
            $explanations[] = "minimal additives";
        } elseif ($additive >= 60) {
            $explanations[] = "some additives present";
        } else {
            $explanations[] = "contains concerning additives";
        }

        if ($processing >= 80) {
            $explanations[] = "minimally processed.";
        } elseif ($processing >= 60) {
            $explanations[] = "moderately processed.";
        } else {
            $explanations[] = "highly processed.";
        }

        return ucfirst(implode(', ', $explanations));
    }

    /**
     * Generate explanation text for cosmetic products
     */
    protected function generateCosmeticExplanation(float $irritant, float $endocrine): string
    {
        $average = ($irritant + $endocrine) / 2;

        if ($average >= 80) {
            return "This product has an excellent safety profile with minimal concerning ingredients.";
        } elseif ($average >= 60) {
            return "This product has a good safety profile with some minor concerns.";
        } elseif ($average >= 40) {
            return "This product contains some ingredients that may cause concern for sensitive individuals.";
        } else {
            return "This product contains multiple ingredients of concern. Consider alternatives with cleaner formulations.";
        }
    }
}
