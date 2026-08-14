<?php

namespace App\Services;

use App\Contracts\ProductCatalogProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenAiBarcodeWebSearchService implements ProductCatalogProvider
{
    public function __construct(
        protected ScanProviderRuntimeConfigService $runtimeConfig
    ) {
    }

    public function providerKey(): string
    {
        return 'openai_barcode_web';
    }

    public function findByBarcode(string $barcode, array $settings = [], array $credentials = []): ?array
    {
        $barcode = trim($barcode);
        $runtimeSettings = [];

        if (!isset($credentials['api_key']) || !array_key_exists('base_url', $settings)) {
            $runtimeSettings = $this->runtimeConfig->openAi();
        }

        $apiKey = $credentials['api_key'] ?? $runtimeSettings['api_key'] ?? null;

        if ($barcode === '' || !is_string($apiKey) || trim($apiKey) === '') {
            return null;
        }

        $settings = array_merge($runtimeSettings, $settings);
        $model = (string) ($settings['barcode_web_search_model'] ?? 'gpt-5.4-mini');
        $baseUrl = rtrim((string) ($settings['base_url'] ?? 'https://api.openai.com/v1'), '/');

        $payload = [
            'model' => $model,
            'tools' => [
                ['type' => 'web_search'],
            ],
            'input' => [[
                'role' => 'user',
                'content' => [[
                    'type' => 'input_text',
                    'text' => $this->prompt($barcode),
                ]],
            ]],
            'text' => [
                'format' => [
                    'type' => 'json_schema',
                    'name' => 'barcode_web_product_match',
                    'strict' => true,
                    'schema' => $this->schema(),
                ],
            ],
        ];

        $cacheKey = 'scanwell:openai_barcode_web:' . sha1($barcode);

        return Cache::remember(
            $cacheKey,
            now()->addMinutes(max(1, (int) ($settings['cache_ttl_minutes'] ?? 1440))),
            function () use ($apiKey, $baseUrl, $barcode, $model, $settings, $payload): ?array {
                return $this->requestAndNormalize($apiKey, $baseUrl, $barcode, $model, $settings, $payload);
            }
        );
    }

    protected function requestAndNormalize(
        string $apiKey,
        string $baseUrl,
        string $barcode,
        string $model,
        array $settings,
        array $payload
    ): ?array {
        try {
            $response = Http::withToken($apiKey)
                ->acceptJson()
                ->retry((int) ($settings['retry_attempts'] ?? 1), 250)
                ->timeout((int) ($settings['timeout'] ?? 30))
                ->post($baseUrl . '/responses', $payload);

            if (!$response->successful()) {
                Log::warning('OpenAI barcode web search failed', [
                    'barcode' => $barcode,
                    'status' => $response->status(),
                ]);

                return null;
            }

            return $this->normalizeResult($response->json(), $barcode);
        } catch (\Throwable $exception) {
            Log::warning('OpenAI barcode web search errored', [
                'barcode' => $barcode,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    protected function normalizeResult(array $response, string $barcode): ?array
    {
        $json = data_get($response, 'output_text')
            ?? data_get($response, 'output.0.content.0.text');

        if (!is_string($json) || trim($json) === '') {
            return null;
        }

        $decoded = json_decode($json, true);

        if (!is_array($decoded) || ($decoded['found'] ?? false) !== true) {
            return null;
        }

        $returnedBarcode = preg_replace('/\D+/', '', (string) ($decoded['barcode'] ?? ''));

        if ($returnedBarcode !== $barcode) {
            Log::warning('Discarded OpenAI web result with non-exact barcode', [
                'requested_barcode' => $barcode,
                'returned_barcode' => $returnedBarcode,
            ]);

            return null;
        }

        $name = trim((string) ($decoded['name'] ?? ''));

        if ($name === '') {
            return null;
        }

        $ingredients = collect($decoded['ingredients'] ?? [])
            ->filter(fn ($ingredient): bool => is_string($ingredient) && trim($ingredient) !== '')
            ->map(fn (string $ingredient): array => [
                'name' => trim($ingredient),
                'percent' => null,
                'vegan' => null,
                'vegetarian' => null,
                'origin' => null,
            ])
            ->values()
            ->all();

        $nutrition = array_filter(
            (array) ($decoded['nutrition'] ?? []),
            fn ($value): bool => $value !== null && $value !== ''
        );

        $rawData = [
            'web_search' => [
                'source_urls' => array_values(array_filter((array) ($decoded['source_urls'] ?? []), 'is_string')),
                'evidence_summary' => trim((string) ($decoded['evidence_summary'] ?? '')),
                'confidence' => (int) ($decoded['confidence'] ?? 0),
            ],
        ];

        return [
            'barcode' => $barcode,
            'name' => $name,
            'brand' => $this->nullableString($decoded['brand'] ?? null),
            'category_id' => null,
            'image_url' => $this->nullableString($decoded['image_url'] ?? null),
            'page_url' => $this->nullableString($decoded['primary_source_url'] ?? null),
            'source' => 'openai_web_search',
            'product_type' => $this->normalizeFamily($decoded['product_family'] ?? null),
            'ingredients' => $ingredients,
            'nutrition' => $nutrition,
            'packaging' => [],
            'warnings' => [
                'Product identity was found through web search and should be verified against the package.',
            ],
            'raw_data' => $rawData,
            'preliminary_score' => 30,
        ];
    }

    protected function prompt(string $barcode): string
    {
        return <<<PROMPT
Search the web for the exact retail product identified by barcode {$barcode}.

Rules:
1. You must use web search. Do not answer from memory alone.
2. Accept a product only when a trustworthy page explicitly connects this exact barcode to the product.
3. Prefer manufacturer pages, official brand pages, reputable retailers, or established barcode/product directories.
4. Never infer or invent ingredients, nutrition, allergens, images, or product identity.
5. If reliable sources do not identify this exact barcode, return found=false.
6. Return nutrition only when it is explicitly published by a source and clearly associated with this product.
7. Return only the requested JSON.

The exact barcode is {$barcode}.
PROMPT;
    }

    protected function schema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'found' => ['type' => 'boolean'],
                'barcode' => ['type' => 'string'],
                'name' => ['type' => 'string'],
                'brand' => ['anyOf' => [['type' => 'string'], ['type' => 'null']]],
                'product_family' => ['enum' => ['food', 'cosmetic', 'pet_food', 'household', 'general']],
                'image_url' => ['anyOf' => [['type' => 'string'], ['type' => 'null']]],
                'primary_source_url' => ['anyOf' => [['type' => 'string'], ['type' => 'null']]],
                'ingredients' => ['type' => 'array', 'items' => ['type' => 'string']],
                'nutrition' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'properties' => [
                        'calories' => ['anyOf' => [['type' => 'number'], ['type' => 'null']]],
                        'fat' => ['anyOf' => [['type' => 'number'], ['type' => 'null']]],
                        'saturated_fat' => ['anyOf' => [['type' => 'number'], ['type' => 'null']]],
                        'carbohydrates' => ['anyOf' => [['type' => 'number'], ['type' => 'null']]],
                        'fiber' => ['anyOf' => [['type' => 'number'], ['type' => 'null']]],
                        'sugars' => ['anyOf' => [['type' => 'number'], ['type' => 'null']]],
                        'protein' => ['anyOf' => [['type' => 'number'], ['type' => 'null']]],
                        'sodium' => ['anyOf' => [['type' => 'number'], ['type' => 'null']]],
                        'serving_size' => ['anyOf' => [['type' => 'string'], ['type' => 'null']]],
                    ],
                    'required' => ['calories', 'fat', 'saturated_fat', 'carbohydrates', 'fiber', 'sugars', 'protein', 'sodium', 'serving_size'],
                ],
                'source_urls' => ['type' => 'array', 'items' => ['type' => 'string']],
                'evidence_summary' => ['type' => 'string'],
                'confidence' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 100],
            ],
            'required' => ['found', 'barcode', 'name', 'brand', 'product_family', 'image_url', 'primary_source_url', 'ingredients', 'nutrition', 'source_urls', 'evidence_summary', 'confidence'],
        ];
    }

    protected function normalizeFamily(mixed $family): string
    {
        return in_array($family, ['food', 'cosmetic', 'pet_food', 'household', 'general'], true)
            ? $family
            : 'general';
    }

    protected function nullableString(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }
}
