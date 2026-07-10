<?php

namespace App\Services;

use App\Contracts\ProductCatalogImportProvider;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class UsdaFoodDataCentralService implements ProductCatalogImportProvider
{
    protected bool $verifySsl;

    public function __construct()
    {
        $this->verifySsl = App::environment('production');
    }

    public function providerKey(): string
    {
        return 'usda_fdc';
    }

    public function searchProducts(
        string $query,
        int $page = 1,
        int $pageSize = 20,
        array $settings = [],
        array $credentials = []
    ): array {
        $resolved = $this->resolveSettings($settings, $credentials);
        $query = trim($query);

        if ($query === '' || blank($resolved['api_key'])) {
            return ['products' => [], 'total' => 0, 'page' => $page, 'page_count' => 0];
        }

        if ($this->isTemporarilyThrottled()) {
            throw new \RuntimeException('USDA FoodData Central requests are temporarily paused after a rate-limit response.');
        }

        $cacheKey = 'scanwell:usda_fdc:search:' . sha1(implode('|', [
            $query,
            $page,
            $pageSize,
            implode(',', $resolved['data_types']),
        ]));

        return Cache::remember($cacheKey, now()->addMinutes($resolved['cache_ttl_minutes']), function () use ($query, $page, $pageSize, $resolved) {
            try {
                $response = $this->createHttpClient($resolved)
                    ->post($resolved['base_url'] . '?api_key=' . urlencode((string) $resolved['api_key']), [
                        'query' => $query,
                        'pageNumber' => $page,
                        'pageSize' => $pageSize,
                        'dataType' => $resolved['data_types'],
                    ]);

                if (!$response->successful()) {
                    if ($response->status() === 429) {
                        $this->markTemporarilyThrottled($resolved);

                        throw new \RuntimeException('USDA FoodData Central rate limit exceeded.');
                    }

                    Log::warning('USDA FoodData Central search failed', [
                        'query' => $query,
                        'status' => $response->status(),
                        'body' => $response->body(),
                    ]);

                    return ['products' => [], 'total' => 0, 'page' => $page, 'page_count' => 0];
                }

                $payload = $response->json();
                $products = collect($payload['foods'] ?? [])
                    ->filter(fn ($food) => is_array($food))
                    ->map(fn (array $food): ?array => $this->transformFood($food))
                    ->filter()
                    ->unique('barcode')
                    ->values();

                $total = (int) ($payload['totalHits'] ?? $products->count());
                $pageCount = $total > 0 && $pageSize > 0 ? (int) ceil($total / $pageSize) : 0;

                return [
                    'products' => $products->all(),
                    'total' => $total,
                    'page' => $page,
                    'page_count' => $pageCount,
                ];
            } catch (\RuntimeException $exception) {
                throw $exception;
            } catch (\Throwable $exception) {
                Log::error('USDA FoodData Central integration failed', [
                    'query' => $query,
                    'error' => $exception->getMessage(),
                ]);

                return ['products' => [], 'total' => 0, 'page' => $page, 'page_count' => 0];
            }
        });
    }

    protected function transformFood(array $food): ?array
    {
        $barcode = $this->extractBarcode($food);

        if ($barcode === null) {
            return null;
        }

        $ingredientsText = $this->normalizeOptionalString($food['ingredients'] ?? null);
        $ingredients = $ingredientsText !== null ? $this->parseIngredients($ingredientsText) : [];
        $nutrition = $this->extractNutrition($food);
        $nutritionText = $this->buildNutritionText($nutrition['lines'], $this->resolveServingSize($food));
        $nutritionValues = $nutrition['values'];
        $warnings = [];

        if ($ingredients === []) {
            $warnings[] = 'USDA result did not include an ingredient list.';
        }

        if ($nutritionValues === []) {
            $warnings[] = 'USDA result did not include label nutrition values.';
        }

        return [
            'barcode' => $barcode,
            'name' => $this->resolveName($food),
            'brand' => $this->resolveBrand($food),
            'category_id' => null,
            'image_url' => null,
            'page_url' => null,
            'source' => 'usda_fdc',
            'product_type' => 'food',
            'ingredients' => $ingredients,
            'ingredients_text' => $ingredientsText,
            'additives' => $this->extractAdditives($ingredients),
            'nutrition' => $nutritionValues,
            'nutrition_text' => $nutritionText,
            'packaging' => [
                'description' => null,
                'materials' => [],
                'is_plastic' => false,
            ],
            'warnings' => array_values(array_unique($warnings)),
            'raw_data' => [
                'food' => $food,
            ],
        ];
    }

    protected function extractNutrition(array $food): array
    {
        $values = [];
        $lines = [];

        foreach ($this->labelNutrientMap() as $path => [$key, $label, $unit]) {
            $value = $this->normalizeNumber(data_get($food, $path));

            if ($value === null) {
                continue;
            }

            $values[$key] = $value;
            $lines[$key] = [
                'label' => $label,
                'value' => $this->formatNumber($value),
                'unit' => $unit,
            ];
        }

        foreach ($food['foodNutrients'] ?? [] as $nutrient) {
            if (!is_array($nutrient)) {
                continue;
            }

            $resolved = $this->resolveFoodNutrient($nutrient);

            if ($resolved === null) {
                continue;
            }

            [$key, $label, $unit, $value] = $resolved;

            if (!array_key_exists($key, $values)) {
                $values[$key] = $value;
            }

            if (!array_key_exists($key, $lines)) {
                $lines[$key] = [
                    'label' => $label,
                    'value' => $this->formatNumber($value),
                    'unit' => $unit,
                ];
            }
        }

        $servingSize = $this->resolveServingSize($food);

        if ($servingSize !== null) {
            $values['serving_size'] = $servingSize;
        }

        return [
            'values' => $values,
            'lines' => array_values($lines),
        ];
    }

    protected function buildNutritionText(array $lines, ?string $servingSize): ?string
    {
        $rows = [];

        if ($servingSize !== null) {
            $rows[] = 'Serving Size ' . $servingSize;
        }

        foreach ($lines as $line) {
            $label = trim((string) ($line['label'] ?? ''));
            $value = trim((string) ($line['value'] ?? ''));
            $unit = trim((string) ($line['unit'] ?? ''));

            if ($label === '' || $value === '') {
                continue;
            }

            $rows[] = trim($label . ' ' . $value . ($unit !== '' ? ' ' . $unit : ''));
        }

        return $rows !== [] ? implode("\n", $rows) : null;
    }

    protected function resolveFoodNutrient(array $nutrient): ?array
    {
        $name = strtolower(trim((string) (
            data_get($nutrient, 'nutrient.name')
            ?? $nutrient['nutrientName']
            ?? $nutrient['name']
            ?? ''
        )));
        $value = $this->normalizeNumber($nutrient['amount'] ?? $nutrient['value'] ?? null);
        $unit = trim((string) (
            data_get($nutrient, 'nutrient.unitName')
            ?? $nutrient['unitName']
            ?? ''
        ));
        $normalizedUnit = $this->normalizeUnit($unit);

        if ($name === '' || $value === null) {
            return null;
        }

        $map = [
            'energy' => ['calories', 'Calories'],
            'total lipid (fat)' => ['fat', 'Fat'],
            'fatty acids, total saturated' => ['saturated_fat', 'Saturated Fat'],
            'fatty acids, total trans' => ['trans_fat', 'Trans Fat'],
            'cholesterol' => ['cholesterol', 'Cholesterol'],
            'sodium, na' => ['sodium', 'Sodium'],
            'carbohydrate, by difference' => ['carbohydrates', 'Carbohydrates'],
            'fiber, total dietary' => ['fiber', 'Fiber'],
            'sugars, total including nlea' => ['sugars', 'Sugars'],
            'sugars, added' => ['added_sugars', 'Added Sugars'],
            'protein' => ['protein', 'Protein'],
            'calcium, ca' => ['calcium', 'Calcium'],
            'iron, fe' => ['iron', 'Iron'],
            'potassium, k' => ['potassium', 'Potassium'],
            'vitamin d (d2 + d3)' => ['vitamin_d', 'Vitamin D'],
        ];

        foreach ($map as $needle => [$key, $label]) {
            if ($name === $needle || str_contains($name, $needle)) {
                if ($key === 'calories' && $normalizedUnit !== '' && $normalizedUnit !== 'kcal') {
                    return null;
                }

                return [$key, $label, $normalizedUnit, $value];
            }
        }

        return null;
    }

    protected function parseIngredients(string $ingredientsText): array
    {
        $segments = [];
        $buffer = '';
        $depth = 0;

        foreach (preg_split('//u', $ingredientsText, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $char) {
            if ($char === '(') {
                $depth++;
            } elseif ($char === ')' && $depth > 0) {
                $depth--;
            }

            if (($char === ',' || $char === ';') && $depth === 0) {
                $segments[] = $buffer;
                $buffer = '';
                continue;
            }

            $buffer .= $char;
        }

        if (trim($buffer) !== '') {
            $segments[] = $buffer;
        }

        if ($segments === []) {
            $segments = [$ingredientsText];
        }

        return collect($segments)
            ->map(function (string $segment): ?array {
                $name = trim($segment, " \t\n\r\0\x0B.,;");

                return $name !== '' ? ['name' => $name] : null;
            })
            ->filter()
            ->values()
            ->all();
    }

    protected function extractAdditives(array $ingredients): array
    {
        return collect($ingredients)
            ->map(function (array $ingredient): ?string {
                $name = trim((string) ($ingredient['name'] ?? ''));

                return $name !== '' ? $name : null;
            })
            ->filter(function (?string $name): bool {
                if ($name === null) {
                    return false;
                }

                $normalized = strtolower($name);

                foreach (['lecithin', 'emulsifier', 'preserv', 'color', 'colour', 'flavor', 'flavour', 'stabilizer'] as $keyword) {
                    if (str_contains($normalized, $keyword)) {
                        return true;
                    }
                }

                return (bool) preg_match('/\be\d{3}\b/i', $normalized);
            })
            ->values()
            ->all();
    }

    protected function resolveSettings(array $settings, array $credentials): array
    {
        return [
            'base_url' => (string) ($settings['base_url'] ?? config('services.usda_fdc.base_url', 'https://api.nal.usda.gov/fdc/v1/foods/search')),
            'api_key' => $credentials['api_key'] ?? config('services.usda_fdc.api_key'),
            'data_types' => $this->normalizeDataTypes($settings['data_types'] ?? config('services.usda_fdc.data_types', ['Branded'])),
            'timeout' => (int) ($settings['timeout'] ?? config('services.usda_fdc.timeout', 10)),
            'retry_attempts' => (int) ($settings['retry_attempts'] ?? config('services.usda_fdc.retry_attempts', 1)),
            'cache_ttl_minutes' => (int) ($settings['cache_ttl_minutes'] ?? 1440),
            'cooldown_seconds' => (int) ($settings['cooldown_seconds'] ?? config('services.usda_fdc.cooldown_seconds', 3600)),
        ];
    }

    protected function normalizeDataTypes(mixed $value): array
    {
        $items = is_array($value) ? $value : preg_split('/[\r\n,;]+/', (string) $value);

        $dataTypes = collect($items)
            ->map(fn ($item) => trim((string) $item))
            ->filter()
            ->unique()
            ->values()
            ->all();

        return $dataTypes !== [] ? $dataTypes : ['Branded'];
    }

    protected function createHttpClient(array $settings): PendingRequest
    {
        $client = Http::acceptJson()
            ->asJson()
            ->timeout($settings['timeout'])
            ->retry($settings['retry_attempts'], 200);

        return $this->verifySsl ? $client : $client->withoutVerifying();
    }

    protected function extractBarcode(array $food): ?string
    {
        foreach ([
            $food['gtinUpc'] ?? null,
            $food['gtin_upc'] ?? null,
            $food['upc'] ?? null,
        ] as $candidate) {
            $barcode = $this->normalizeBarcode($candidate);

            if ($barcode !== null) {
                return $barcode;
            }
        }

        return null;
    }

    protected function resolveName(array $food): string
    {
        $name = trim((string) ($food['description'] ?? ''));

        return $name !== '' ? $name : 'Unknown Product';
    }

    protected function resolveBrand(array $food): ?string
    {
        foreach ([
            $food['brandOwner'] ?? null,
            $food['brandName'] ?? null,
            $food['subbrandName'] ?? null,
        ] as $candidate) {
            $value = trim((string) $candidate);

            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    protected function resolveServingSize(array $food): ?string
    {
        $size = $this->normalizeNumber($food['servingSize'] ?? null);
        $unit = trim((string) ($food['servingSizeUnit'] ?? ''));

        if ($size === null) {
            return null;
        }

        return trim($this->formatNumber($size) . ($unit !== '' ? ' ' . $this->normalizeUnit($unit) : ''));
    }

    protected function normalizeBarcode(mixed $barcode): ?string
    {
        if ($barcode === null) {
            return null;
        }

        $normalized = preg_replace('/\D+/', '', (string) $barcode);

        return $normalized !== '' ? $normalized : null;
    }

    protected function normalizeOptionalString(mixed $value): ?string
    {
        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }

    protected function normalizeNumber(mixed $value): int|float|null
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (float) $value == (int) $value ? (int) $value : (float) $value;
        }

        return null;
    }

    protected function formatNumber(int|float $value): string
    {
        if ((float) $value === (float) ((int) $value)) {
            return (string) ((int) $value);
        }

        return rtrim(rtrim(number_format((float) $value, 4, '.', ''), '0'), '.');
    }

    protected function normalizeUnit(?string $unit): string
    {
        $normalized = strtoupper(trim((string) $unit));

        return match ($normalized) {
            'G' => 'g',
            'MG' => 'mg',
            'MCG' => 'mcg',
            'UG' => 'mcg',
            'KCAL' => 'kcal',
            'KJ' => 'kJ',
            default => trim((string) $unit),
        };
    }

    protected function labelNutrientMap(): array
    {
        return [
            'labelNutrients.calories.value' => ['calories', 'Calories', 'kcal'],
            'labelNutrients.fat.value' => ['fat', 'Fat', 'g'],
            'labelNutrients.saturatedFat.value' => ['saturated_fat', 'Saturated Fat', 'g'],
            'labelNutrients.transFat.value' => ['trans_fat', 'Trans Fat', 'g'],
            'labelNutrients.cholesterol.value' => ['cholesterol', 'Cholesterol', 'mg'],
            'labelNutrients.sodium.value' => ['sodium', 'Sodium', 'mg'],
            'labelNutrients.carbohydrates.value' => ['carbohydrates', 'Carbohydrates', 'g'],
            'labelNutrients.fiber.value' => ['fiber', 'Fiber', 'g'],
            'labelNutrients.sugars.value' => ['sugars', 'Sugars', 'g'],
            'labelNutrients.addedSugars.value' => ['added_sugars', 'Added Sugars', 'g'],
            'labelNutrients.protein.value' => ['protein', 'Protein', 'g'],
            'labelNutrients.calcium.value' => ['calcium', 'Calcium', 'mg'],
            'labelNutrients.iron.value' => ['iron', 'Iron', 'mg'],
            'labelNutrients.potassium.value' => ['potassium', 'Potassium', 'mg'],
            'labelNutrients.postassium.value' => ['potassium', 'Potassium', 'mg'],
        ];
    }

    protected function isTemporarilyThrottled(): bool
    {
        return Cache::has($this->throttleCacheKey());
    }

    protected function markTemporarilyThrottled(array $settings): void
    {
        Cache::put(
            $this->throttleCacheKey(),
            true,
            now()->addSeconds((int) ($settings['cooldown_seconds'] ?? config('services.usda_fdc.cooldown_seconds', 3600)))
        );
    }

    protected function throttleCacheKey(): string
    {
        return 'scanwell:usda_fdc:temporarily-throttled';
    }
}
