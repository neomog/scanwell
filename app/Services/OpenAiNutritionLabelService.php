<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenAiNutritionLabelService
{
    public function extractFromImage(string $imageBinary, string $mimeType = 'image/jpeg'): ?array
    {
        $apiKey = config('services.openai.api_key');

        if (!is_string($apiKey) || trim($apiKey) === '') {
            return null;
        }

        $payload = [
            'model' => config('services.openai.nutrition_extraction_model', config('services.openai.image_recognition_model', 'gpt-5.4-mini')),
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
                    'name' => 'nutrition_label_extraction',
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
                Log::warning('OpenAI nutrition vision request failed', [
                    'status' => $response->status(),
                    'body' => $response->json() ?? $response->body(),
                ]);

                return null;
            }

            return $this->normalizeResult($response->json());
        } catch (\Throwable $exception) {
            Log::error('OpenAI nutrition vision request errored', [
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

        if (!is_array($decoded) || !isset($decoded['nutrients']) || !is_array($decoded['nutrients'])) {
            return null;
        }

        $lines = [];
        $nutrition = [];

        if (is_string($decoded['serving_basis'] ?? null) && trim($decoded['serving_basis']) !== '') {
            $lines[] = trim($decoded['serving_basis']);
        }

        foreach ($decoded['nutrients'] as $item) {
            if (!is_array($item)) {
                continue;
            }

            $key = $this->nullableString($item['key'] ?? null);
            if ($key === null) {
                continue;
            }

            $value = $this->normalizeNumber($item['value'] ?? null);
            $unit = $this->nullableString($item['unit'] ?? null);

            if ($value === null) {
                continue;
            }

            $label = $this->displayLabelForKey($key);
            $lines[] = trim($label . ' ' . $this->stringifyNumber($value) . ($unit ? $unit : ''));

            $internalKey = $this->mapExternalKeyToInternal($key);
            if ($internalKey !== null) {
                $nutrition[$internalKey] = $value;
            }
        }

        if ($lines === []) {
            return null;
        }

        if (($decoded['serving_basis'] ?? null) && !isset($nutrition['serving_size'])) {
            $nutrition['serving_size'] = trim((string) $decoded['serving_basis']);
        }

        return [
            'nutrition_text' => implode("\n", $lines),
            'nutrition' => $nutrition,
            'extracted_text' => implode("\n", $lines),
            'confidence' => $this->boundedInteger($decoded['confidence'] ?? 0),
            'analysis_source' => 'openai_vision',
            'ocr_mode' => 'structured_nutrition_json',
        ];
    }

    protected function prompt(): string
    {
        return <<<'PROMPT'
Analyze this nutrition label image and extract nutrient names, values, and units.

The label may be formatted in different ways:
- A two-column table
- A comma-separated sentence
- A colon-separated list
- Nutrient names and values on separate lines
- A mixed or irregular layout

Your task is to correctly match each nutrient with its actual value and unit.

Rules:
1. Do not rely only on OCR reading order.
2. If the label is tabular, match nutrients and values that are on the same horizontal row.
3. If the label is comma-separated, parse each nutrient-value pair from each segment.
4. If the label uses colons, parse nutrient: value unit.
5. If a nutrient appears on one line and its value appears on the next line, pair them only when the relationship is clear.
6. Preserve units exactly where possible: kcal, kJ, g, mg, mcg, µg, IU.
7. Convert numeric values to numbers.
8. Do not guess hidden or unclear values.
9. If a value is missing or unclear, return null.
10. Return only valid JSON. Do not include explanations.

Normalize nutrient names into these keys where present:
energy, carbohydrate, sugar, protein, fat, saturated_fat, trans_fat, fiber, sodium, salt, calcium, vitamin_a, vitamin_b1, vitamin_b2, vitamin_b3, vitamin_b6, vitamin_b12, vitamin_c, vitamin_d, vitamin_e, iron, potassium, cholesterol.

Include a serving_basis string only when clearly visible, such as "per 100g", "per 100 ml", or "Serving Size 30 g".
PROMPT;
    }

    protected function schema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'serving_basis' => [
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
                'nutrients' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'properties' => [
                            'key' => [
                                'enum' => [
                                    'energy', 'carbohydrate', 'sugar', 'protein', 'fat', 'saturated_fat', 'trans_fat',
                                    'fiber', 'sodium', 'salt', 'calcium', 'vitamin_a', 'vitamin_b1', 'vitamin_b2',
                                    'vitamin_b3', 'vitamin_b6', 'vitamin_b12', 'vitamin_c', 'vitamin_d', 'vitamin_e',
                                    'iron', 'potassium', 'cholesterol',
                                ],
                            ],
                            'value' => [
                                'anyOf' => [
                                    ['type' => 'number'],
                                    ['type' => 'null'],
                                ],
                            ],
                            'unit' => [
                                'anyOf' => [
                                    ['enum' => ['kcal', 'kJ', 'g', 'mg', 'mcg', 'µg', 'IU']],
                                    ['type' => 'null'],
                                ],
                            ],
                        ],
                        'required' => ['key', 'value', 'unit'],
                    ],
                ],
            ],
            'required' => ['serving_basis', 'confidence', 'nutrients'],
        ];
    }

    protected function displayLabelForKey(string $key): string
    {
        return match ($key) {
            'energy' => 'Energy',
            'carbohydrate' => 'Carbohydrate',
            'sugar' => 'Sugar',
            'protein' => 'Protein',
            'fat' => 'Fat',
            'saturated_fat' => 'Saturated Fat',
            'trans_fat' => 'Trans Fat',
            'fiber' => 'Fiber',
            'sodium' => 'Sodium',
            'salt' => 'Salt',
            'calcium' => 'Calcium',
            'vitamin_a' => 'Vitamin A',
            'vitamin_b1' => 'Vitamin B1',
            'vitamin_b2' => 'Vitamin B2',
            'vitamin_b3' => 'Vitamin B3',
            'vitamin_b6' => 'Vitamin B6',
            'vitamin_b12' => 'Vitamin B12',
            'vitamin_c' => 'Vitamin C',
            'vitamin_d' => 'Vitamin D',
            'vitamin_e' => 'Vitamin E',
            'iron' => 'Iron',
            'potassium' => 'Potassium',
            'cholesterol' => 'Cholesterol',
            default => ucwords(str_replace('_', ' ', $key)),
        };
    }

    protected function mapExternalKeyToInternal(string $key): ?string
    {
        return match ($key) {
            'energy' => 'calories',
            'carbohydrate' => 'carbohydrates',
            'sugar' => 'sugars',
            'protein', 'fat', 'saturated_fat', 'trans_fat', 'fiber', 'sodium', 'calcium', 'vitamin_d', 'iron', 'potassium', 'cholesterol' => $key,
            default => null,
        };
    }

    protected function stringifyNumber(float $value): string
    {
        if (fmod($value, 1.0) === 0.0) {
            return (string) (int) $value;
        }

        return rtrim(rtrim(number_format($value, 4, '.', ''), '0'), '.');
    }

    protected function normalizeNumber(mixed $value): ?float
    {
        if (!is_numeric($value)) {
            return null;
        }

        return (float) $value;
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
