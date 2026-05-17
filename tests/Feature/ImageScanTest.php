<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ImageScanTest extends TestCase
{
    use RefreshDatabase;

    public function test_image_scan_can_resolve_using_barcode_hint(): void
    {
        Sanctum::actingAs(User::factory()->create());
        Storage::fake('public');

        $barcode = '12345678';

        Http::fake($this->fakeBarcodeResponses($barcode, [
            'status' => 1,
            'product' => [
                'code' => $barcode,
                'product_name' => 'Test Granola',
                'brands' => 'Scanwell Foods',
                'categories' => 'Breakfast cereals, Granola',
                'ingredients_text' => 'Oats, Honey, Almonds',
                'nutriments' => [
                    'energy-kcal_100g' => 420,
                    'fat_100g' => 10,
                    'saturated-fat_100g' => 1,
                    'carbohydrates_100g' => 65,
                    'fiber_100g' => 8,
                    'sugars_100g' => 14,
                    'proteins_100g' => 9,
                    'sodium_100g' => 0.1,
                ],
                'image_url' => 'https://example.com/granola.jpg',
            ],
        ]));

        $response = $this->post('/api/v1/scan/image', [
            'image' => UploadedFile::fake()->image('granola.jpg'),
            'barcode_hint' => $barcode,
        ], [
            'Accept' => 'application/json',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.product.barcode', $barcode)
            ->assertJsonPath('data.scan.scan_metadata.scan_mode', 'image')
            ->assertJsonPath('data.scan.scan_metadata.matched_by', 'image_barcode_hint');

        $storedPath = $response->json('data.scan.scan_metadata.image_scan.stored_image.path');

        $this->assertNotNull($storedPath);
        Storage::disk('public')->assertExists($storedPath);
    }

    public function test_image_scan_can_resolve_using_openai_vision_extracted_barcode(): void
    {
        Sanctum::actingAs(User::factory()->create());
        Storage::fake('public');

        config()->set('services.openai.api_key', 'test-openai-key');

        $barcode = '23456789';

        Http::fake(array_merge(
            [
                'https://api.openai.com/v1/responses' => Http::response([
                    'output' => [[
                        'content' => [[
                            'text' => json_encode([
                                'barcode' => $barcode,
                                'product_name' => 'Vision Chips',
                                'brand' => 'Scanwell Snacks',
                                'category_hint' => 'food',
                                'extracted_text' => 'Vision Chips Scanwell Snacks ' . $barcode,
                                'confidence' => 91,
                                'front_label_visible' => true,
                                'barcode_visible' => true,
                                'nutrition_panel_visible' => false,
                                'ingredients_visible' => false,
                            ]),
                        ]],
                    ]],
                ]),
            ],
            $this->fakeBarcodeResponses($barcode, [
                'status' => 1,
                'product' => [
                    'code' => $barcode,
                    'product_name' => 'Vision Chips',
                    'brands' => 'Scanwell Snacks',
                    'categories' => 'Snacks, Potato chips',
                    'ingredients_text' => 'Potatoes, Oil, Salt',
                    'nutriments' => [
                        'energy-kcal_100g' => 510,
                        'fat_100g' => 31,
                        'saturated-fat_100g' => 3,
                        'carbohydrates_100g' => 53,
                        'fiber_100g' => 4,
                        'sugars_100g' => 1,
                        'proteins_100g' => 6,
                        'sodium_100g' => 0.6,
                    ],
                    'image_url' => 'https://example.com/chips.jpg',
                ],
            ])
        ));

        $response = $this->post('/api/v1/scan/image', [
            'image' => UploadedFile::fake()->image('chips.jpg'),
        ], [
            'Accept' => 'application/json',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.product.barcode', $barcode)
            ->assertJsonPath('data.scan.scan_metadata.matched_by', 'image_barcode')
            ->assertJsonPath('data.scan.scan_metadata.analysis_source', 'openai_vision');
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
