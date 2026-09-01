<?php

namespace Tests\Feature;

use App\Models\ProductImage;
use App\Models\Product;
use App\Services\ProductImageSimilarityService;
use App\Services\ProductWorkflowService;
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

    public function test_image_scan_can_resolve_using_google_cloud_vision_extracted_barcode(): void
    {
        Sanctum::actingAs(User::factory()->create());
        Storage::fake('public');

        config()->set('services.openai.api_key', null);
        config()->set('services.google_cloud_vision.credentials_json', $this->fakeGoogleCredentialsJson());

        $barcode = '23456789';

        Http::fake(array_merge(
            [
                'https://oauth2.googleapis.com/token' => Http::response([
                    'access_token' => 'google-access-token',
                    'expires_in' => 3600,
                    'token_type' => 'Bearer',
                ]),
                'https://vision.googleapis.com/v1/images:annotate' => Http::response([
                    'responses' => [[
                        'fullTextAnnotation' => [
                            'text' => "Scanwell Snacks\nVision Chips\n{$barcode}",
                            'pages' => [[
                                'blocks' => [
                                    ['confidence' => 0.91],
                                    ['confidence' => 0.89],
                                ],
                            ]],
                        ],
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
            ->assertJsonPath('data.scan.scan_metadata.analysis_source', 'google_cloud_vision')
            ->assertJsonPath('data.scan.scan_metadata.image_scan.ocr.provider', 'google_cloud_vision')
            ->assertJsonPath('data.scan.scan_metadata.image_scan.ocr.mode', 'DOCUMENT_TEXT_DETECTION');
    }

    public function test_image_scan_can_resolve_using_normalized_brand_and_product_name_match(): void
    {
        Sanctum::actingAs(User::factory()->create());
        Storage::fake('public');

        config()->set('services.openai.api_key', null);
        config()->set('services.google_cloud_vision.credentials_json', $this->fakeGoogleCredentialsJson());

        $product = Product::create([
            'barcode' => '55667788',
            'name' => 'Purified Water',
            'brand' => 'Kirkland',
            'category_id' => 1,
            'source' => 'manual',
            'raw_data' => [],
        ]);

        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'google-access-token',
                'expires_in' => 3600,
                'token_type' => 'Bearer',
            ]),
            'https://vision.googleapis.com/v1/images:annotate' => Http::response([
                'responses' => [[
                    'fullTextAnnotation' => [
                        'text' => "Kirkland\nPurified Water",
                        'pages' => [[
                            'blocks' => [
                                ['confidence' => 0.93],
                            ],
                        ]],
                    ],
                ]],
            ]),
        ]);

        $response = $this->post('/api/v1/scan/image', [
            'image' => UploadedFile::fake()->image('water.jpg'),
        ], [
            'Accept' => 'application/json',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.product.barcode', $product->barcode)
            ->assertJsonPath('data.scan.scan_metadata.matched_by', 'image_brand_product_match')
            ->assertJsonPath('data.scan.scan_metadata.analysis_source', 'google_cloud_vision');
    }

    public function test_image_scan_can_resolve_using_catalog_alias_text_match(): void
    {
        Sanctum::actingAs(User::factory()->create());
        Storage::fake('public');

        config()->set('services.openai.api_key', null);
        config()->set('services.google_cloud_vision.credentials_json', $this->fakeGoogleCredentialsJson());

        $product = Product::create([
            'barcode' => '99887766',
            'name' => 'Sparkling Water',
            'brand' => 'Bubly',
            'category_id' => 1,
            'source' => 'manual',
            'raw_data' => [
                'aliases' => ['Blackberry Bubly', 'Bubly Blackberry Sparkling Water'],
            ],
            'manual_overrides' => [],
        ]);

        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'google-access-token',
                'expires_in' => 3600,
                'token_type' => 'Bearer',
            ]),
            'https://vision.googleapis.com/v1/images:annotate' => Http::response([
                'responses' => [[
                    'fullTextAnnotation' => [
                        'text' => "Blackberry Bubly\nSparkling Water",
                        'pages' => [[
                            'blocks' => [
                                ['confidence' => 0.88],
                            ],
                        ]],
                    ],
                ]],
            ]),
        ]);

        $response = $this->post('/api/v1/scan/image', [
            'image' => UploadedFile::fake()->image('bubly.jpg'),
        ], [
            'Accept' => 'application/json',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.product.barcode', $product->barcode)
            ->assertJsonPath('data.scan.scan_metadata.matched_by', 'image_alias_text_match')
            ->assertJsonPath('data.scan.scan_metadata.image_scan.signals.ocr_provider', 'google_cloud_vision');
    }

    public function test_image_scan_can_resolve_using_openai_identity_extraction(): void
    {
        Sanctum::actingAs(User::factory()->create());
        Storage::fake('public');

        config()->set('services.openai.api_key', 'test-openai-key');

        $product = Product::create([
            'barcode' => '44556677',
            'name' => 'Purified Water',
            'brand' => 'Kirkland',
            'category_id' => 1,
            'source' => 'manual',
            'raw_data' => [],
        ]);

        Http::fake([
            'https://api.openai.com/v1/responses' => Http::response([
                'output' => [[
                    'content' => [[
                        'text' => json_encode([
                            'barcode_hint' => null,
                            'product_name' => 'Purified Water',
                            'brand' => 'Kirkland',
                            'extracted_text' => 'Kirkland Purified Water',
                            'confidence' => 96,
                        ], JSON_THROW_ON_ERROR),
                    ]],
                ]],
            ]),
        ]);

        $response = $this->post('/api/v1/scan/image', [
            'image' => UploadedFile::fake()->image('openai-water.jpg'),
        ], [
            'Accept' => 'application/json',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.product.barcode', $product->barcode)
            ->assertJsonPath('data.scan.scan_metadata.matched_by', 'image_brand_product_match')
            ->assertJsonPath('data.scan.scan_metadata.analysis_source', 'openai_vision')
            ->assertJsonPath('data.scan.scan_metadata.image_scan.ocr.provider', 'openai_vision')
            ->assertJsonPath('data.scan.scan_metadata.image_scan.ocr.mode', 'structured_product_identity_json');
    }

    public function test_image_scan_prefers_google_vision_identity_when_openai_guess_disagrees_at_lower_confidence(): void
    {
        Sanctum::actingAs(User::factory()->create());
        Storage::fake('public');

        config()->set('services.openai.api_key', 'test-openai-key');
        config()->set('services.google_cloud_vision.credentials_json', $this->fakeGoogleCredentialsJson());

        $product = Product::create([
            'barcode' => '12121212',
            'name' => 'Hand Sanitizer Gel',
            'brand' => 'Clorox',
            'category_id' => 1,
            'source' => 'manual',
            'raw_data' => [],
        ]);

        Http::fake([
            'https://api.openai.com/v1/responses' => Http::response([
                'output' => [[
                    'content' => [[
                        'text' => json_encode([
                            'barcode_hint' => null,
                            'product_name' => 'Sanitizer Gel',
                            'brand' => 'Purell',
                            'extracted_text' => 'Purell Sanitizer Gel',
                            'confidence' => 81,
                        ], JSON_THROW_ON_ERROR),
                    ]],
                ]],
            ]),
            'https://oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'google-access-token',
                'expires_in' => 3600,
                'token_type' => 'Bearer',
            ]),
            'https://vision.googleapis.com/v1/images:annotate' => Http::response([
                'responses' => [[
                    'fullTextAnnotation' => [
                        'text' => "Clorox\nHand Sanitizer Gel",
                        'pages' => [[
                            'blocks' => [
                                ['confidence' => 0.92],
                            ],
                        ]],
                    ],
                ]],
            ]),
        ]);

        $response = $this->post('/api/v1/scan/image', [
            'image' => UploadedFile::fake()->image('sanitizer.jpg'),
        ], [
            'Accept' => 'application/json',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.product.barcode', $product->barcode)
            ->assertJsonPath('data.scan.scan_metadata.matched_by', 'image_brand_product_match')
            ->assertJsonPath('data.scan.scan_metadata.analysis_source', 'hybrid_vision')
            ->assertJsonPath('data.scan.scan_metadata.image_scan.signals.brand', 'Clorox')
            ->assertJsonPath('data.scan.scan_metadata.image_scan.signals.product_name', 'Hand Sanitizer Gel');
    }

    public function test_image_scan_can_use_visual_similarity_to_break_variant_ties(): void
    {
        Sanctum::actingAs(User::factory()->create());
        Storage::fake('public');

        config()->set('services.openai.api_key', null);
        config()->set('services.google_cloud_vision.credentials_json', $this->fakeGoogleCredentialsJson());

        $target = Product::create([
            'barcode' => '77770001',
            'name' => 'Sparkling Water Lime',
            'brand' => 'Bubly',
            'category_id' => 1,
            'source' => 'manual',
            'raw_data' => [],
        ]);

        $sibling = Product::create([
            'barcode' => '77770002',
            'name' => 'Sparkling Water Blackberry',
            'brand' => 'Bubly',
            'category_id' => 1,
            'source' => 'manual',
            'raw_data' => [],
        ]);

        ProductImage::create([
            'product_id' => $target->id,
            'url' => 'https://example.com/lime.jpg',
            'source' => 'manual',
            'is_primary' => true,
            'sort_order' => 0,
        ]);

        ProductImage::create([
            'product_id' => $sibling->id,
            'url' => 'https://example.com/blackberry.jpg',
            'source' => 'manual',
            'is_primary' => true,
            'sort_order' => 0,
        ]);

        $this->mock(ProductImageSimilarityService::class, function ($mock) use ($target, $sibling) {
            $mock->shouldReceive('scoreProductsAgainstImage')
                ->once()
                ->andReturn([
                    $target->id => [
                        'similarity' => 0.96,
                        'image_id' => 'img-lime',
                        'image_url' => 'https://example.com/lime.jpg',
                    ],
                    $sibling->id => [
                        'similarity' => 0.71,
                        'image_id' => 'img-blackberry',
                        'image_url' => 'https://example.com/blackberry.jpg',
                    ],
                ]);
        });

        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'google-access-token',
                'expires_in' => 3600,
                'token_type' => 'Bearer',
            ]),
            'https://vision.googleapis.com/v1/images:annotate' => Http::response([
                'responses' => [[
                    'fullTextAnnotation' => [
                        'text' => "Bubly\nSparkling Water",
                        'pages' => [[
                            'blocks' => [
                                ['confidence' => 0.9],
                            ],
                        ]],
                    ],
                ]],
            ]),
            'https://world.openfoodfacts.org/api/v2/product/77770001.json' => Http::response([
                'status' => 1,
                'product' => [
                    'code' => '77770001',
                    'product_name' => 'Sparkling Water Lime',
                    'brands' => 'Bubly',
                    'categories' => 'Water, Sparkling water',
                    'ingredients_text' => 'Carbonated Water, Natural Flavor',
                    'nutriments' => [
                        'energy-kcal_100g' => 0,
                    ],
                    'image_url' => 'https://example.com/lime.jpg',
                ],
            ]),
            'https://world.openbeautyfacts.org/api/v2/product/77770001.json' => Http::response(['status' => 0], 404),
            'https://world.openproductfacts.org/api/v2/product/77770001.json' => Http::response(['status' => 0], 404),
            'https://world.openpetfoodfacts.org/api/v2/product/77770001.json' => Http::response(['status' => 0], 404),
        ]);

        $response = $this->post('/api/v1/scan/image', [
            'image' => UploadedFile::fake()->image('bubly-variant.jpg'),
        ], [
            'Accept' => 'application/json',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.product.barcode', '77770001')
            ->assertJsonPath('data.scan.scan_metadata.matched_by', 'image_visual_text_match');
    }

    public function test_image_scan_prefers_openai_identity_when_google_ocr_is_noisy_promotional_text(): void
    {
        Sanctum::actingAs(User::factory()->create());
        Storage::fake('public');

        config()->set('services.openai.api_key', 'test-openai-key');
        config()->set('services.google_cloud_vision.credentials_json', $this->fakeGoogleCredentialsJson());

        $product = Product::create([
            'barcode' => '7613034920348',
            'name' => 'Aero chocolate',
            'brand' => 'Nestle',
            'category_id' => 1,
            'source' => 'manual',
            'raw_data' => [],
        ]);

        Http::fake([
            'https://api.openai.com/v1/responses' => Http::response([
                'output' => [[
                    'content' => [[
                        'text' => json_encode([
                            'barcode_hint' => null,
                            'product_name' => 'Aero chocolate',
                            'brand' => 'Nestle',
                            'extracted_text' => 'Nestle Aero chocolate',
                            'confidence' => 96,
                        ], JSON_THROW_ON_ERROR),
                    ]],
                ]],
            ]),
            'https://oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'google-access-token',
                'expires_in' => 3600,
                'token_type' => 'Bearer',
            ]),
            'https://vision.googleapis.com/v1/images:annotate' => Http::response([
                'responses' => [[
                    'fullTextAnnotation' => [
                        'text' => "4x Nestle Aero share-a-bubble chocolate feel the bubbles melt Chorva WIN devilishly chic trip NEW YORK THE DEVIL PRADA WEARS ONLY IN CINEMAS",
                        'pages' => [[
                            'blocks' => [
                                ['confidence' => 0.94],
                            ],
                        ]],
                    ],
                ]],
            ]),
            'https://world.openfoodfacts.org/api/v2/product/7613034920348.json' => Http::response([
                'status' => 1,
                'product' => [
                    'code' => '7613034920348',
                    'product_name' => 'Aero chocolate',
                    'brands' => 'Nestle',
                    'categories' => 'Chocolate candies, Bars, Chocolates',
                    'ingredients_text' => 'Sugar, Cocoa mass',
                    'nutriments' => [
                        'energy-kcal_100g' => 529.63,
                    ],
                    'image_url' => 'https://example.com/aero.jpg',
                ],
            ]),
            'https://world.openbeautyfacts.org/api/v2/product/7613034920348.json' => Http::response(['status' => 0], 404),
            'https://world.openproductfacts.org/api/v2/product/7613034920348.json' => Http::response(['status' => 0], 404),
            'https://world.openpetfoodfacts.org/api/v2/product/7613034920348.json' => Http::response(['status' => 0], 404),
        ]);

        $response = $this->post('/api/v1/scan/image', [
            'image' => UploadedFile::fake()->image('aero.jpg'),
        ], [
            'Accept' => 'application/json',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.product.barcode', $product->barcode)
            ->assertJsonPath('data.scan.scan_metadata.matched_by', 'image_brand_product_match')
            ->assertJsonPath('data.scan.scan_metadata.analysis_source', 'hybrid_vision')
            ->assertJsonPath('data.scan.scan_metadata.image_scan.signals.brand', 'Nestle')
            ->assertJsonPath('data.scan.scan_metadata.image_scan.signals.product_name', 'Aero chocolate')
            ->assertJsonPath('data.scan.scan_metadata.image_scan.signals.extracted_text', 'Nestle Aero chocolate');
    }

    public function test_image_scan_can_resolve_using_visual_match_when_text_identity_is_noisy(): void
    {
        Sanctum::actingAs(User::factory()->create());
        Storage::fake('public');

        config()->set('services.openai.api_key', null);
        config()->set('services.google_cloud_vision.credentials_json', $this->fakeGoogleCredentialsJson());

        $target = Product::create([
            'barcode' => '7613034920348',
            'name' => 'Aero chocolate',
            'brand' => 'Nestle',
            'category_id' => 1,
            'source' => 'manual',
            'raw_data' => [],
        ]);

        ProductImage::create([
            'product_id' => $target->id,
            'url' => 'https://example.com/aero.jpg',
            'source' => 'manual',
            'is_primary' => true,
            'sort_order' => 0,
        ]);

        $other = Product::create([
            'barcode' => '7613034920999',
            'name' => 'Milk chocolate',
            'brand' => 'Other Brand',
            'category_id' => 1,
            'source' => 'manual',
            'raw_data' => [],
        ]);

        ProductImage::create([
            'product_id' => $other->id,
            'url' => 'https://example.com/other.jpg',
            'source' => 'manual',
            'is_primary' => true,
            'sort_order' => 0,
        ]);

        $this->mock(ProductImageSimilarityService::class, function ($mock) use ($target, $other) {
            $mock->shouldReceive('scoreProductsAgainstImage')
                ->once()
                ->andReturn([
                    $target->id => [
                        'similarity' => 0.975,
                        'image_id' => 'img-aero',
                        'image_url' => 'https://example.com/aero.jpg',
                    ],
                    $other->id => [
                        'similarity' => 0.73,
                        'image_id' => 'img-other',
                        'image_url' => 'https://example.com/other.jpg',
                    ],
                ]);
        });

        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'google-access-token',
                'expires_in' => 3600,
                'token_type' => 'Bearer',
            ]),
            'https://vision.googleapis.com/v1/images:annotate' => Http::response([
                'responses' => [[
                    'fullTextAnnotation' => [
                        'text' => "4x Nestle Aero share-a-bubble chocolate feel the bubbles melt Chorva WIN devilishly chic trip NEW YORK THE DEVIL PRADA WEARS ONLY IN CINEMAS",
                        'pages' => [[
                            'blocks' => [
                                ['confidence' => 0.94],
                            ],
                        ]],
                    ],
                ]],
            ]),
            'https://world.openfoodfacts.org/api/v2/product/7613034920348.json' => Http::response([
                'status' => 1,
                'product' => [
                    'code' => '7613034920348',
                    'product_name' => 'Aero chocolate',
                    'brands' => 'Nestle',
                    'categories' => 'Chocolate candies, Bars, Chocolates',
                    'ingredients_text' => 'Sugar, Cocoa mass',
                    'nutriments' => [
                        'energy-kcal_100g' => 529.63,
                    ],
                    'image_url' => 'https://example.com/aero.jpg',
                ],
            ]),
            'https://world.openbeautyfacts.org/api/v2/product/7613034920348.json' => Http::response(['status' => 0], 404),
            'https://world.openproductfacts.org/api/v2/product/7613034920348.json' => Http::response(['status' => 0], 404),
            'https://world.openpetfoodfacts.org/api/v2/product/7613034920348.json' => Http::response(['status' => 0], 404),
        ]);

        $response = $this->post('/api/v1/scan/image', [
            'image' => UploadedFile::fake()->image('aero-visual.jpg'),
        ], [
            'Accept' => 'application/json',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.product.barcode', $target->barcode)
            ->assertJsonPath('data.scan.scan_metadata.matched_by', 'image_visual_match');
    }

    public function test_image_scan_keeps_strong_visual_winner_when_confidence_ties_between_candidates(): void
    {
        Sanctum::actingAs(User::factory()->create());
        Storage::fake('public');

        config()->set('services.openai.api_key', null);
        config()->set('services.google_cloud_vision.credentials_json', $this->fakeGoogleCredentialsJson());

        $target = Product::create([
            'barcode' => '7613034920348',
            'name' => 'Aero chocolate',
            'brand' => 'Nestle',
            'category_id' => 1,
            'source' => 'manual',
            'raw_data' => [],
        ]);

        ProductImage::create([
            'product_id' => $target->id,
            'url' => 'https://example.com/aero.jpg',
            'source' => 'manual',
            'is_primary' => true,
            'sort_order' => 0,
        ]);

        $fallback = Product::create([
            'barcode' => '6033000680051',
            'name' => 'Classic Coffee',
            'brand' => 'Nescafe',
            'category_id' => 1,
            'source' => 'manual',
            'raw_data' => [],
        ]);

        ProductImage::create([
            'product_id' => $fallback->id,
            'url' => 'https://example.com/nescafe.jpg',
            'source' => 'manual',
            'is_primary' => true,
            'sort_order' => 0,
        ]);

        $closeSibling = Product::create([
            'barcode' => '7613035040823',
            'name' => 'Aero Peppermint',
            'brand' => 'Nestle',
            'category_id' => 1,
            'source' => 'manual',
            'raw_data' => [],
        ]);

        ProductImage::create([
            'product_id' => $closeSibling->id,
            'url' => 'https://example.com/aero-peppermint.jpg',
            'source' => 'manual',
            'is_primary' => true,
            'sort_order' => 0,
        ]);

        $this->mock(ProductImageSimilarityService::class, function ($mock) use ($target, $fallback, $closeSibling) {
            $mock->shouldReceive('scoreProductsAgainstImage')
                ->once()
                ->andReturn([
                    $target->id => [
                        'similarity' => 0.7253,
                        'image_id' => 'img-aero',
                        'image_url' => 'https://example.com/aero.jpg',
                    ],
                    $fallback->id => [
                        'similarity' => 0.6784,
                        'image_id' => 'img-nescafe',
                        'image_url' => 'https://example.com/nescafe.jpg',
                    ],
                    $closeSibling->id => [
                        'similarity' => 0.6901,
                        'image_id' => 'img-aero-peppermint',
                        'image_url' => 'https://example.com/aero-peppermint.jpg',
                    ],
                ]);
        });

        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'google-access-token',
                'expires_in' => 3600,
                'token_type' => 'Bearer',
            ]),
            'https://vision.googleapis.com/v1/images:annotate' => Http::response([
                'responses' => [[
                    'fullTextAnnotation' => [
                        'text' => "4x Nestle Aero chocolate 2025 206 CENTURY STIDOS UKC open late drawsack 1serving 143 calories per energy bar",
                        'pages' => [[
                            'blocks' => [
                                ['confidence' => 0.94],
                            ],
                        ]],
                    ],
                ]],
            ]),
            'https://world.openfoodfacts.org/api/v2/product/7613034920348.json' => Http::response([
                'status' => 1,
                'product' => [
                    'code' => '7613034920348',
                    'product_name' => 'Aero chocolate',
                    'brands' => 'Nestle',
                    'categories' => 'Chocolate candies, Bars, Chocolates',
                    'ingredients_text' => 'Sugar, Cocoa mass',
                    'nutriments' => [
                        'energy-kcal_100g' => 529.63,
                    ],
                    'image_url' => 'https://example.com/aero.jpg',
                ],
            ]),
            'https://world.openbeautyfacts.org/api/v2/product/7613034920348.json' => Http::response(['status' => 0], 404),
            'https://world.openproductfacts.org/api/v2/product/7613034920348.json' => Http::response(['status' => 0], 404),
            'https://world.openpetfoodfacts.org/api/v2/product/7613034920348.json' => Http::response(['status' => 0], 404),
        ]);

        $response = $this->post('/api/v1/scan/image', [
            'image' => UploadedFile::fake()->image('aero-visual-tie.jpg'),
        ], [
            'Accept' => 'application/json',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.product.barcode', $target->barcode)
            ->assertJsonPath('data.scan.scan_metadata.matched_by', 'image_visual_text_match');
    }

    public function test_remote_catalog_images_are_localized_when_product_is_created(): void
    {
        Storage::fake('public');

        Http::fake([
            'https://example.com/catalog-image.jpg' => Http::response('fake-image-bytes', 200, [
                'Content-Type' => 'image/jpeg',
            ]),
        ]);

        /** @var ProductWorkflowService $service */
        $service = app(ProductWorkflowService::class);

        $product = $service->createProduct([
            'barcode' => '1231231231234',
            'name' => 'Localized Image Product',
            'brand' => 'Scanwell',
            'category_id' => 1,
            'product_family' => 'food',
            'image_url' => 'https://example.com/catalog-image.jpg',
            'images' => [[
                'url' => 'https://example.com/catalog-image.jpg',
                'source' => 'open_food_facts',
                'is_primary' => true,
                'sort_order' => 0,
            ]],
            'ingredients' => [],
            'nutrition' => [],
            'raw_data' => [],
        ], [
            'source' => 'open_food_facts',
            'audit' => false,
        ]);

        $image = $product->images()->firstOrFail();

        $this->assertSame('public', $image->disk);
        $this->assertNotNull($image->path);
        Storage::disk('public')->assertExists($image->path);
        $this->assertSame(Storage::disk('public')->url($image->path), $product->fresh()->image_url);
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

    protected function fakeGoogleCredentialsJson(): string
    {
        return json_encode([
            'type' => 'service_account',
            'project_id' => 'scanwell-test',
            'private_key_id' => 'test-private-key-id',
            'private_key' => <<<KEY
-----BEGIN PRIVATE KEY-----
MIIEvQIBADANBgkqhkiG9w0BAQEFAASCBKcwggSjAgEAAoIBAQDPU1n+v2m8dS95
QUH7yhQ3Z4EKmoU+PEOp9l6fdv4eaKQsgv7gkRze/BBIC1LFIF0GSEs9T4IkbV99
R7ZPFQqgHo0rDXhQ8i9k3r1ta7zwzZL62sN49ot9g+6UnTzSjwhZ8i6sTe2hYgeU
NuGyA9/hh3QgSF0L7SWFnqqvXxTz6+0H3qBpQ7ey6whmd4DsuLP0B2+y/MH6bQ0Y
sYvLTLbTXYRxe6fKGE4V1p5LmSO2Hehwd0hp5gXqkN1DI2G0oPLQhC4xX7Go3Xsu
3XxPPWivPjYZEkQjyl2JZP6f8GYQibY6/VX2L4m9f8rQSY5POlWHCvAvH3Q1k3T/
w4jPuscpAgMBAAECggEAFr6TvAJHe0q9fWQThg/8S6AmQ27v8C2I44b12HqfoXly
FjTl/vo7Qr2U3kghQG3ky2Mf1tAnQkWb9qGTiNtthSgxjVKMe7KBHBxKXmY68rsB
j9EtTrvO6x32g8k4y4M52Ca0xWPfVb4jV9XX7Vd4CM+V3oWWse1DgT36c0OtLMqN
sW2NzQEB7IcjDyrG6vPUDzkqjQOqBmQbK/0rBbdRK15TY7kL3VNk+fWDAgFs9sH3
MepmvI6IP2t1WaqAfZktx3P72aXXePuyU4zQyKRB7ViG4l6ClAv6jh6Kq5TeLkEE
2ayQ6z+YcVnRlyd8kbnR8djFvCqvLRwVvByW4jtR0QKBgQDvE59MoV6KdeIk+gQ2
ylB1cxX2BnLqM9w6xowElcY+L9SQKkaS7u5K4bxBqwY0pbdV2ujj+GXcZJm8oKtm
7mZ8gVx6RkV+LOiS6/NBbw8Q8QLbCBujjFEBSjrBo0VB1JCNU7TnN5VvxJ9/6X4T
3NRGOlqB5FXX6v4eWycA0iErPwKBgQDcRV5HSE7vYQ6hIgpOodQFw7EiJMbODwZh
e6nJeH8y8FVZgNQwdGtzH1jXP3YJw8TnfM0FJzS69GUH5j3QaTJM7Y8/NMA1YFzw
Jba+Sf6ulO0TR+EJjzEv9aN0R6kA7BjMEJ1WBS5GMgNlRJwK43Nj7Fgpi6sM3+nI
wK9aq4uZ6QKBgHSNL1crC+5zZIGo10vvMaqkYQZgINspbRTHgb8QvEyfQd4u3gP1
5wH6n5Vh7W1n6cL4Flt0RDSW7j6oDgv2d9pnHlDAX7rZk3PXN5gP9PAZs3I5bFZ4
awQwZuwIcH91LAtsPl1gByy5gRBy9V8sMVhED9+eMlxW66cAuVFQ8A+VAoGAI07N
00z1u0wzD7aTn2Ur9Gc3IO2rN3mnb8mJ6YPmG1Q0gl2n9nct6N1nwlbR0hzDzXh0
s0J0iW7yRu+uKVnyw5L+P7CEgYCUAqX8ueeBvh6V0p4yhroDV+1WLe9rRNGOKNRu
1Mo5UBwpxh7cUApCqjep9VK5TQFW+0SfwnhQ7AECgYEAqYd9K4g3eBuV0ZV30Nzg
RjTY46Ym3CFaJADvOQXKtiwGxIKQHAFtPkWsp3CNY1gp0HfXwUYRWv1dqX5vmqcr
qm4qYlXU9ya64wBYr9oXnhcuWph86Gl5W2R77VUJpmqb7f5sMY7Rzwoz7j2gfGM9
H34hfSfkqXU5L5g8f30wDyE=
-----END PRIVATE KEY-----
KEY,
            'client_email' => 'vision-ocr@scanwell-test.iam.gserviceaccount.com',
            'client_id' => '1234567890',
        ], JSON_THROW_ON_ERROR);
    }
}
