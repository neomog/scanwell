<?php

namespace App\Services;

use App\Contracts\ProductCatalogProvider;
use App\Models\Scan;
use App\Models\ScanProvider;
use App\Models\ScanProviderLookup;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class ProductCatalogService
{
    protected array $lastLookupSummary = [
        'attempts' => [],
        'attempted_provider_keys' => [],
        'attempts_count' => 0,
        'resolved' => false,
    ];

    public function __construct(
        protected ProductFamilyResolver $productFamilyResolver
    ) {
    }

    public function findByBarcode(string $barcode, ?string $productFamily = null, ?Scan $scan = null): ?array
    {
        $candidates = [];
        $attempts = [];

        foreach ($this->activeProviders($productFamily) as $providerRecord) {
            $startedAt = microtime(true);
            $candidate = null;
            $error = null;
            $status = 'not_found';
            $driver = $this->resolveDriver($providerRecord);

            if ($driver === null) {
                $status = 'error';
                $error = 'Configured provider driver does not implement ProductCatalogProvider.';
            } else {
                try {
                    $candidate = $driver->findByBarcode(
                        $barcode,
                        $this->buildProviderSettings($providerRecord),
                        $providerRecord->credentials ?? []
                    );
                } catch (\Throwable $exception) {
                    $status = 'error';
                    $error = $exception->getMessage();

                    Log::error('Scan provider lookup failed', [
                        'provider_key' => $providerRecord->provider_key,
                        'barcode' => $barcode,
                        'error' => $exception->getMessage(),
                    ]);
                }
            }

            $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);

            if ($candidate === null) {
                $lookup = $this->storeLookupAttempt(
                    $providerRecord,
                    $scan,
                    $barcode,
                    $status,
                    false,
                    null,
                    $latencyMs,
                    $error
                );

                $attempts[] = $this->buildAttemptSummary($providerRecord, $lookup);
                continue;
            }

            $normalized = $this->normalizeCandidate($candidate, $barcode, $providerRecord->provider_key);

            if ($normalized !== null) {
                $lookup = $this->storeLookupAttempt(
                    $providerRecord,
                    $scan,
                    $barcode,
                    'success',
                    true,
                    $normalized['confidence'],
                    $latencyMs,
                    null,
                    [
                        'name' => $normalized['name'] ?? null,
                        'brand' => $normalized['brand'] ?? null,
                        'source' => $normalized['source'] ?? null,
                        'product_family' => $normalized['product_type'] ?? null,
                        'warnings' => $normalized['warnings'] ?? [],
                    ]
                );
                $attempts[] = $this->buildAttemptSummary($providerRecord, $lookup);
                $candidates[] = $normalized;
                $this->markProviderSuccess($providerRecord);
            } else {
                $lookup = $this->storeLookupAttempt(
                    $providerRecord,
                    $scan,
                    $barcode,
                    'rejected',
                    false,
                    null,
                    $latencyMs,
                    'Provider returned a payload that failed Scanwell normalization checks.'
                );
                $attempts[] = $this->buildAttemptSummary($providerRecord, $lookup);
            }
        }

        $this->lastLookupSummary = [
            'attempts' => $attempts,
            'attempted_provider_keys' => array_values(array_map(
                fn (array $attempt): string => $attempt['provider_key'],
                $attempts
            )),
            'attempts_count' => count($attempts),
            'resolved' => false,
        ];

        if ($candidates === []) {
            return null;
        }

        usort($candidates, function (array $left, array $right): int {
            return [$right['confidence'], $right['completeness']]
                <=> [$left['confidence'], $left['completeness']];
        });

        $bestCandidate = $candidates[0];
        $bestCandidate['lookup_summary'] = $this->lastLookupSummary;

        if ($bestCandidate['confidence'] < config('scanning.min_candidate_confidence', 45)) {
            return null;
        }

        $this->lastLookupSummary['resolved'] = true;
        $bestCandidate['lookup_summary'] = $this->lastLookupSummary;

        return $bestCandidate;
    }

    protected function normalizeCandidate(array $candidate, string $barcode, string $providerKey): ?array
    {
        if (($candidate['barcode'] ?? null) !== $barcode) {
            return null;
        }

        $family = $this->productFamilyResolver->resolveFromNormalized($candidate);
        $completeness = $this->calculateCompleteness($candidate);
        $warnings = $candidate['warnings'] ?? [];

        if ($completeness < 35) {
            $warnings[] = 'Limited vendor data was returned for this barcode.';
        }

        return array_merge($candidate, [
            'provider' => $providerKey,
            'product_type' => $family,
            'category_id' => $candidate['category_id'] ?? $this->productFamilyResolver->categoryIdForFamily($family, $candidate),
            'completeness' => $completeness,
            'confidence' => $this->calculateConfidence($candidate, $completeness),
            'warnings' => array_values(array_unique(array_filter($warnings))),
        ]);
    }

    protected function calculateCompleteness(array $candidate): int
    {
        $score = 0;

        if (!empty($candidate['name']) && $candidate['name'] !== 'Unknown Product') {
            $score += 20;
        }

        if (!empty($candidate['brand'])) {
            $score += 10;
        }

        if (!empty($candidate['image_url'])) {
            $score += 10;
        }

        if (!empty($candidate['ingredients'])) {
            $score += 20;
        }

        if ($this->hasNutritionData($candidate)) {
            $score += 20;
        }

        if (!empty(data_get($candidate, 'raw_data.categories')) || !empty(data_get($candidate, 'raw_data.categories_tags'))) {
            $score += 10;
        }

        if (!empty(data_get($candidate, 'packaging.materials'))) {
            $score += 10;
        }

        return min(100, $score);
    }

    protected function calculateConfidence(array $candidate, int $completeness): int
    {
        $confidence = 55;

        if (!empty($candidate['image_url'])) {
            $confidence += 5;
        }

        if (!empty($candidate['ingredients'])) {
            $confidence += 5;
        }

        if ($this->hasNutritionData($candidate)) {
            $confidence += 5;
        }

        if (($candidate['product_type'] ?? null) !== ProductFamilyResolver::GENERAL) {
            $confidence += 5;
        }

        $confidence += (int) round($completeness / 4);

        return min(100, $confidence);
    }

    protected function hasNutritionData(array $candidate): bool
    {
        $nutrition = $candidate['nutrition'] ?? [];

        foreach ($nutrition as $value) {
            if ($value !== null && $value !== '') {
                return true;
            }
        }

        return false;
    }

    public function lastLookupSummary(): array
    {
        return $this->lastLookupSummary;
    }

    protected function activeProviders(?string $productFamily = null): Collection
    {
        if (!class_exists(ScanProvider::class) || !\Illuminate\Support\Facades\Schema::hasTable('scan_providers')) {
            return collect(config('scanning.providers', []))
                ->map(fn (string $driverClass, int $index) => new ScanProvider([
                    'name' => class_basename($driverClass),
                    'provider_key' => app($driverClass)->providerKey(),
                    'driver' => $driverClass,
                    'is_active' => true,
                    'priority' => ($index + 1) * 10,
                    'supported_families' => [],
                    'settings' => [],
                    'credentials' => [],
                    'timeout_seconds' => 10,
                    'retry_attempts' => 3,
                    'cache_ttl_minutes' => 10080,
                    'health_status' => 'unknown',
                ]));
        }

        return ScanProvider::query()
            ->where('is_active', true)
            ->orderBy('priority')
            ->orderBy('name')
            ->get()
            ->filter(fn (ScanProvider $provider): bool => $provider->supportsFamily($productFamily))
            ->values();
    }

    protected function resolveDriver(ScanProvider $providerRecord): ?ProductCatalogProvider
    {
        $driver = app($providerRecord->driver);

        return $driver instanceof ProductCatalogProvider ? $driver : null;
    }

    protected function buildProviderSettings(ScanProvider $providerRecord): array
    {
        return array_merge($providerRecord->settings ?? [], [
            'timeout' => $providerRecord->timeout_seconds,
            'retry_attempts' => $providerRecord->retry_attempts,
            'cache_ttl_minutes' => $providerRecord->cache_ttl_minutes,
        ]);
    }

    protected function storeLookupAttempt(
        ScanProvider $providerRecord,
        ?Scan $scan,
        string $barcode,
        string $status,
        bool $matched,
        ?int $confidence,
        ?int $latencyMs,
        ?string $error = null,
        ?array $responseSummary = null
    ): ScanProviderLookup {
        $lookup = ScanProviderLookup::create([
            'scan_provider_id' => $providerRecord->id,
            'scan_id' => $scan?->id,
            'barcode' => $barcode,
            'status' => $status,
            'matched' => $matched,
            'product_family' => $responseSummary['product_family'] ?? null,
            'confidence' => $confidence,
            'latency_ms' => $latencyMs,
            'error_message' => $error,
            'response_summary' => $responseSummary,
        ]);

        if ($status === 'error') {
            $this->markProviderFailure($providerRecord, $error);
        }

        return $lookup;
    }

    protected function buildAttemptSummary(ScanProvider $providerRecord, ScanProviderLookup $lookup): array
    {
        return [
            'provider_key' => $providerRecord->provider_key,
            'provider_name' => $providerRecord->name,
            'status' => $lookup->status,
            'matched' => $lookup->matched,
            'confidence' => $lookup->confidence,
            'latency_ms' => $lookup->latency_ms,
            'error' => $lookup->error_message,
        ];
    }

    protected function markProviderSuccess(ScanProvider $providerRecord): void
    {
        if (!$providerRecord->exists) {
            return;
        }

        $providerRecord->forceFill([
            'health_status' => 'healthy',
            'last_success_at' => now(),
            'last_error' => null,
        ])->save();
    }

    protected function markProviderFailure(ScanProvider $providerRecord, ?string $error): void
    {
        if (!$providerRecord->exists) {
            return;
        }

        $providerRecord->forceFill([
            'health_status' => 'degraded',
            'last_failure_at' => now(),
            'last_error' => $error,
        ])->save();
    }
}
