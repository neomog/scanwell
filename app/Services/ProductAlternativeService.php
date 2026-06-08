<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ProductAlternativeService
{
    public function __construct(
        protected ProductFamilyResolver $productFamilyResolver
    ) {
    }

    public function findFor(Product $product, int $limit = 3): Collection
    {
        $product->loadMissing(['foodScore', 'cosmeticScore', 'nutrition', 'ingredients', 'images', 'barcodes']);

        $family = $this->productFamilyResolver->resolveFromProduct($product);
        $currentScore = $product->score;

        if ($currentScore === null) {
            return collect();
        }

        $candidates = $this->candidateQuery($product, $family, true)
            ->get();

        if ($candidates->isEmpty()) {
            $candidates = $this->candidateQuery($product, $family, false)->get();
        }

        return $candidates
            ->map(function (Product $candidate) use ($product, $family, $currentScore) {
                $score = $candidate->score ?? 0;
                $scoreImprovement = round($score - $currentScore, 1);
                $reasons = $this->reasonsFor($product, $candidate, $family);
                $rankScore = $this->rankScore($product, $candidate, $family, $scoreImprovement, $reasons);

                $candidate->setAttribute('alternative_score_improvement', $scoreImprovement);
                $candidate->setAttribute('alternative_reasons', $reasons);
                $candidate->setAttribute('alternative_summary', $reasons[0] ?? 'Higher scoring alternative');
                $candidate->setAttribute('alternative_match_score', round($rankScore, 1));

                return $candidate;
            })
            ->filter(fn (Product $candidate) => ($candidate->alternative_score_improvement ?? 0) > 0)
            ->sortByDesc('alternative_match_score')
            ->take($limit)
            ->values();
    }

    protected function candidateQuery(Product $product, string $family, bool $strictCategory): Builder
    {
        $threshold = $this->minimumAlternativeScore($product);

        $query = Product::query()
            ->with(['foodScore', 'cosmeticScore', 'nutrition', 'ingredients', 'images', 'barcodes'])
            ->where('id', '!=', $product->id)
            ->where(function (Builder $builder) use ($family, $product) {
                if (($product->product_family ?? null) !== null) {
                    $builder->where('product_family', $family);
                }

                $range = $this->categoryRangeForFamily($family);
                if ($range !== null) {
                    $builder->orWhereBetween('category_id', $range);
                }
            });

        if ($strictCategory && $product->category_id) {
            $query->where('category_id', $product->category_id);
        } elseif (!$strictCategory && $product->category_id) {
            $range = $this->categoryRangeForFamily($family);
            if ($range !== null) {
                $query->whereBetween('category_id', $range);
            }
        }

        if (!$strictCategory && !$product->category_id) {
            $categoryName = trim((string) $product->category_name);
            if ($categoryName !== '') {
                $query->where('category_name', $categoryName);
            }
        }

        return match ($family) {
            ProductFamilyResolver::COSMETIC => $query
                ->whereHas('cosmeticScore', fn (Builder $scoreQuery) => $scoreQuery->where('overall_score', '>=', $threshold)),
            default => $query
                ->whereHas('foodScore', fn (Builder $scoreQuery) => $scoreQuery->where('overall_score', '>=', $threshold)),
        };
    }

    protected function minimumAlternativeScore(Product $product): float
    {
        $currentScore = $product->score ?? 0;

        return max($currentScore + 8, 60);
    }

    protected function rankScore(Product $original, Product $candidate, string $family, float $scoreImprovement, array $reasons): float
    {
        $rank = ($candidate->score ?? 0) * 1.4;
        $rank += $scoreImprovement * 3.0;

        if ($original->category_id && $candidate->category_id === $original->category_id) {
            $rank += 25;
        }

        if ($candidate->primary_image_url) {
            $rank += 4;
        }

        if ($this->hasVerifiedData($candidate, $family)) {
            $rank += 8;
        }

        $rank += min(12, count($reasons) * 4);

        $tokenOverlap = $this->nameTokenOverlap($original, $candidate);
        $rank += $tokenOverlap * 6;

        return $rank;
    }

    protected function reasonsFor(Product $original, Product $candidate, string $family): array
    {
        $reasons = match ($family) {
            ProductFamilyResolver::COSMETIC => $this->cosmeticReasons($original, $candidate),
            default => $this->foodReasons($original, $candidate),
        };

        if ($reasons === []) {
            $reasons[] = 'Higher overall score in the same product group';
        }

        return array_values(array_unique($reasons));
    }

    protected function foodReasons(Product $original, Product $candidate): array
    {
        $reasons = [];
        $originalScore = $original->foodScore;
        $candidateScore = $candidate->foodScore;

        if (!$originalScore || !$candidateScore) {
            return $reasons;
        }

        if (($candidateScore->nutrition_score ?? 0) >= ($originalScore->nutrition_score ?? 0) + 8) {
            $reasons[] = 'Stronger nutritional profile';
        }

        if (($candidateScore->ingredient_score ?? 0) >= ($originalScore->ingredient_score ?? 0) + 8) {
            $reasons[] = 'Cleaner ingredient profile';
        }

        if (($candidateScore->additive_score ?? 0) >= ($originalScore->additive_score ?? 0) + 8) {
            $reasons[] = 'Fewer additive concerns';
        }

        if (($candidateScore->processing_score ?? 0) >= ($originalScore->processing_score ?? 0) + 8) {
            $reasons[] = 'Less processed option';
        }

        $originalNutrition = $original->nutrition;
        $candidateNutrition = $candidate->nutrition;

        if ($originalNutrition && $candidateNutrition) {
            if (($candidateNutrition->sugars ?? PHP_FLOAT_MAX) < ($originalNutrition->sugars ?? PHP_FLOAT_MAX)) {
                $reasons[] = 'Lower sugar';
            }

            if (($candidateNutrition->sodium ?? PHP_FLOAT_MAX) < ($originalNutrition->sodium ?? PHP_FLOAT_MAX)) {
                $reasons[] = 'Lower sodium';
            }

            if (($candidateNutrition->fiber ?? -1) > ($originalNutrition->fiber ?? -1)) {
                $reasons[] = 'More fiber';
            }

            if (($candidateNutrition->protein ?? -1) > ($originalNutrition->protein ?? -1)) {
                $reasons[] = 'More protein';
            }
        }

        return array_slice($reasons, 0, 3);
    }

    protected function cosmeticReasons(Product $original, Product $candidate): array
    {
        $reasons = [];
        $originalScore = $original->cosmeticScore;
        $candidateScore = $candidate->cosmeticScore;

        if (!$originalScore || !$candidateScore) {
            return $reasons;
        }

        if (($candidateScore->irritant_score ?? 0) >= ($originalScore->irritant_score ?? 0) + 8) {
            $reasons[] = 'Lower apparent irritant risk';
        }

        if (($candidateScore->endocrine_score ?? 0) >= ($originalScore->endocrine_score ?? 0) + 8) {
            $reasons[] = 'Fewer endocrine risk signals';
        }

        if (($candidateScore->allergen_score ?? 0) >= ($originalScore->allergen_score ?? 0) + 8) {
            $reasons[] = 'Lower fragrance-allergen load';
        }

        if (($candidateScore->environmental_score ?? 0) >= ($originalScore->environmental_score ?? 0) + 8) {
            $reasons[] = 'Better environmental profile';
        }

        return array_slice($reasons, 0, 3);
    }

    protected function hasVerifiedData(Product $product, string $family): bool
    {
        return match ($family) {
            ProductFamilyResolver::COSMETIC => $product->ingredients->isNotEmpty(),
            default => $product->ingredients->isNotEmpty() || $product->nutrition !== null,
        };
    }

    protected function nameTokenOverlap(Product $original, Product $candidate): int
    {
        $originalTokens = $this->nameTokens($original);
        $candidateTokens = $this->nameTokens($candidate);

        return count(array_intersect($originalTokens, $candidateTokens));
    }

    protected function nameTokens(Product $product): array
    {
        $text = strtolower(trim(implode(' ', array_filter([
            $product->name,
            $product->brand,
        ]))));

        preg_match_all('/[a-z0-9]{3,}/', $text, $matches);

        return array_values(array_diff(array_unique($matches[0] ?? []), [
            'with', 'from', 'the', 'and', 'for', 'pack', 'ml', 'g',
        ]));
    }

    protected function categoryRangeForFamily(string $family): ?array
    {
        return match ($family) {
            ProductFamilyResolver::COSMETIC => [100, 199],
            ProductFamilyResolver::PET_FOOD => [300, 399],
            ProductFamilyResolver::HOUSEHOLD => [400, 499],
            ProductFamilyResolver::GENERAL => [900, 999],
            ProductFamilyResolver::FOOD => [1, 99],
            default => null,
        };
    }
}
