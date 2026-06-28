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
                        'completeness' => $normalized['completeness'] ?? null,
                        'has_ingredients' => !empty($normalized['ingredients']),
                        'has_nutrition' => $this->hasNutritionData($normalized),
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

        $bestCandidate = $this->enrichCandidate($candidates[0], array_slice($candidates, 1));
        $bestCandidate['lookup_summary'] = $this->lastLookupSummary;

        if ($bestCandidate['confidence'] < config('scanning.min_candidate_confidence', 45)) {
            return null;
        }

        $this->lastLookupSummary['resolved'] = true;
        $bestCandidate['lookup_summary'] = $this->lastLookupSummary;

        return $bestCandidate;
    }

    protected function enrichCandidate(array $primary, array $others): array
    {
        $enriched = $primary;
        $contributingSources = array_values(array_unique(array_filter([
            $primary['source'] ?? null,
        ])));

        foreach ($others as $candidate) {
            if (empty($enriched['ingredients']) && !empty($candidate['ingredients'])) {
                $enriched['ingredients'] = $candidate['ingredients'];
                $contributingSources[] = $candidate['source'] ?? null;
            }

            if (!$this->hasNutritionData($enriched) && $this->hasNutritionData($candidate)) {
                $enriched['nutrition'] = $candidate['nutrition'] ?? [];
                $contributingSources[] = $candidate['source'] ?? null;
            }

            if (empty($enriched['image_url']) && !empty($candidate['image_url'])) {
                $enriched['image_url'] = $candidate['image_url'];
                $contributingSources[] = $candidate['source'] ?? null;
            }

            if (empty($enriched['brand']) && !empty($candidate['brand'])) {
                $enriched['brand'] = $candidate['brand'];
                $contributingSources[] = $candidate['source'] ?? null;
            }

            if (
                (empty($enriched['name']) || $enriched['name'] === 'Unknown Product')
                && !empty($candidate['name'])
                && $candidate['name'] !== 'Unknown Product'
            ) {
                $enriched['name'] = $candidate['name'];
                $contributingSources[] = $candidate['source'] ?? null;
            }

            if (empty(data_get($enriched, 'raw_data.categories')) && !empty(data_get($candidate, 'raw_data.categories'))) {
                data_set($enriched, 'raw_data.categories', data_get($candidate, 'raw_data.categories'));
                $contributingSources[] = $candidate['source'] ?? null;
            }

            if (empty(data_get($enriched, 'raw_data.categories_tags')) && !empty(data_get($candidate, 'raw_data.categories_tags'))) {
                data_set($enriched, 'raw_data.categories_tags', data_get($candidate, 'raw_data.categories_tags'));
                $contributingSources[] = $candidate['source'] ?? null;
            }
        }

        $enriched['additives'] = collect($enriched['ingredients'] ?? [])
            ->filter(function (array $ingredient): bool {
                $name = strtolower(trim((string) ($ingredient['name'] ?? '')));

                if ($name === '') {
                    return false;
                }

                if ((bool) ($ingredient['is_additive'] ?? false)) {
                    return true;
                }

                foreach (['lecithin', 'emulsifier', 'preserv', 'color', 'flavor', 'flavour', 'stabilizer'] as $keyword) {
                    if (str_contains($name, $keyword)) {
                        return true;
                    }
                }

                return (bool) preg_match('/\be\d{3}\b/i', $name);
            })
            ->pluck('name')
            ->values()
            ->all();

        $uniqueSources = array_values(array_unique(array_filter($contributingSources)));

        if ($uniqueSources !== []) {
            data_set($enriched, 'raw_data._scanwell.contributing_sources', $uniqueSources);
        }

        $enriched['completeness'] = $this->calculateCompleteness($enriched);
        $enriched['confidence'] = $this->calculateConfidence($enriched, $enriched['completeness']);

        return $enriched;
    }

    protected function normalizeCandidate(array $candidate, string $barcode, string $providerKey): ?array
    {
        if (($candidate['barcode'] ?? null) !== $barcode) {
            return null;
        }

        $family = $this->productFamilyResolver->resolveFromNormalized($candidate);
        $completeness = $this->calculateCompleteness($candidate);
        $warnings = $candidate['warnings'] ?? [];

        if (!$this->meetsMinimumDataRequirements($candidate, $family, $completeness)) {
            return null;
        }

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
        $hasIngredients = !empty($candidate['ingredients']);
        $hasNutrition = $this->hasNutritionData($candidate);
        $name = trim((string) ($candidate['name'] ?? ''));

        if (!empty($candidate['image_url'])) {
            $confidence += 5;
        }

        if ($hasIngredients) {
            $confidence += 5;
        }

        if ($hasNutrition) {
            $confidence += 5;
        }

        if (($candidate['product_type'] ?? null) !== ProductFamilyResolver::GENERAL) {
            $confidence += 5;
        }

        $confidence += (int) round($completeness / 4);

        if (!$hasIngredients && !$hasNutrition) {
            $confidence -= 25;
        }

        if ($name === '' || $name === 'Unknown Product') {
            $confidence -= 15;
        }

        return max(0, min(100, $confidence));
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
            return $this->configuredProviders();
        }

        $providers = ScanProvider::query()
            ->where('is_active', true)
            ->orderBy('priority')
            ->orderBy('name')
            ->get()
            ->filter(fn (ScanProvider $provider): bool => $provider->supportsFamily($productFamily))
            ->values();

        return $providers->isNotEmpty() ? $providers : $this->configuredProviders($productFamily);
    }

    protected function configuredProviders(?string $productFamily = null): Collection
    {
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
            ]))
            ->filter(fn (ScanProvider $provider): bool => $provider->supportsFamily($productFamily))
            ->values();
    }

    protected function meetsMinimumDataRequirements(array $candidate, string $family, int $completeness): bool
    {
        $hasIngredients = !empty($candidate['ingredients']);
        $hasNutrition = $this->hasNutritionData($candidate);
        $hasName = filled($candidate['name'] ?? null) && ($candidate['name'] ?? null) !== 'Unknown Product';
        $hasBrand = filled($candidate['brand'] ?? null);
        $hasImage = filled($candidate['image_url'] ?? null);

        return match ($family) {
            ProductFamilyResolver::FOOD, ProductFamilyResolver::PET_FOOD => $hasIngredients || $hasNutrition,
            ProductFamilyResolver::COSMETIC, ProductFamilyResolver::HOUSEHOLD => $hasIngredients || $completeness >= 45,
            default => $hasName && ($hasBrand || $hasImage),
        };
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
