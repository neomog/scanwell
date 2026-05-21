<?php

namespace Tests\Feature;

use App\Models\Product;
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
