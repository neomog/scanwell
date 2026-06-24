<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenAiProductImageIdentityService
{
    public function extractFromImage(string $imageBinary, string $mimeType = 'image/jpeg'): ?array
    {
        $apiKey = config('services.openai.api_key');

        if (!is_string($apiKey) || trim($apiKey) === '') {
            return null;
        }

        $payload = [
            'model' => config('services.openai.image_recognition_model', 'gpt-5.4-mini'),
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
                    'name' => 'product_image_identity',
                    'strict' => true,
                    'schema' => $this->schema(),
                ],
            ],
        ];

        try {
            $response = Http::withToken($apiKey)
                ->acceptJson()
                ->timeout((int) config('services.openai.timeout', 30))
                ->post(rtrim((string) config('services.openai.base_url', 'https://api.openai.com/v1'), '/') . '/responses', $payload);

            if (!$response->successful()) {
                Log::warning('OpenAI product image identity request failed', [
                    'status' => $response->status(),
                    'body' => $response->json() ?? $response->body(),
                ]);

                return null;
            }

            return $this->normalizeResult($response->json());
        } catch (\Throwable $exception) {
            Log::error('OpenAI product image identity request errored', [
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

        $barcode = $this->normalizeBarcode($decoded['barcode_hint'] ?? null);
        $brand = $this->nullableString($decoded['brand'] ?? null);
        $productName = $this->nullableString($decoded['product_name'] ?? null);
        $extractedText = $this->nullableString($decoded['extracted_text'] ?? null);

        if ($barcode === null && $brand === null && $productName === null && $extractedText === null) {
            return null;
        }

        return [
            'extracted_text' => $extractedText,
            'product_name' => $productName,
            'brand' => $brand,
            'barcode_hint' => $barcode,
            'confidence' => $this->boundedInteger($decoded['confidence'] ?? 0) / 100,
            'provider' => 'openai_vision',
            'mode' => 'structured_product_identity_json',
        ];
    }

    protected function prompt(): string
    {
        return <<<'PROMPT'
Analyze this product image and extract product-identifying information.

The image may be:
- A front package photo
- A gallery image
- A shelf photo
- A back label
- A mixed or partially visible product shot

Your task is to extract only the most useful product identity fields for catalog matching.

Rules:
1. Do not rely only on OCR reading order.
2. Prefer the actual product brand and product name shown on the packaging.
3. If a barcode is clearly visible, return only the readable 8-13 digit barcode.
4. If the product name spans multiple lines, join it correctly.
5. Ignore nutrition facts, ingredient lists, warnings, storage instructions, and unrelated small-print unless they are the only readable clues.
6. The extracted_text field should be a short readable summary of the most identifying visible text, not every OCR token.
7. Do not guess hidden or blurry values.
8. If a field is unclear, return null for that field.
9. Return only valid JSON. Do not include explanations.
PROMPT;
    }

    protected function schema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'barcode_hint' => [
                    'anyOf' => [
                        ['type' => 'string'],
                        ['type' => 'null'],
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
            ],
            'required' => ['barcode_hint', 'product_name', 'brand', 'extracted_text', 'confidence'],
        ];
    }

    protected function normalizeBarcode(mixed $value): ?string
    {
        $value = $this->nullableString($value);

        if ($value === null) {
            return null;
        }

        if (preg_match('/^\d{8,13}$/', $value) !== 1) {
            return null;
        }

        return $value;
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

    protected function toDataUrl(string $imageBinary, string $mimeType): string
    {
        return 'data:' . $mimeType . ';base64,' . base64_encode($imageBinary);
    }
}
