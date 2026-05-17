<?php

namespace App\Services;

use App\Contracts\ProductCatalogProvider;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class UpcItemDbService implements ProductCatalogProvider
{
    protected bool $verifySsl;

    public function __construct()
    {
        $this->verifySsl = App::environment('production');
    }

    public function providerKey(): string
    {
        return 'upcitemdb';
    }

    public function findByBarcode(string $barcode, array $settings = [], array $credentials = []): ?array
    {
        $resolved = $this->resolveSettings($settings, $credentials);

        if ($resolved['mode'] === 'prod' && blank($resolved['user_key'])) {
            return null;
        }

        $cacheKey = "scanwell:upcitemdb:{$resolved['mode']}:{$barcode}";

        return Cache::remember($cacheKey, now()->addMinutes($resolved['cache_ttl_minutes']), function () use ($barcode, $resolved) {
            try {
                $response = $this->createHttpClient($resolved)
                    ->get($resolved['base_url'], ['upc' => $barcode]);

                if (in_array($response->status(), [404, 400], true)) {
                    return null;
                }

                if (!$response->successful()) {
                    Log::warning('UPCitemdb request failed', [
                        'barcode' => $barcode,
                        'status' => $response->status(),
                        'body' => $response->body(),
                    ]);

                    return null;
                }

                $payload = $response->json();
                $item = collect($payload['items'] ?? [])->first();

                if (!is_array($item)) {
                    return null;
                }

                $resolvedBarcode = (string) ($item['upc'] ?? $item['ean'] ?? '');

                if ($resolvedBarcode !== $barcode) {
                    return null;
                }

                return $this->transformProduct($item);
            } catch (\Throwable $exception) {
                Log::error('UPCitemdb integration failed', [
                    'barcode' => $barcode,
                    'error' => $exception->getMessage(),
                ]);

                return null;
            }
        });
    }

    protected function transformProduct(array $item): array
    {
        $images = array_values(array_filter(array_map('strval', $item['images'] ?? [])));

        return [
            'barcode' => (string) ($item['upc'] ?? $item['ean'] ?? ''),
            'name' => $item['title'] ?? 'Unknown Product',
            'brand' => $item['brand'] ?? null,
            'category_id' => null,
            'image_url' => $images[0] ?? null,
            'source' => 'upcitemdb',
            'product_type' => $this->inferProductType($item),
            'ingredients' => [],
            'nutrition' => [],
            'packaging' => [
                'description' => null,
                'materials' => [],
                'is_plastic' => false,
            ],
            'warnings' => [],
            'raw_data' => $item,
        ];
    }

    protected function inferProductType(array $item): string
    {
        $category = strtolower((string) ($item['category'] ?? ''));
        $title = strtolower((string) ($item['title'] ?? ''));

        return match (true) {
            str_contains($category, 'beauty'), str_contains($category, 'personal care'), str_contains($title, 'conditioner') => 'cosmetic',
            str_contains($category, 'pet') => 'pet_food',
            str_contains($category, 'food'), str_contains($category, 'grocery'), str_contains($category, 'beverage') => 'food',
            str_contains($category, 'household'), str_contains($category, 'cleaning') => 'household',
            default => 'general',
        };
    }

    protected function createHttpClient(array $settings): PendingRequest
    {
        $headers = [
            'Accept' => 'application/json',
            'Accept-Encoding' => 'gzip,deflate',
        ];

        if ($settings['mode'] === 'prod') {
            $headers['user_key'] = $settings['user_key'];
            $headers['key_type'] = $settings['key_type'];
        }

        $client = Http::withHeaders($headers)->timeout($settings['timeout'])->retry($settings['retry_attempts'], 200);

        return $this->verifySsl ? $client : $client->withoutVerifying();
    }

    protected function resolveSettings(array $settings, array $credentials): array
    {
        $mode = (string) ($settings['mode'] ?? config('services.upcitemdb.mode', 'trial'));
        $baseUrl = $mode === 'prod'
            ? config('services.upcitemdb.prod_base_url', 'https://api.upcitemdb.com/prod/v1/lookup')
            : config('services.upcitemdb.trial_base_url', 'https://api.upcitemdb.com/prod/trial/lookup');

        return [
            'mode' => $mode,
            'base_url' => (string) ($settings['base_url'] ?? $baseUrl),
            'user_key' => $credentials['user_key'] ?? config('services.upcitemdb.user_key'),
            'key_type' => (string) ($settings['key_type'] ?? config('services.upcitemdb.key_type', '3scale')),
            'timeout' => (int) ($settings['timeout'] ?? config('services.upcitemdb.timeout', 10)),
            'retry_attempts' => (int) ($settings['retry_attempts'] ?? config('services.upcitemdb.retry_attempts', 1)),
            'cache_ttl_minutes' => (int) ($settings['cache_ttl_minutes'] ?? 720),
        ];
    }
}
