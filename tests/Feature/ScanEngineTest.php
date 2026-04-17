<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ScanEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_plastic_bottled_water_is_not_given_a_perfect_score(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $barcode = '12345678';

        Http::fake($this->fakeBarcodeResponses($barcode, [
            'status' => 1,
            'product' => [
                'code' => $barcode,
                'product_name' => 'Wisconsin Spring Water',
                'brands' => 'Wisconsin Water',
                'categories' => 'Waters, Spring waters',
                'categories_tags' => ['en:waters', 'en:spring-waters'],
                'packaging' => 'Plastic bottle',
                'packaging_tags' => ['en:plastic-bottle'],
                'ingredients' => [
                    ['text' => 'Water', 'percent' => 100],
                ],
                'nutriments' => [
                    'energy-kcal_100g' => 0,
                    'fat_100g' => 0,
                    'saturated-fat_100g' => 0,
                    'sugars_100g' => 0,
                    'proteins_100g' => 0,
                    'sodium_100g' => 0,
                ],
                'image_url' => 'https://example.com/water.jpg',
            ],
        ]));

        $response = $this->postJson('/api/v1/scan', [
            'barcode' => $barcode,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true);

        $score = (float) $response->json('data.product.score');

        $this->assertLessThan(95, $score);
        $this->assertLessThanOrEqual(config('scanning.water_plastic_score_cap', 89), $score);
        $this->assertContains(
            'Plastic bottled water is capped below a perfect score because packaging impact matters.',
            $response->json('data.product.scores.food.warnings', [])
        );
    }

    public function test_beauty_lookup_creates_a_cosmetic_score_and_not_a_food_score(): void
    {
        $barcode = '87654321';

        Http::fake(array_merge(
            $this->fakeMisses($barcode),
            [
                "https://world.openbeautyfacts.org/api/v2/product/{$barcode}.json" => Http::response([
                    'status' => 1,
                    'product' => [
                        'code' => $barcode,
                        'product_name' => 'Gentle Face Cleanser',
                        'brands' => 'Scanwell Beauty',
                        'categories' => 'Face care, Cleansers',
                        'ingredients_text' => 'Water, Glycerin, Fragrance',
                        'packaging' => 'Tube',
                        'image_url' => 'https://example.com/cleanser.jpg',
                    ],
                ]),
            ]
        ));

        $response = $this->getJson("/api/v1/products/barcode/{$barcode}");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.source', 'open_beauty_facts');

        $product = Product::where('barcode', $barcode)->firstOrFail();

        $this->assertDatabaseHas('cosmetic_scores', [
            'product_id' => $product->id,
        ]);

        $this->assertDatabaseMissing('food_scores', [
            'product_id' => $product->id,
        ]);
    }

    public function test_general_products_return_unknown_interpretation_instead_of_fake_high_scores(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $barcode = '11223344';

        Http::fake(array_merge(
            $this->fakeMisses($barcode),
            [
                "https://world.openproductfacts.org/api/v2/product/{$barcode}.json" => Http::response([
                    'status' => 1,
                    'product' => [
                        'code' => $barcode,
                        'product_name' => 'Multi Surface Cleaner',
                        'brands' => 'Home Bright',
                        'categories' => 'Household cleaners',
                        'packaging' => 'Plastic trigger bottle',
                        'image_url' => 'https://example.com/cleaner.jpg',
                    ],
                ]),
            ]
        ));

        $response = $this->postJson('/api/v1/scan', [
            'barcode' => $barcode,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.product.score', null)
            ->assertJsonPath('data.score_interpretation.grade', 'Unknown')
            ->assertJsonPath('data.score_interpretation.color', 'gray');
    }

    protected function fakeBarcodeResponses(string $barcode, array $foodResponse): array
    {
        return array_merge($this->fakeMisses($barcode), [
            "https://world.openfoodfacts.org/api/v2/product/{$barcode}.json" => Http::response($foodResponse),
        ]);
    }

    protected function fakeMisses(string $barcode): array
    {
        return [
            "https://world.openfoodfacts.org/api/v2/product/{$barcode}.json" => Http::response(['status' => 0], 404),
            "https://world.openbeautyfacts.org/api/v2/product/{$barcode}.json" => Http::response(['status' => 0], 404),
            "https://world.openproductfacts.org/api/v2/product/{$barcode}.json" => Http::response(['status' => 0], 404),
            "https://world.openpetfoodfacts.org/api/v2/product/{$barcode}.json" => Http::response(['status' => 0], 404),
        ];
    }
}
