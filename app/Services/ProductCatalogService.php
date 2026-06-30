<?php

namespace App\Services;

use App\Contracts\ProductCatalogImportProvider;
use App\Contracts\ProductCatalogProvider;
use App\Models\Scan;
use App\Models\ScanProvider;
use App\Models\ScanProviderLookup;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
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
        protected ProductFamilyResolver $productFamilyResolver,
        protected OpenAiVisionService $openAiVisionService,
        protected OpenAiIngredientLabelService $openAiIngredientLabelService,
        protected OpenAiNutritionLabelService $openAiNutritionLabelService
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

                if ($this->shouldShortCircuitAfterCandidate($normalized)) {
                    break;
                }
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
        $bestCandidate = $this->augmentCandidateFromTrustedSearch($bestCandidate, $productFamily);
        $bestCandidate = $this->augmentCandidateFromImage($bestCandidate);
        $bestCandidate['lookup_summary'] = $this->lastLookupSummary;

        if ($bestCandidate['confidence'] < config('scanning.min_candidate_confidence', 45)) {
            return null;
        }

        $this->lastLookupSummary['resolved'] = true;
        $bestCandidate['lookup_summary'] = $this->lastLookupSummary;

        return $bestCandidate;
    }

    protected function augmentCandidateFromTrustedSearch(array $candidate, ?string $productFamily = null): array
    {
        if (!$this->shouldAugmentCandidateFromTrustedSearch($candidate)) {
            return $candidate;
        }

        $searchCandidate = $this->findTrustedSearchCandidate($candidate, $productFamily);

        if ($searchCandidate === null) {
            return $candidate;
        }

        $augmented = $candidate;

        foreach (['name', 'brand', 'image_url', 'page_url', 'ingredients_text', 'nutrition_text'] as $field) {
            if (
                (empty($augmented[$field]) || $augmented[$field] === 'Unknown Product')
                && !empty($searchCandidate[$field])
            ) {
                $augmented[$field] = $searchCandidate[$field];
            }
        }

        if (empty($augmented['ingredients']) && !empty($searchCandidate['ingredients'])) {
            $augmented['ingredients'] = $searchCandidate['ingredients'];
        }

        if (empty($augmented['additives']) && !empty($searchCandidate['additives'])) {
            $augmented['additives'] = $searchCandidate['additives'];
        }

        if (!$this->hasNutritionData($augmented) && $this->hasNutritionData($searchCandidate)) {
            $augmented['nutrition'] = $searchCandidate['nutrition'] ?? [];
        }

        $contributingSources = array_values(array_unique(array_filter(array_merge(
            data_get($augmented, 'raw_data._scanwell.contributing_sources', []),
            [$searchCandidate['source'] ?? null]
        ))));

        if ($contributingSources !== []) {
            data_set($augmented, 'raw_data._scanwell.contributing_sources', $contributingSources);
        }

        if (!empty($searchCandidate['page_url'])) {
            data_set($augmented, 'raw_data._scanwell.trusted_search.page_url', $searchCandidate['page_url']);
        }

        data_set($augmented, 'raw_data._scanwell.trusted_search', array_merge(
            data_get($augmented, 'raw_data._scanwell.trusted_search', []),
            [
                'source' => $searchCandidate['source'] ?? null,
                'applied' => true,
                'resolved_at' => now()->toIso8601String(),
            ]
        ));

        $augmented['warnings'] = array_values(array_unique(array_filter(array_merge(
            $augmented['warnings'] ?? [],
            !empty($searchCandidate['warnings']) && is_array($searchCandidate['warnings']) ? $searchCandidate['warnings'] : []
        ))));

        $augmented['completeness'] = $this->calculateCompleteness($augmented);
        $augmented['confidence'] = $this->calculateConfidence($augmented, $augmented['completeness']);

        return $augmented;
    }

    protected function shouldAugmentCandidateFromTrustedSearch(array $candidate): bool
    {
        if (!config('scanning.trusted_search_enrichment.enabled', true)) {
            return false;
        }

        if (filled(data_get($candidate, 'raw_data._scanwell.trusted_search.applied'))) {
            return false;
        }

        $family = (string) ($candidate['product_type'] ?? ProductFamilyResolver::GENERAL);
        $needsIngredients = empty($candidate['ingredients']);
        $needsNutrition = in_array($family, [ProductFamilyResolver::FOOD, ProductFamilyResolver::PET_FOOD], true)
            && !$this->hasNutritionData($candidate);
        $needsIdentity = empty($candidate['brand'])
            || empty($candidate['name'])
            || ($candidate['name'] ?? null) === 'Unknown Product';

        return $needsIngredients || $needsNutrition || $needsIdentity;
    }

    protected function findTrustedSearchCandidate(array $candidate, ?string $productFamily = null): ?array
    {
        $queries = $this->buildTrustedSearchQueries($candidate);

        if ($queries === []) {
            return null;
        }

        $family = $productFamily ?? ($candidate['product_type'] ?? null);
        $candidates = [];

        foreach ($this->activeProviders($family) as $providerRecord) {
            $driver = app($providerRecord->driver);

            if (!$driver instanceof ProductCatalogImportProvider) {
                continue;
            }

            $providerMatched = false;

            foreach ($queries as $query) {
                try {
                    $result = $driver->searchProducts(
                        $query,
                        1,
                        (int) config('scanning.trusted_search_enrichment.page_size', 6),
                        $this->buildProviderSettings($providerRecord),
                        $providerRecord->credentials ?? []
                    );
                } catch (\Throwable $exception) {
                    Log::warning('Trusted product search enrichment failed', [
                        'provider_key' => $providerRecord->provider_key,
                        'query' => $query,
                        'barcode' => $candidate['barcode'] ?? null,
                        'error' => $exception->getMessage(),
                    ]);
                    continue;
                }

                foreach ($result['products'] ?? [] as $searchResult) {
                    if (!is_array($searchResult)) {
                        continue;
                    }

                    $normalized = $this->normalizeCandidate(
                        $searchResult,
                        (string) ($candidate['barcode'] ?? ''),
                        $providerRecord->provider_key
                    );

                    if ($normalized === null) {
                        continue;
                    }

                    $normalized['search_match_score'] = $this->scoreTrustedSearchMatch($candidate, $normalized);
                    $candidates[] = $normalized;
                    $providerMatched = true;
                }

                if ($providerMatched) {
                    break;
                }
            }
        }

        if ($candidates === []) {
            return null;
        }

        usort($candidates, function (array $left, array $right): int {
            return [$right['search_match_score'] ?? 0, $right['confidence'], $right['completeness']]
                <=> [$left['search_match_score'] ?? 0, $left['confidence'], $left['completeness']];
        });

        $best = $candidates[0];

        if (($best['search_match_score'] ?? 0) < (int) config('scanning.trusted_search_enrichment.min_match_score', 80)) {
            return null;
        }

        return $best;
    }

    protected function buildTrustedSearchQueries(array $candidate): array
    {
        $barcode = trim((string) ($candidate['barcode'] ?? ''));
        $name = trim((string) ($candidate['name'] ?? ''));
        $brand = trim((string) ($candidate['brand'] ?? ''));

        return collect([
            trim(implode(' ', array_filter([$barcode, $brand, $name]))),
            trim(implode(' ', array_filter([$barcode, $name]))),
            trim(implode(' ', array_filter([$brand, $name]))),
            $barcode,
            $name,
        ])
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    protected function scoreTrustedSearchMatch(array $original, array $searchCandidate): int
    {
        $score = 0;

        if (($searchCandidate['barcode'] ?? null) === ($original['barcode'] ?? null)) {
            $score += 60;
        }

        $originalName = $this->normalizeLooseText((string) ($original['name'] ?? ''));
        $searchName = $this->normalizeLooseText((string) ($searchCandidate['name'] ?? ''));
        $originalBrand = $this->normalizeLooseText((string) ($original['brand'] ?? ''));
        $searchBrand = $this->normalizeLooseText((string) ($searchCandidate['brand'] ?? ''));

        if ($originalName !== '' && $searchName !== '') {
            similar_text($originalName, $searchName, $namePct);
            $score += (int) round($namePct * 0.25);
        }

        if ($originalBrand !== '' && $searchBrand !== '') {
            similar_text($originalBrand, $searchBrand, $brandPct);
            $score += (int) round($brandPct * 0.15);
        }

        if (!empty($searchCandidate['ingredients'])) {
            $score += 5;
        }

        if ($this->hasNutritionData($searchCandidate)) {
            $score += 5;
        }

        return min(100, $score);
    }

    protected function normalizeLooseText(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value) ?? '';

        return trim($value);
    }

    protected function augmentCandidateFromImage(array $candidate): array
    {
        if (!$this->shouldAugmentCandidateFromImage($candidate)) {
            return $candidate;
        }

        $cacheKey = 'scanwell:catalog:ai-enrichment:' . sha1(
            implode('|', [
                (string) ($candidate['barcode'] ?? ''),
                (string) ($candidate['image_url'] ?? ''),
                (string) ($candidate['source'] ?? ''),
            ])
        );

        $delta = Cache::remember(
            $cacheKey,
            now()->addMinutes((int) config('scanning.ai_catalog_enrichment.cache_ttl_minutes', 10080)),
            fn (): array => $this->buildImageAugmentationDelta($candidate)
        );

        if ($delta === []) {
            return $candidate;
        }

        $augmented = $candidate;

        foreach (['name', 'brand', 'image_url', 'ingredients_text', 'nutrition_text'] as $field) {
            if (
                (empty($augmented[$field]) || $augmented[$field] === 'Unknown Product')
                && !empty($delta[$field])
            ) {
                $augmented[$field] = $delta[$field];
            }
        }

        if (empty($augmented['ingredients']) && !empty($delta['ingredients'])) {
            $augmented['ingredients'] = $delta['ingredients'];
        }

        if (!$this->hasNutritionData($augmented) && !empty($delta['nutrition'])) {
            $augmented['nutrition'] = $delta['nutrition'];
        }

        if (empty($augmented['additives']) && !empty($delta['additives'])) {
            $augmented['additives'] = $delta['additives'];
        }

        if (!empty($delta['raw_data']) && is_array($delta['raw_data'])) {
            $augmented['raw_data'] = array_replace_recursive($augmented['raw_data'] ?? [], $delta['raw_data']);
        }

        $warnings = array_values(array_unique(array_filter(array_merge(
            $augmented['warnings'] ?? [],
            !empty($delta['applied']) ? ['Some missing product details were enriched from the product image.'] : []
        ))));

        $augmented['warnings'] = $warnings;
        $augmented['completeness'] = $this->calculateCompleteness($augmented);
        $augmented['confidence'] = $this->calculateConfidence($augmented, $augmented['completeness']);

        return $augmented;
    }

    protected function shouldAugmentCandidateFromImage(array $candidate): bool
    {
        if (!config('scanning.ai_catalog_enrichment.enabled', true)) {
            return false;
        }

        if (!filled(config('services.openai.api_key'))) {
            return false;
        }

        if (!filled($candidate['image_url'] ?? null)) {
            return false;
        }

        if (filled(data_get($candidate, 'raw_data._scanwell.ai_catalog_enrichment.applied'))) {
            return false;
        }

        $family = (string) ($candidate['product_type'] ?? ProductFamilyResolver::GENERAL);
        $needsIngredients = empty($candidate['ingredients']);
        $needsNutrition = in_array($family, [ProductFamilyResolver::FOOD, ProductFamilyResolver::PET_FOOD], true)
            && !$this->hasNutritionData($candidate);
        $needsIdentity = empty($candidate['brand'])
            || empty($candidate['name'])
            || ($candidate['name'] ?? null) === 'Unknown Product';

        return $needsIngredients || $needsNutrition || $needsIdentity;
    }

    protected function buildImageAugmentationDelta(array $candidate): array
    {
        $downloaded = $this->downloadImageForAugmentation((string) $candidate['image_url']);

        if ($downloaded === null) {
            return [];
        }

        $delta = [];
        $applied = [];
        $family = (string) ($candidate['product_type'] ?? ProductFamilyResolver::GENERAL);

        if (empty($candidate['brand']) || empty($candidate['name']) || ($candidate['name'] ?? null) === 'Unknown Product') {
            $identity = $this->openAiVisionService->analyzeProductImage(
                $downloaded['binary'],
                $downloaded['mime_type']
            );

            if (!empty($identity['product_name']) && (($candidate['name'] ?? null) === 'Unknown Product' || empty($candidate['name']))) {
                $delta['name'] = $identity['product_name'];
                $applied[] = 'name';
            }

            if (!empty($identity['brand']) && empty($candidate['brand'])) {
                $delta['brand'] = $identity['brand'];
                $applied[] = 'brand';
            }

            if ($identity !== null) {
                data_set($delta, 'raw_data._scanwell.ai_catalog_enrichment.identity', [
                    'analysis_source' => 'openai_vision',
                    'confidence' => $identity['confidence'] ?? null,
                    'front_label_visible' => $identity['front_label_visible'] ?? null,
                    'ingredients_visible' => $identity['ingredients_visible'] ?? null,
                    'nutrition_panel_visible' => $identity['nutrition_panel_visible'] ?? null,
                ]);
            }
        }

        if (empty($candidate['ingredients'])) {
            $ingredientResult = $this->openAiIngredientLabelService->extractFromImage(
                $downloaded['binary'],
                $downloaded['mime_type']
            );

            if (!empty($ingredientResult['ingredients'])) {
                $delta['ingredients'] = $ingredientResult['ingredients'];
                $delta['ingredients_text'] = $ingredientResult['ingredients_text'] ?? null;
                $delta['additives'] = $this->extractAdditivesFromIngredients($ingredientResult['ingredients']);
                $applied[] = 'ingredients';

                data_set($delta, 'raw_data._scanwell.ai_catalog_enrichment.ingredients', [
                    'analysis_source' => $ingredientResult['analysis_source'] ?? 'openai_vision',
                    'confidence' => $ingredientResult['confidence'] ?? null,
                ]);
            }
        }

        if (
            in_array($family, [ProductFamilyResolver::FOOD, ProductFamilyResolver::PET_FOOD], true)
            && !$this->hasNutritionData($candidate)
        ) {
            $nutritionResult = $this->openAiNutritionLabelService->extractFromImage(
                $downloaded['binary'],
                $downloaded['mime_type']
            );

            if (!empty($nutritionResult['nutrition'])) {
                $delta['nutrition'] = $nutritionResult['nutrition'];
                $delta['nutrition_text'] = $nutritionResult['nutrition_text'] ?? null;
                $applied[] = 'nutrition';

                data_set($delta, 'raw_data._scanwell.ai_catalog_enrichment.nutrition', [
                    'analysis_source' => $nutritionResult['analysis_source'] ?? 'openai_vision',
                    'confidence' => $nutritionResult['confidence'] ?? null,
                ]);
            }
        }

        if ($applied !== []) {
            data_set($delta, 'raw_data._scanwell.ai_catalog_enrichment.applied', $applied);
            data_set($delta, 'raw_data._scanwell.ai_catalog_enrichment.image_url', $candidate['image_url'] ?? null);
        }

        return $delta;
    }

    protected function downloadImageForAugmentation(string $imageUrl): ?array
    {
        try {
            $response = Http::timeout((int) config('scanning.ai_catalog_enrichment.image_download_timeout', 8))
                ->accept('image/*')
                ->get($imageUrl);

            if (!$response->successful()) {
                return null;
            }

            $mimeType = strtolower(trim(explode(';', (string) $response->header('Content-Type', 'image/jpeg'))[0]));
            if ($mimeType === '' || !str_starts_with($mimeType, 'image/')) {
                return null;
            }

            $binary = $response->body();
            if ($binary === '') {
                return null;
            }

            return [
                'binary' => $binary,
                'mime_type' => $mimeType,
            ];
        } catch (\Throwable $exception) {
            Log::warning('Failed to download provider image for AI catalog enrichment', [
                'image_url' => $imageUrl,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    protected function extractAdditivesFromIngredients(array $ingredients): array
    {
        return collect($ingredients)
            ->map(function ($ingredient): ?string {
                if (is_array($ingredient)) {
                    return isset($ingredient['name']) ? trim((string) $ingredient['name']) : null;
                }

                return is_string($ingredient) ? trim($ingredient) : null;
            })
            ->filter(function (?string $name): bool {
                if (!$name) {
                    return false;
                }

                $normalized = strtolower($name);

                foreach (['lecithin', 'emulsifier', 'preserv', 'color', 'flavor', 'flavour', 'stabilizer'] as $keyword) {
                    if (str_contains($normalized, $keyword)) {
                        return true;
                    }
                }

                return (bool) preg_match('/\be\d{3}\b/i', $normalized);
            })
            ->values()
            ->all();
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

    protected function shouldShortCircuitAfterCandidate(array $candidate): bool
    {
        $family = (string) ($candidate['product_type'] ?? ProductFamilyResolver::GENERAL);
        $hasIngredients = !empty($candidate['ingredients']);
        $hasNutrition = $this->hasNutritionData($candidate);
        $confidence = (int) ($candidate['confidence'] ?? 0);
        $completeness = (int) ($candidate['completeness'] ?? 0);

        if (in_array($family, [ProductFamilyResolver::FOOD, ProductFamilyResolver::PET_FOOD], true)) {
            return $hasIngredients
                && $hasNutrition
                && $confidence >= 80
                && $completeness >= 75;
        }

        return $confidence >= 80 && $completeness >= 60;
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
