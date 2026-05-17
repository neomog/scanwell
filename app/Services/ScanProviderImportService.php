<?php

namespace App\Services;

use App\Contracts\ProductCatalogImportProvider;
use App\Models\ScanProvider;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class ScanProviderImportService
{
    public function __construct(
        protected ProductWorkflowService $productWorkflowService
    ) {
    }

    public function supportsImport(ScanProvider $provider): bool
    {
        return $this->resolveDriver($provider) instanceof ProductCatalogImportProvider;
    }

    public function import(ScanProvider $provider, ?User $actor = null): array
    {
        $driver = $this->resolveDriver($provider);

        if (!$driver instanceof ProductCatalogImportProvider) {
            throw new RuntimeException("{$provider->name} does not support catalog imports.");
        }

        $config = $this->resolveImportConfig($provider);
        $seenBarcodes = [];
        $created = 0;
        $updated = 0;
        $skipped = 0;

        for ($page = 1; $page <= $config['max_pages']; $page++) {
            $result = $driver->searchProducts(
                $config['query'],
                $page,
                $config['page_size'],
                $this->buildProviderSettings($provider),
                $provider->credentials ?? []
            );

            $products = collect($result['products'] ?? [])
                ->filter(fn ($product) => is_array($product))
                ->values();

            if ($products->isEmpty()) {
                break;
            }

            foreach ($products as $candidate) {
                try {
                    $payload = $this->buildPayload($candidate, $provider, $config, $page);
                    $barcode = trim((string) ($payload['barcode'] ?? ''));

                    if ($barcode === '' || isset($seenBarcodes[$barcode])) {
                        $skipped++;
                        continue;
                    }

                    if (!$this->isImportablePayload($payload, $provider, $page)) {
                        $seenBarcodes[$barcode] = true;
                        $skipped++;
                        continue;
                    }

                    $seenBarcodes[$barcode] = true;
                    $existing = $this->productWorkflowService->findByBarcode($barcode);

                    if ($existing) {
                        $this->productWorkflowService->updateProduct($existing, $payload, [
                            'actor' => $actor,
                            'source' => $provider->provider_key,
                            'mark_approved' => true,
                            'audit_action' => 'provider_import_updated',
                            'audit_description' => "Product updated from {$provider->name} import",
                            'audit_metadata' => [
                                'provider_key' => $provider->provider_key,
                                'import_query' => $config['query'],
                                'import_page' => $page,
                            ],
                        ]);
                        $updated++;
                        continue;
                    }

                    $this->productWorkflowService->createProduct($payload, [
                        'actor' => $actor,
                        'source' => $provider->provider_key,
                        'mark_approved' => true,
                        'audit_action' => 'provider_import_created',
                        'audit_description' => "Product created from {$provider->name} import",
                        'audit_metadata' => [
                            'provider_key' => $provider->provider_key,
                            'import_query' => $config['query'],
                            'import_page' => $page,
                        ],
                    ]);
                    $created++;
                } catch (\Throwable $exception) {
                    Log::warning('Skipping provider import product after processing failure', [
                        'provider_key' => $provider->provider_key,
                        'import_page' => $page,
                        'barcode' => $barcode ?? null,
                        'error' => $exception->getMessage(),
                    ]);

                    $skipped++;
                }
            }

            if ($products->count() < $config['page_size']) {
                break;
            }
        }

        $provider->forceFill([
            'health_status' => 'healthy',
            'last_success_at' => now(),
            'last_error' => null,
        ])->save();

        return [
            'provider_key' => $provider->provider_key,
            'query' => $config['query'],
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'processed' => $created + $updated + $skipped,
        ];
    }

    public function importConfigSummary(ScanProvider $provider): ?array
    {
        $query = trim((string) data_get($provider->settings, 'import.query', ''));

        if ($query === '') {
            return null;
        }

        return [
            'query' => $query,
            'page_size' => max(1, min(100, (int) data_get($provider->settings, 'import.page_size', 20))),
            'max_pages' => max(1, min(20, (int) data_get($provider->settings, 'import.max_pages', 1))),
        ];
    }

    protected function resolveImportConfig(ScanProvider $provider): array
    {
        $summary = $this->importConfigSummary($provider);

        if ($summary === null) {
            throw new RuntimeException("Set settings.import.query for {$provider->name} before running sync.");
        }

        return $summary;
    }

    protected function resolveDriver(ScanProvider $provider): mixed
    {
        return app($provider->driver);
    }

    protected function buildProviderSettings(ScanProvider $provider): array
    {
        return array_merge($provider->settings ?? [], [
            'timeout' => $provider->timeout_seconds,
            'retry_attempts' => $provider->retry_attempts,
            'cache_ttl_minutes' => $provider->cache_ttl_minutes,
        ]);
    }

    protected function buildPayload(array $candidate, ScanProvider $provider, array $config, int $page): array
    {
        $rawData = is_array($candidate['raw_data'] ?? null) ? $candidate['raw_data'] : [];
        $rawData['_scanwell'] = array_merge($rawData['_scanwell'] ?? [], [
            'provider' => $provider->provider_key,
            'import' => [
                'query' => $config['query'],
                'page' => $page,
                'synced_at' => now()->toIso8601String(),
            ],
        ]);

        $candidate['barcode'] = trim((string) ($candidate['barcode'] ?? data_get($rawData, 'code', '')));
        $candidate['name'] = $this->resolveCandidateName($candidate, $rawData);
        $candidate['source'] = $candidate['source'] ?? $provider->provider_key;
        $candidate['raw_data'] = $rawData;

        return $candidate;
    }

    protected function resolveCandidateName(array $candidate, array $rawData): ?string
    {
        foreach ([
            $candidate['name'] ?? null,
            $candidate['product_name'] ?? null,
            data_get($rawData, 'product_name'),
            data_get($rawData, 'generic_name'),
            data_get($rawData, 'abbreviated_product_name'),
        ] as $value) {
            $resolved = trim((string) $value);

            if ($resolved !== '') {
                return $resolved;
            }
        }

        return null;
    }

    protected function isImportablePayload(array $payload, ScanProvider $provider, int $page): bool
    {
        $barcode = trim((string) ($payload['barcode'] ?? ''));
        $name = trim((string) ($payload['name'] ?? ''));

        if ($barcode !== '' && $name !== '') {
            return true;
        }

        Log::warning('Skipping invalid provider import product', [
            'provider_key' => $provider->provider_key,
            'import_page' => $page,
            'barcode' => $barcode !== '' ? $barcode : null,
            'has_name' => $name !== '',
        ]);

        return false;
    }
}
