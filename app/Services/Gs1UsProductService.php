<?php

namespace App\Services;

use App\Contracts\ProductCatalogProvider;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class Gs1UsProductService implements ProductCatalogProvider
{
    protected bool $verifySsl;

    public function __construct()
    {
        $this->verifySsl = App::environment('production');
    }

    public function providerKey(): string
    {
        return 'gs1_us';
    }

    public function findByBarcode(string $barcode, array $settings = [], array $credentials = []): ?array
    {
        $resolved = $this->resolveSettings($settings, $credentials);

        if (blank($resolved['base_url']) || blank($resolved['api_key'])) {
            return null;
        }

        $cacheKey = "scanwell:gs1us:{$barcode}";

        return Cache::remember($cacheKey, now()->addMinutes($resolved['cache_ttl_minutes']), function () use ($barcode, $resolved) {
            try {
                $request = $this->createHttpClient($resolved);
                $response = $resolved['http_method'] === 'POST'
                    ? $request->post($resolved['base_url'], [$resolved['barcode_field'] => $barcode])
                    : $request->get($resolved['base_url'], [$resolved['barcode_field'] => $barcode]);

                if (!$response->successful()) {
                    Log::warning('GS1 US lookup failed', [
                        'barcode' => $barcode,
                        'status' => $response->status(),
                        'body' => $response->body(),
                    ]);

                    return null;
                }

                $payload = $response->json();
                $record = $this->extractRecord($payload);

                if (!is_array($record)) {
                    return null;
                }

                $resolvedBarcode = (string) data_get(
                    $record,
                    $resolved['barcode_path'],
                    data_get($record, 'gtin', data_get($record, 'GTIN', ''))
                );

                if ($resolvedBarcode !== $barcode) {
                    return null;
                }

                return $this->transformProduct($record, $payload, $barcode);
            } catch (\Throwable $exception) {
                Log::error('GS1 US integration failed', [
                    'barcode' => $barcode,
                    'error' => $exception->getMessage(),
                ]);

                return null;
            }
        });
    }

    protected function extractRecord(array $payload): ?array
    {
        foreach (['items', 'data', 'results', 'value'] as $field) {
            $value = $payload[$field] ?? null;

            if (is_array($value) && is_array($value[0] ?? null)) {
                return $value[0];
            }
        }

        return is_array($payload) ? $payload : null;
    }

    protected function transformProduct(array $record, array $payload, string $barcode): array
    {
        $brand = data_get($record, 'brandName')
            ?? data_get($record, 'brand')
            ?? data_get($record, 'ownerName')
            ?? data_get($record, 'companyName');

        return [
            'barcode' => $barcode,
            'name' => data_get($record, 'description')
                ?? data_get($record, 'productDescription')
                ?? data_get($record, 'productName')
                ?? 'Unknown Product',
            'brand' => is_string($brand) ? $brand : null,
            'category_id' => null,
            'image_url' => data_get($record, 'imageUrl'),
            'source' => 'gs1_us',
            'product_type' => 'general',
            'ingredients' => [],
            'nutrition' => [],
            'packaging' => [
                'description' => null,
                'materials' => [],
                'is_plastic' => false,
            ],
            'warnings' => [],
            'raw_data' => [
                'record' => $record,
                'payload' => $payload,
            ],
        ];
    }

    protected function createHttpClient(array $settings): PendingRequest
    {
        $headers = [
            'Accept' => 'application/json',
            'APIKey' => $settings['api_key'],
        ];

        if (filled($settings['account_id'])) {
            $headers['X-Product-Owner-Account-Id'] = $settings['account_id'];
        }

        $client = Http::withHeaders($headers)->timeout($settings['timeout'])->retry($settings['retry_attempts'], 250);

        return $this->verifySsl ? $client : $client->withoutVerifying();
    }

    protected function resolveSettings(array $settings, array $credentials): array
    {
        return [
            'base_url' => (string) ($settings['base_url'] ?? config('services.gs1_us.base_url', '')),
            'api_key' => $credentials['api_key'] ?? config('services.gs1_us.api_key'),
            'account_id' => $credentials['account_id'] ?? config('services.gs1_us.account_id'),
            'http_method' => strtoupper((string) ($settings['http_method'] ?? config('services.gs1_us.http_method', 'GET'))),
            'barcode_field' => (string) ($settings['barcode_field'] ?? config('services.gs1_us.barcode_field', 'gtin')),
            'barcode_path' => (string) ($settings['barcode_path'] ?? config('services.gs1_us.barcode_path', 'gtin')),
            'timeout' => (int) ($settings['timeout'] ?? config('services.gs1_us.timeout', 12)),
            'retry_attempts' => (int) ($settings['retry_attempts'] ?? config('services.gs1_us.retry_attempts', 1)),
            'cache_ttl_minutes' => (int) ($settings['cache_ttl_minutes'] ?? 1440),
        ];
    }
}
