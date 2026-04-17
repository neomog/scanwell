<?php

namespace App\Services;

use App\Contracts\ProductCatalogProvider;

class ProductCatalogService
{
    /**
     * @var array<int, ProductCatalogProvider>
     */
    protected array $providers = [];

    public function __construct(
        protected ProductFamilyResolver $productFamilyResolver
    ) {
        foreach (config('scanning.providers', []) as $providerClass) {
            $provider = app($providerClass);

            if ($provider instanceof ProductCatalogProvider) {
                $this->providers[] = $provider;
            }
        }
    }

    public function findByBarcode(string $barcode): ?array
    {
        $candidates = [];

        foreach ($this->providers as $provider) {
            $candidate = $provider->findByBarcode($barcode);

            if ($candidate === null) {
                continue;
            }

            $normalized = $this->normalizeCandidate($candidate, $barcode, $provider->providerKey());

            if ($normalized !== null) {
                $candidates[] = $normalized;
            }
        }

        if ($candidates === []) {
            return null;
        }

        usort($candidates, function (array $left, array $right): int {
            return [$right['confidence'], $right['completeness']]
                <=> [$left['confidence'], $left['completeness']];
        });

        $bestCandidate = $candidates[0];

        return $bestCandidate['confidence'] >= config('scanning.min_candidate_confidence', 45)
            ? $bestCandidate
            : null;
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
}
