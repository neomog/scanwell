<?php

namespace App\Services;

use App\Contracts\ProductCatalogImportProvider;
use App\Contracts\ProductCatalogProvider;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenFoodFactsService implements ProductCatalogImportProvider, ProductCatalogProvider
{
    protected bool $verifySsl;

    public function __construct()
    {
        $this->verifySsl = App::environment('production');
    }

    public function providerKey(): string
    {
        return 'open_facts';
    }

    public function findByBarcode(string $barcode, array $settings = [], array $credentials = []): ?array
    {
        $resolvedSettings = $this->resolveSettings($settings);
        $cacheKey = "scanwell:open_facts:product:{$barcode}";

        return Cache::remember($cacheKey, now()->addMinutes($resolvedSettings['cache_ttl_minutes']), function () use ($barcode, $resolvedSettings) {
            $candidates = [];

            foreach ($resolvedSettings['sources'] as $source) {
                $candidate = $this->fetchFromSource($source, $barcode, $resolvedSettings);

                if ($candidate !== null) {
                    $candidates[] = $candidate;
                }
            }

            if ($candidates === []) {
                Log::info('Product not found on configured Open Facts sources', ['barcode' => $barcode]);

                return null;
            }

            usort($candidates, function (array $left, array $right): int {
                return $right['preliminary_score'] <=> $left['preliminary_score'];
            });

            return $candidates[0];
        });
    }

    public function searchProducts(string $query, int $page = 1, int $pageSize = 20, array $settings = [], array $credentials = []): array
    {
        $resolvedSettings = $this->resolveSettings($settings);
        $primarySource = $resolvedSettings['sources'][0] ?? null;

        if ($primarySource === null) {
            return ['products' => [], 'total' => 0, 'page' => $page, 'page_count' => 0];
        }

        try {
            $response = $this->createHttpClient($resolvedSettings)->get("{$primarySource['base_url']}/search", [
                'search_terms' => $query,
                'page' => $page,
                'page_size' => $pageSize,
                'json' => 1,
            ]);

            if (!$response->successful()) {
                return ['products' => [], 'total' => 0, 'page' => $page, 'page_count' => 0];
            }

            $data = $response->json();

            return [
                'products' => array_map(
                    fn (array $product): array => $this->transformProductData($product, $primarySource),
                    $data['products'] ?? []
                ),
                'total' => $data['count'] ?? 0,
                'page' => $data['page'] ?? $page,
                'page_count' => $data['page_count'] ?? 0,
            ];
        } catch (\Throwable $exception) {
            Log::error('Open Facts search error', [
                'query' => $query,
                'error' => $exception->getMessage(),
            ]);

            return ['products' => [], 'total' => 0, 'page' => $page, 'page_count' => 0];
        }
    }

    protected function fetchFromSource(array $source, string $barcode, array $settings): ?array
    {
        try {
            $response = $this->createHttpClient($settings)->get("{$source['base_url']}/product/{$barcode}.json");

            if (!$response->successful()) {
                return null;
            }

            $data = $response->json();

            if (($data['status'] ?? 0) !== 1 || !isset($data['product'])) {
                return null;
            }

            $productCode = (string) ($data['product']['code'] ?? '');

            if ($productCode !== $barcode) {
                Log::warning('Discarded non-exact barcode match from Open Facts', [
                    'requested_barcode' => $barcode,
                    'returned_barcode' => $productCode,
                    'source' => $source['key'] ?? 'unknown',
                ]);

                return null;
            }

            return $this->transformProductData($data['product'], $source);
        } catch (\Throwable $exception) {
            Log::error('Open Facts barcode lookup failed', [
                'barcode' => $barcode,
                'source' => $source['key'] ?? 'unknown',
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    protected function transformProductData(array $data, array $source): array
    {
        $ingredients = $this->extractIngredients($data);
        $nutrition = $this->extractNutrition($data);
        $packaging = $this->extractPackaging($data);
        $name = $this->resolveProductName($data);

        return [
            'barcode' => (string) ($data['code'] ?? ''),
            'name' => $name,
            'brand' => $data['brands'] ?? $data['brand_owner'] ?? null,
            'category_id' => null,
            'image_url' => $data['image_url'] ?? $data['image_front_url'] ?? $data['image_front_small_url'] ?? null,
            'source' => $source['key'] ?? 'open_facts',
            'product_type' => $source['family_hint'] ?? 'general',
            'ingredients' => $ingredients,
            'nutrition' => $nutrition,
            'packaging' => $packaging,
            'warnings' => $this->buildWarnings($ingredients, $nutrition, $packaging),
            'preliminary_score' => $this->calculatePreliminaryScore($ingredients, $nutrition, $packaging, $data),
            'raw_data' => $data,
        ];
    }

    protected function resolveProductName(array $data): string
    {
        foreach ([
            $data['product_name'] ?? null,
            $data['generic_name'] ?? null,
            $data['abbreviated_product_name'] ?? null,
        ] as $candidate) {
            $value = trim((string) $candidate);

            if ($value !== '') {
                return $value;
            }
        }

        return 'Unknown Product';
    }

    protected function createHttpClient(array $settings): PendingRequest
    {
        $httpClient = Http::timeout($settings['timeout'])->retry($settings['retry_attempts'], 100);

        return $this->verifySsl ? $httpClient : $httpClient->withoutVerifying();
    }

    protected function extractIngredients(array $data): array
    {
        $ingredients = [];

        if (isset($data['ingredients']) && is_array($data['ingredients'])) {
            foreach ($data['ingredients'] as $ingredient) {
                $name = $ingredient['text'] ?? $ingredient['id'] ?? null;

                if (!$name) {
                    continue;
                }

                $ingredients[] = [
                    'name' => trim((string) $name),
                    'percent' => $ingredient['percent'] ?? null,
                    'vegan' => $ingredient['vegan'] ?? null,
                    'vegetarian' => $ingredient['vegetarian'] ?? null,
                    'origin' => $ingredient['origin'] ?? null,
                ];
            }
        }

        if ($ingredients !== []) {
            return $ingredients;
        }

        $ingredientsText = $data['ingredients_text'] ?? $data['ingredients_text_en'] ?? null;

        if (!is_string($ingredientsText) || trim($ingredientsText) === '') {
            return [];
        }

        $parts = preg_split('/[,;]+/', $ingredientsText) ?: [];

        foreach ($parts as $part) {
            $name = trim($part);

            if ($name === '') {
                continue;
            }

            $ingredients[] = [
                'name' => $name,
                'percent' => null,
                'vegan' => null,
                'vegetarian' => null,
                'origin' => null,
            ];
        }

        return $ingredients;
    }

    protected function extractNutrition(array $data): array
    {
        $nutriments = $data['nutriments'] ?? [];

        return [
            'calories' => $nutriments['energy-kcal_100g'] ?? $nutriments['energy-kcal_100ml'] ?? null,
            'fat' => $nutriments['fat_100g'] ?? $nutriments['fat_100ml'] ?? null,
            'saturated_fat' => $nutriments['saturated-fat_100g'] ?? $nutriments['saturated-fat_100ml'] ?? null,
            'carbohydrates' => $nutriments['carbohydrates_100g'] ?? $nutriments['carbohydrates_100ml'] ?? null,
            'fiber' => $nutriments['fiber_100g'] ?? $nutriments['fiber_100ml'] ?? null,
            'sugars' => $nutriments['sugars_100g'] ?? $nutriments['sugars_100ml'] ?? null,
            'protein' => $nutriments['proteins_100g'] ?? $nutriments['proteins_100ml'] ?? null,
            'sodium' => $nutriments['sodium_100g'] ?? $nutriments['sodium_100ml'] ?? null,
            'serving_size' => $data['serving_size'] ?? null,
        ];
    }

    protected function extractPackaging(array $data): array
    {
        $description = $data['packaging'] ?? $data['packaging_text'] ?? null;
        $tags = array_map('strtolower', array_merge(
            $this->toStringArray($data['packaging_tags'] ?? []),
            $this->toStringArray($data['packagings_materials_tags'] ?? []),
            $this->toStringArray($data['packagings'] ?? [])
        ));

        $flatDescription = strtolower(is_string($description) ? $description : implode(' ', $this->toStringArray($description)));
        $searchSpace = trim(implode(' ', array_filter(array_merge($tags, [$flatDescription]))));

        $materials = [];

        if ($searchSpace !== '') {
            if (str_contains($searchSpace, 'plastic')) {
                $materials[] = 'plastic';
            }

            if (str_contains($searchSpace, 'glass')) {
                $materials[] = 'glass';
            }

            if (str_contains($searchSpace, 'aluminium') || str_contains($searchSpace, 'aluminum') || str_contains($searchSpace, 'metal')) {
                $materials[] = 'metal';
            }

            if (str_contains($searchSpace, 'paper') || str_contains($searchSpace, 'cardboard')) {
                $materials[] = 'paper';
            }
        }

        return [
            'description' => $description,
            'materials' => array_values(array_unique($materials)),
            'is_plastic' => in_array('plastic', $materials, true),
        ];
    }

    protected function buildWarnings(array $ingredients, array $nutrition, array $packaging): array
    {
        $warnings = [];

        if ($ingredients === [] && !$this->hasNutritionData($nutrition)) {
            $warnings[] = 'Vendor returned very limited product details.';
        }

        if (($packaging['is_plastic'] ?? false) === true) {
            $warnings[] = 'Plastic packaging detected.';
        }

        return $warnings;
    }

    protected function calculatePreliminaryScore(array $ingredients, array $nutrition, array $packaging, array $data): int
    {
        $score = 30;

        if ($ingredients !== []) {
            $score += 25;
        }

        if ($this->hasNutritionData($nutrition)) {
            $score += 25;
        }

        if (!empty($data['product_name'] ?? null)) {
            $score += 10;
        }

        if (!empty($data['brands'] ?? null)) {
            $score += 5;
        }

        if (!empty($data['image_url'] ?? null) || !empty($data['image_front_url'] ?? null)) {
            $score += 5;
        }

        if (!empty($packaging['materials'])) {
            $score += 5;
        }

        return min(100, $score);
    }

    protected function hasNutritionData(array $nutrition): bool
    {
        foreach ($nutrition as $value) {
            if ($value !== null && $value !== '') {
                return true;
            }
        }

        return false;
    }

    protected function toStringArray(mixed $value): array
    {
        if (is_string($value)) {
            return [$value];
        }

        if (!is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map(function (mixed $item): ?string {
            if (is_string($item)) {
                return $item;
            }

            if (is_array($item)) {
                return implode(' ', array_filter(array_map(
                    fn (mixed $nested): ?string => is_string($nested) ? $nested : null,
                    $item
                )));
            }

            return null;
        }, $value)));
    }

    protected function resolveSettings(array $settings = []): array
    {
        return [
            'sources' => $settings['sources'] ?? config('scanning.open_food_facts.sources', []),
            'timeout' => (int) ($settings['timeout'] ?? config('scanning.open_food_facts.timeout', 10)),
            'retry_attempts' => (int) ($settings['retry_attempts'] ?? config('scanning.open_food_facts.retry_attempts', 3)),
            'cache_ttl_minutes' => (int) ($settings['cache_ttl_minutes'] ?? 60 * 24 * 7),
        ];
    }
}
