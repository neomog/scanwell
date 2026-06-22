<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenAiIngredientLabelService
{
    public function extractFromImage(string $imageBinary, string $mimeType = 'image/jpeg'): ?array
    {
        $apiKey = config('services.openai.api_key');

        if (!is_string($apiKey) || trim($apiKey) === '') {
            return null;
        }

        $payload = [
            'model' => config('services.openai.ingredient_extraction_model', config('services.openai.image_recognition_model', 'gpt-5.4-mini')),
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
                    'name' => 'ingredient_label_extraction',
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
                Log::warning('OpenAI ingredient vision request failed', [
                    'status' => $response->status(),
                    'body' => $response->json() ?? $response->body(),
                ]);

                return null;
            }

            return $this->normalizeResult($response->json());
        } catch (\Throwable $exception) {
            Log::error('OpenAI ingredient vision request errored', [
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

        if (!is_array($decoded) || !isset($decoded['ingredients']) || !is_array($decoded['ingredients'])) {
            return null;
        }

        $ingredients = collect($decoded['ingredients'])
            ->map(fn ($item) => is_string($item) ? trim($item) : null)
            ->filter()
            ->values()
            ->all();

        if ($ingredients === []) {
            return null;
        }

        $ingredientsText = implode(', ', $ingredients);

        return [
            'ingredients_text' => $ingredientsText,
            'ingredients' => $ingredients,
            'extracted_text' => $ingredientsText,
            'confidence' => $this->boundedInteger($decoded['confidence'] ?? 0),
            'analysis_source' => 'openai_vision',
            'ocr_mode' => 'structured_ingredients_json',
        ];
    }

    protected function prompt(): string
    {
        return <<<'PROMPT'
Analyze this product image and extract the ingredient list.

Rules:
1. Return only the actual ingredient names in label order.
2. If the image contains headings like Ingredients, Active Ingredients, or Inactive Ingredients, use the ingredient list that follows.
3. Do not rely only on OCR reading order when text appears in columns, blocks, or wrapped lines.
4. If ingredients are split across multiple lines, join them into one continuous ingredient list.
5. If the label uses separators like commas, semicolons, bullets, or colons, preserve the real ingredient boundaries.
6. Ignore nutrition facts, warnings, directions, dosage, supplement facts, marketing copy, barcodes, and unrelated text.
7. Preserve ingredient wording as shown where readable.
8. Do not guess hidden or unclear ingredients.
9. Return only valid JSON. Do not include explanations.
PROMPT;
    }

    protected function schema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'confidence' => [
                    'type' => 'integer',
                    'minimum' => 0,
                    'maximum' => 100,
                ],
                'ingredients' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'string',
                    ],
                ],
            ],
            'required' => ['confidence', 'ingredients'],
        ];
    }

    protected function toDataUrl(string $imageBinary, string $mimeType): string
    {
        return 'data:' . $mimeType . ';base64,' . base64_encode($imageBinary);
    }

    protected function boundedInteger(mixed $value): int
    {
        $value = is_numeric($value) ? (int) $value : 0;

        return max(0, min(100, $value));
    }
}
