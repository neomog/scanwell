<?php

namespace App\Services;

use App\Contracts\ProductCatalogProvider;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BarcodeLookupService implements ProductCatalogProvider
{
    protected bool $verifySsl;

    public function __construct()
    {
        $this->verifySsl = App::environment('production');
    }

    public function providerKey(): string
    {
        return 'barcode_lookup';
    }

    public function findByBarcode(string $barcode, array $settings = [], array $credentials = []): ?array
    {
        $resolved = $this->resolveSettings($settings, $credentials);

        if (blank($resolved['api_key'])) {
            return null;
        }

        $cacheKey = "scanwell:barcode_lookup:{$barcode}";

        return Cache::remember($cacheKey, now()->addMinutes($resolved['cache_ttl_minutes']), function () use ($barcode, $resolved) {
            try {
                $response = $this->createHttpClient($resolved)->get($resolved['base_url'], [
                    'barcode' => $barcode,
                    'formatted' => 'n',
                    'key' => $resolved['api_key'],
                ]);

                if ($response->status() === 404) {
                    return null;
                }

                if (!$response->successful()) {
                    Log::warning('Barcode Lookup request failed', [
                        'barcode' => $barcode,
                        'status' => $response->status(),
                        'body' => $response->body(),
                    ]);

                    return null;
                }

                $payload = $response->json();
                $product = collect($payload['products'] ?? [])->first();

                if (!is_array($product)) {
                    return null;
                }

                $resolvedBarcode = (string) ($product['barcode_number'] ?? $product['barcode'] ?? '');

                if ($resolvedBarcode !== $barcode) {
                    return null;
                }

                return $this->transformProduct($product);
            } catch (\Throwable $exception) {
                Log::error('Barcode Lookup integration failed', [
                    'barcode' => $barcode,
                    'error' => $exception->getMessage(),
                ]);

                return null;
            }
        });
    }

    protected function transformProduct(array $product): array
    {
        $ingredients = $this->extractIngredients($product);
        $nutrition = $this->extractNutrition($product);
        $images = array_values(array_filter(array_map('strval', $product['images'] ?? [])));

        return [
            'barcode' => (string) ($product['barcode_number'] ?? ''),
            'name' => $product['title'] ?? $product['product_name'] ?? 'Unknown Product',
            'brand' => $product['brand'] ?? $product['manufacturer'] ?? null,
            'category_id' => null,
            'image_url' => $images[0] ?? null,
            'source' => 'barcode_lookup',
            'product_type' => $this->inferProductType($product),
            'ingredients' => $ingredients,
            'nutrition' => $nutrition,
            'packaging' => [
                'description' => null,
                'materials' => [],
                'is_plastic' => false,
            ],
            'warnings' => [],
            'raw_data' => $product,
        ];
    }

    protected function extractIngredients(array $product): array
    {
        $ingredientsText = $product['ingredients'] ?? null;

        if (!is_string($ingredientsText) || trim($ingredientsText) === '') {
            return [];
        }

        return collect(preg_split('/[,;]+/', $ingredientsText) ?: [])
            ->map(fn (string $item): array => ['name' => trim($item)])
            ->filter(fn (array $item): bool => $item['name'] !== '')
            ->values()
            ->all();
    }

    protected function extractNutrition(array $product): array
    {
        $nutritionText = $product['nutrition_facts'] ?? null;

        if (!is_string($nutritionText) || trim($nutritionText) === '') {
            return [];
        }

        $map = [
            'calories' => '/calories?\s*([0-9.]+)/i',
            'fat' => '/fat\s*([0-9.]+)/i',
            'saturated_fat' => '/saturated\s+fat\s*([0-9.]+)/i',
            'carbohydrates' => '/carbohydrates?\s*([0-9.]+)/i',
            'fiber' => '/fiber\s*([0-9.]+)/i',
            'sugars' => '/sugars?\s*([0-9.]+)/i',
            'protein' => '/protein\s*([0-9.]+)/i',
            'sodium' => '/sodium\s*([0-9.]+)/i',
        ];

        $nutrition = [];

        foreach ($map as $field => $pattern) {
            if (preg_match($pattern, $nutritionText, $matches) === 1) {
                $nutrition[$field] = (float) $matches[1];
            }
        }

        return $nutrition;
    }

    protected function inferProductType(array $product): string
    {
        $category = strtolower((string) ($product['category'] ?? ''));
        $title = strtolower((string) ($product['title'] ?? ''));

        if (str_contains($category, 'beauty') || str_contains($category, 'personal care') || str_contains($title, 'shampoo')) {
            return 'cosmetic';
        }

        if (str_contains($category, 'pet')) {
            return 'pet_food';
        }

        if (str_contains($category, 'food') || str_contains($category, 'beverage') || str_contains($category, 'grocery')) {
            return 'food';
        }

        if (str_contains($category, 'household') || str_contains($category, 'cleaning')) {
            return 'household';
        }

        return 'general';
    }

    protected function createHttpClient(array $settings): PendingRequest
    {
        $client = Http::acceptJson()->timeout($settings['timeout'])->retry($settings['retry_attempts'], 150);

        return $this->verifySsl ? $client : $client->withoutVerifying();
    }

    protected function resolveSettings(array $settings, array $credentials): array
    {
        return [
            'base_url' => rtrim((string) ($settings['base_url'] ?? config('services.barcode_lookup.base_url', 'https://api.barcodelookup.com/v3/products')), '/'),
            'api_key' => $credentials['api_key'] ?? config('services.barcode_lookup.api_key'),
            'timeout' => (int) ($settings['timeout'] ?? config('services.barcode_lookup.timeout', 10)),
            'retry_attempts' => (int) ($settings['retry_attempts'] ?? config('services.barcode_lookup.retry_attempts', 1)),
            'cache_ttl_minutes' => (int) ($settings['cache_ttl_minutes'] ?? 1440),
        ];
    }
}
