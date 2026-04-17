<?php

namespace Tests\Unit;

use App\Models\FoodNutrition;
use App\Models\Ingredient;
use App\Models\Product;
use App\Services\ScoreCalculationService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Tests\TestCase;

class ScoreCalculationServiceTest extends TestCase
{
    public function test_it_caps_plastic_bottled_water_below_perfect_score(): void
    {
        $product = new Product([
            'name' => 'Wisconsin Spring Water',
            'brand' => 'Wisconsin Water',
            'category_id' => 10,
            'raw_data' => [
                'categories' => 'Waters, Spring waters',
                '_scanwell' => [
                    'product_family' => 'food',
                    'confidence' => 88,
                    'completeness' => 90,
                    'packaging' => [
                        'materials' => ['plastic'],
                        'is_plastic' => true,
                    ],
                ],
            ],
        ]);

        $product->setRelation('ingredients', new EloquentCollection([
            new Ingredient([
                'name' => 'Water',
                'risk_level' => 'low',
            ]),
        ]));

        $product->setRelation('nutrition', new FoodNutrition([
            'sugars' => 0,
            'fat' => 0,
            'saturated_fat' => 0,
            'protein' => 0,
            'fiber' => 0,
            'sodium' => 0,
        ]));

        $scores = app(ScoreCalculationService::class)->calculateFoodScores($product);

        $this->assertLessThan(95, $scores['overall']);
        $this->assertLessThanOrEqual(config('scanning.water_plastic_score_cap', 89), $scores['overall']);
        $this->assertContains(
            'Plastic bottled water is capped below a perfect score because packaging impact matters.',
            $scores['warnings']
        );
    }

    public function test_it_returns_conservative_cosmetic_scores_when_ingredients_are_missing(): void
    {
        $product = new Product([
            'name' => 'Gentle Face Cleanser',
            'category_id' => 110,
            'raw_data' => [
                '_scanwell' => [
                    'product_family' => 'cosmetic',
                    'confidence' => 60,
                    'completeness' => 35,
                ],
            ],
        ]);

        $product->setRelation('ingredients', new EloquentCollection());

        $scores = app(ScoreCalculationService::class)->calculateCosmeticScores($product);

        $this->assertSame(config('scanning.sparse_cosmetic_score_cap', 55), $scores['overall']);
        $this->assertContains(
            'Ingredient list is missing, so the cosmetic score is capped conservatively.',
            $scores['warnings']
        );
    }
}
