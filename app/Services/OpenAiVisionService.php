<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenAiVisionService
{
    public function __construct(
        protected ScanProviderRuntimeConfigService $runtimeConfig
    ) {
    }

    public function analyzeProductImage(string $imageBinary, string $mimeType = 'image/jpeg'): ?array
    {
        $settings = $this->runtimeConfig->openAi();
        $apiKey = $settings['api_key'] ?? null;

        if (!(bool) ($settings['is_active'] ?? true) || !is_string($apiKey) || trim($apiKey) === '') {
            return null;
        }

        $payload = [
            'model' => $settings['image_recognition_model'] ?? 'gpt-5.4-mini',
            'input' => [[
                'role' => 'user',
                'content' => [
                    [
                        'type' => 'input_text',
                        'text' => $this->prompt(),
                    ],
                    [
                        'type' => 'input_image',
                        'image_url' => $this->toDataUrl($imageBinary, $mimeType),
                    ],
                ],
            ]],
            'text' => [
                'format' => [
                    'type' => 'json_schema',
                    'name' => 'product_image_analysis',
                    'strict' => true,
                    'schema' => $this->schema(),
                ],
            ],
        ];

        try {
            $response = Http::withToken($apiKey)
                ->acceptJson()
                ->retry((int) ($settings['retry_attempts'] ?? 1), 200)
                ->timeout((int) ($settings['timeout'] ?? 30))
                ->post(rtrim((string) ($settings['base_url'] ?? 'https://api.openai.com/v1'), '/') . '/responses', $payload);

            if (!$response->successful()) {
                Log::warning('OpenAI vision request failed', [
                    'status' => $response->status(),
                    'body' => $response->json() ?? $response->body(),
                ]);

                return null;
            }

            return $this->normalizeResult($response->json());
        } catch (\Throwable $exception) {
            Log::error('OpenAI vision request errored', [
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    protected function normalizeResult(array $response): ?array
    {
        $json = data_get($response, 'output.0.content.0.text')
            ?? data_get($response, 'output_text')
            ?? data_get($response, 'content.0.text');

        if (!is_string($json) || trim($json) === '') {
            return null;
        }

        $decoded = json_decode($json, true);

        if (!is_array($decoded)) {
            return null;
        }

        return [
            'barcode' => $this->nullableString($decoded['barcode'] ?? null),
            'product_name' => $this->nullableString($decoded['product_name'] ?? null),
            'brand' => $this->nullableString($decoded['brand'] ?? null),
            'category_hint' => $this->nullableString($decoded['category_hint'] ?? null),
            'extracted_text' => $this->nullableString($decoded['extracted_text'] ?? null),
            'confidence' => $this->boundedInteger($decoded['confidence'] ?? null),
            'front_label_visible' => (bool) ($decoded['front_label_visible'] ?? false),
            'barcode_visible' => (bool) ($decoded['barcode_visible'] ?? false),
            'nutrition_panel_visible' => (bool) ($decoded['nutrition_panel_visible'] ?? false),
            'ingredients_visible' => (bool) ($decoded['ingredients_visible'] ?? false),
        ];
    }

    protected function prompt(): string
    {
        return 'Analyze this product photo and extract product-identification signals for a catalog lookup. '
            . 'Return only structured data for the exact product shown. '
            . 'If a barcode is visible, extract the digits exactly. '
            . 'If text is unclear, leave that field null rather than guessing. '
            . 'Use the full readable product label text for extracted_text.';
    }

    protected function schema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'barcode' => [
                    'anyOf' => [
                        [
                            'type' => 'string',
                            'pattern' => '^[0-9]{8,13}$',
                        ],
                        [
                            'type' => 'null',
                        ],
                    ],
                ],
                'product_name' => [
                    'anyOf' => [
                        ['type' => 'string'],
                        ['type' => 'null'],
                    ],
                ],
                'brand' => [
                    'anyOf' => [
                        ['type' => 'string'],
                        ['type' => 'null'],
                    ],
                ],
                'category_hint' => [
                    'enum' => ['food', 'cosmetic', 'pet_food', 'household', 'general', null],
                ],
                'extracted_text' => [
                    'anyOf' => [
                        ['type' => 'string'],
                        ['type' => 'null'],
                    ],
                ],
                'confidence' => [
                    'type' => 'integer',
                    'minimum' => 0,
                    'maximum' => 100,
                ],
                'front_label_visible' => [
                    'type' => 'boolean',
                ],
                'barcode_visible' => [
                    'type' => 'boolean',
                ],
                'nutrition_panel_visible' => [
                    'type' => 'boolean',
                ],
                'ingredients_visible' => [
                    'type' => 'boolean',
                ],
            ],
            'required' => [
                'barcode',
                'product_name',
                'brand',
                'category_hint',
                'extracted_text',
                'confidence',
                'front_label_visible',
                'barcode_visible',
                'nutrition_panel_visible',
                'ingredients_visible',
            ],
        ];
    }

    protected function toDataUrl(string $imageBinary, string $mimeType): string
    {
        return 'data:' . $mimeType . ';base64,' . base64_encode($imageBinary);
    }

    protected function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    protected function boundedInteger(mixed $value): int
    {
        $value = is_numeric($value) ? (int) $value : 0;

        return max(0, min(100, $value));
    }
}
