<?php

namespace Tests\Unit;

use App\Services\OpenAiBarcodeWebSearchService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenAiBarcodeWebSearchServiceTest extends TestCase
{
    public function test_it_returns_an_exact_barcode_web_match_in_the_existing_provider_shape(): void
    {
        Http::fake([
            'https://api.openai.com/v1/responses' => Http::response([
                'output_text' => json_encode([
                    'found' => true,
                    'barcode' => '123456789012',
                    'name' => 'Example Product',
                    'brand' => 'Example Brand',
                    'product_family' => 'food',
                    'image_url' => null,
                    'primary_source_url' => 'https://example.com/product',
                    'ingredients' => ['water', 'salt'],
                    'nutrition' => [
                        'calories' => 10,
                        'fat' => null,
                        'saturated_fat' => null,
                        'carbohydrates' => 1,
                        'fiber' => null,
                        'sugars' => 0,
                        'protein' => 0,
                        'sodium' => 100,
                        'serving_size' => '100 g',
                    ],
                    'source_urls' => ['https://example.com/product'],
                    'evidence_summary' => 'The source lists the exact barcode.',
                    'confidence' => 90,
                ]),
            ]),
        ]);

        $candidate = app(OpenAiBarcodeWebSearchService::class)->findByBarcode(
            '123456789012',
            [
                'base_url' => 'https://api.openai.com/v1',
                'barcode_web_search_model' => 'test-model',
                'timeout' => 5,
                'retry_attempts' => 0,
                'cache_ttl_minutes' => 1,
            ],
            ['api_key' => 'test-key']
        );

        $this->assertSame('123456789012', $candidate['barcode']);
        $this->assertSame('Example Product', $candidate['name']);
        $this->assertSame('openai_web_search', $candidate['source']);
        $this->assertNotEmpty($candidate['ingredients']);

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://api.openai.com/v1/responses'
                && $request->hasHeader('Authorization', 'Bearer test-key')
                && data_get($request->data(), 'tools.0.type') === 'web_search';
        });
    }

    public function test_it_rejects_a_web_result_for_a_different_barcode(): void
    {
        Http::fake([
            'https://api.openai.com/v1/responses' => Http::response([
                'output_text' => json_encode([
                    'found' => true,
                    'barcode' => '999999999999',
                    'name' => 'Wrong Product',
                ]),
            ]),
        ]);

        $candidate = app(OpenAiBarcodeWebSearchService::class)->findByBarcode(
            '123456789012',
            ['base_url' => 'https://api.openai.com/v1', 'retry_attempts' => 0],
            ['api_key' => 'test-key']
        );

        $this->assertNull($candidate);
    }
}
