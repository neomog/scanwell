<?php

namespace App\Services;

use App\Contracts\ProductCatalogProvider;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EdamamFoodDatabaseService implements ProductCatalogProvider
{
    protected bool $verifySsl;

    public function __construct()
    {
        $this->verifySsl = App::environment('production');
    }

    public function providerKey(): string
    {
        return 'edamam';
    }

    public function findByBarcode(string $barcode, array $settings = [], array $credentials = []): ?array
    {
        $resolved = $this->resolveSettings($settings, $credentials);

        if (blank($resolved['app_id']) || blank($resolved['app_key'])) {
            return null;
        }

        $cacheKey = "scanwell:edamam:{$barcode}";

        return Cache::remember($cacheKey, now()->addMinutes($resolved['cache_ttl_minutes']), function () use ($barcode, $resolved) {
            try {
                $response = $this->createHttpClient($resolved)->get($resolved['base_url'], [
                    'upc' => $barcode,
                    'app_id' => $resolved['app_id'],
                    'app_key' => $resolved['app_key'],
                    'category' => $resolved['category'],
                    'nutrition-type' => $resolved['nutrition_type'],
                ]);

                if (!$response->successful()) {
                    Log::warning('Edamam lookup failed', [
                        'barcode' => $barcode,
                        'status' => $response->status(),
                        'body' => $response->body(),
                    ]);

                    return null;
                }

                $payload = $response->json();
                $candidate = $this->selectCandidate($payload, $barcode);

                if ($candidate === null) {
                    return null;
                }

                return $this->transformProduct($barcode, $candidate, $payload);
            } catch (\Throwable $exception) {
                Log::error('Edamam integration failed', [
                    'barcode' => $barcode,
                    'error' => $exception->getMessage(),
                ]);

                return null;
            }
        });
    }

    protected function selectCandidate(array $payload, string $barcode): ?array
    {
        foreach ($payload['hints'] ?? [] as $hint) {
            if (!is_array($hint)) {
                continue;
            }

            $candidateBarcode = (string) data_get($hint, 'food.foodIdProperties.upc', data_get($hint, 'food.upc', ''));

            if ($candidateBarcode === $barcode) {
                return $hint;
            }
        }

        $parsed = $payload['parsed'] ?? [];

        return is_array($parsed[0] ?? null) ? $parsed[0] : null;
    }

    protected function transformProduct(string $barcode, array $hint, array $payload): array
    {
        $food = $hint['food'] ?? [];
        $measures = $hint['measures'] ?? [];

        return [
            'barcode' => $barcode,
            'name' => $food['label'] ?? 'Unknown Product',
            'brand' => $food['brand'] ?? null,
            'category_id' => null,
            'image_url' => $food['image'] ?? null,
            'source' => 'edamam',
            'product_type' => 'food',
            'ingredients' => $this->extractIngredients($food),
            'nutrition' => $this->extractNutrition($food),
            'packaging' => [
                'description' => null,
                'materials' => [],
                'is_plastic' => false,
            ],
            'warnings' => $this->extractWarnings($food),
            'raw_data' => [
                'hint' => $hint,
                'parsed' => $payload['parsed'] ?? [],
                'measures' => $measures,
            ],
        ];
    }

    protected function extractIngredients(array $food): array
    {
        $ingredients = $food['ingredients'] ?? [];

        if (!is_array($ingredients)) {
            return [];
        }

        return collect($ingredients)
            ->map(function ($ingredient): ?array {
                $name = is_array($ingredient)
                    ? ($ingredient['text'] ?? $ingredient['food'] ?? null)
                    : (is_string($ingredient) ? $ingredient : null);

                return filled($name) ? ['name' => trim((string) $name)] : null;
            })
            ->filter()
            ->values()
            ->all();
    }

    protected function extractNutrition(array $food): array
    {
        $nutrients = $food['nutrients'] ?? [];

        return array_filter([
            'calories' => $nutrients['ENERC_KCAL'] ?? null,
            'fat' => $nutrients['FAT'] ?? null,
            'saturated_fat' => $nutrients['FASAT'] ?? null,
            'carbohydrates' => $nutrients['CHOCDF'] ?? null,
            'fiber' => $nutrients['FIBTG'] ?? null,
            'sugars' => $nutrients['SUGAR'] ?? null,
            'protein' => $nutrients['PROCNT'] ?? null,
            'sodium' => $nutrients['NA'] ?? null,
        ], fn ($value) => $value !== null && $value !== '');
    }

    protected function extractWarnings(array $food): array
    {
        $warnings = [];

        foreach (['healthLabels', 'cautions', 'dietLabels'] as $field) {
            foreach ($food[$field] ?? [] as $value) {
                if (is_string($value) && $value !== '') {
                    $warnings[] = $value;
                }
            }
        }

        return array_values(array_unique($warnings));
    }

    protected function createHttpClient(array $settings): PendingRequest
    {
        $client = Http::acceptJson()->timeout($settings['timeout'])->retry($settings['retry_attempts'], 200);

        return $this->verifySsl ? $client : $client->withoutVerifying();
    }

    protected function resolveSettings(array $settings, array $credentials): array
    {
        return [
            'base_url' => (string) ($settings['base_url'] ?? config('services.edamam.base_url', 'https://api.edamam.com/api/food-database/v2/parser')),
            'app_id' => $credentials['app_id'] ?? config('services.edamam.app_id'),
            'app_key' => $credentials['app_key'] ?? config('services.edamam.app_key'),
            'category' => (string) ($settings['category'] ?? config('services.edamam.category', 'packaged-foods')),
            'nutrition_type' => (string) ($settings['nutrition_type'] ?? config('services.edamam.nutrition_type', 'cooking')),
            'timeout' => (int) ($settings['timeout'] ?? config('services.edamam.timeout', 10)),
            'retry_attempts' => (int) ($settings['retry_attempts'] ?? config('services.edamam.retry_attempts', 1)),
            'cache_ttl_minutes' => (int) ($settings['cache_ttl_minutes'] ?? 1440),
        ];
    }
}
