<?php

namespace App\Services;

use App\Models\CosmeticScore;
use App\Models\FoodNutrition;
use App\Models\FoodScore;
use App\Models\Ingredient;
use App\Models\Product;
use App\Models\ProductAuditLog;
use App\Models\ProductBarcode;
use App\Models\ProductContribution;
use App\Models\ProductImage;
use App\Models\User;
use InvalidArgumentException;

class ProductWorkflowService
{
    public function __construct(
        protected ProductPayloadService $productPayloadService,
        protected ProductFamilyResolver $productFamilyResolver,
        protected ScoreCalculationService $scoreCalculationService
    ) {
    }

    public function findByBarcode(string $barcode): ?Product
    {
        return Product::with(['ingredients', 'nutrition', 'foodScore', 'cosmeticScore', 'images', 'barcodes'])
            ->where('barcode', $barcode)
            ->orWhereHas('barcodes', fn ($query) => $query->where('barcode', $barcode))
            ->first();
    }

    public function createProduct(array $payload, array $context = []): Product
    {
        $normalized = $this->normalizePayload($payload);
        $this->assertRequiredProductFields($normalized);
        $actor = $context['actor'] ?? null;
        $manualOverrides = ($context['track_manual_overrides'] ?? false)
            ? $this->buildManualOverrides([], $normalized)
            : ($context['manual_overrides'] ?? []);

        $product = Product::create([
            'barcode' => $normalized['barcode'],
            'name' => $normalized['name'],
            'brand' => $normalized['brand'] ?? null,
            'category_id' => $normalized['category_id'] ?? null,
            'product_family' => $normalized['product_family'],
            'category_name' => $normalized['category_name'] ?? null,
            'image_url' => $normalized['image_url'] ?? null,
            'ingredients_text' => $normalized['ingredients_text'] ?? null,
            'additives' => $normalized['additives'] ?? [],
            'allergens' => $normalized['allergens'] ?? [],
            'region_availability' => $normalized['region_availability'] ?? [],
            'manual_overrides' => $manualOverrides,
            'source' => $context['source'] ?? ($normalized['source'] ?? 'manual'),
            'raw_data' => $this->buildRawData(null, $normalized, $manualOverrides, $context),
            'created_by' => $context['created_by'] ?? $actor?->id,
            'approved_by' => $context['approved_by'] ?? (($context['mark_approved'] ?? true) ? $actor?->id : null),
            'approved_at' => $context['approved_at'] ?? (($context['mark_approved'] ?? true) ? now() : null),
        ]);

        $this->syncIngredients($product, $normalized['ingredients'] ?? []);
        $this->syncNutrition($product, $normalized['nutrition'] ?? []);
        $this->syncBarcodes($product, $normalized['barcodes'] ?? [], $normalized['barcode'], $actor);
        $this->syncImages($product, $normalized['images'] ?? [], $actor);
        $this->syncScores($product->fresh(['ingredients', 'nutrition']));

        if (($context['audit'] ?? true) === true) {
            $this->recordAudit(
                $actor,
                $product,
                $context['contribution'] ?? null,
                $context['audit_action'] ?? 'product_created',
                $context['audit_description'] ?? 'Product created',
                ['new' => $this->productPayloadService->snapshotProduct($product)],
                $context['audit_metadata'] ?? []
            );
        }

        return $product->fresh([
            'ingredients',
            'nutrition',
            'foodScore',
            'cosmeticScore',
            'images',
            'barcodes',
        ]);
    }

    public function updateProduct(Product $product, array $payload, array $context = []): Product
    {
        $originalSnapshot = $this->productPayloadService->snapshotProduct($product);
        $normalized = $this->normalizePayload($payload, $product);
        $actor = $context['actor'] ?? null;
        $attributes = [];

        foreach ([
            'barcode',
            'name',
            'brand',
            'category_id',
            'product_family',
            'category_name',
            'image_url',
            'ingredients_text',
            'additives',
            'allergens',
            'region_availability',
        ] as $field) {
            if (array_key_exists($field, $normalized)) {
                $attributes[$field] = $normalized[$field];
            }
        }

        if (($context['source'] ?? null) !== null) {
            $attributes['source'] = $context['source'];
        } elseif (array_key_exists('source', $normalized)) {
            $attributes['source'] = $normalized['source'];
        }

        if (($context['approved_by'] ?? null) !== null) {
            $attributes['approved_by'] = $context['approved_by'];
        } elseif (($context['mark_approved'] ?? false) && $actor) {
            $attributes['approved_by'] = $actor->id;
        }

        if (($context['mark_approved'] ?? false) === true) {
            $attributes['approved_at'] = $context['approved_at'] ?? now();
        }

        $manualOverrides = $product->manual_overrides ?? [];

        if ($context['track_manual_overrides'] ?? false) {
            $manualOverrides = $this->buildManualOverrides($manualOverrides, $normalized);
            $attributes['manual_overrides'] = $manualOverrides;
        }

        $attributes['raw_data'] = $this->buildRawData($product, $normalized, $manualOverrides, $context);

        if ($context['preserve_manual_overrides'] ?? false) {
            $attributes = $this->applyStoredManualOverridesToAttributes($attributes, $product->manual_overrides ?? []);
        }

        $product->fill($attributes);
        $product->save();

        if (array_key_exists('ingredients', $normalized)) {
            $this->syncIngredients(
                $product,
                ($context['preserve_manual_overrides'] ?? false)
                    ? ($product->manual_overrides['ingredients'] ?? $normalized['ingredients'])
                    : $normalized['ingredients']
            );
        }

        if (array_key_exists('nutrition', $normalized)) {
            $this->syncNutrition(
                $product,
                ($context['preserve_manual_overrides'] ?? false)
                    ? ($product->manual_overrides['nutrition'] ?? $normalized['nutrition'])
                    : $normalized['nutrition']
            );
        }

        if (array_key_exists('barcodes', $normalized) || array_key_exists('barcode', $normalized)) {
            $this->syncBarcodes(
                $product,
                ($context['preserve_manual_overrides'] ?? false)
                    ? ($product->manual_overrides['barcodes'] ?? ($normalized['barcodes'] ?? $originalSnapshot['barcodes'] ?? []))
                    : ($normalized['barcodes'] ?? $originalSnapshot['barcodes'] ?? []),
                $product->barcode,
                $actor
            );
        }

        if (array_key_exists('images', $normalized) || array_key_exists('image_url', $normalized)) {
            $this->syncImages(
                $product,
                ($context['preserve_manual_overrides'] ?? false)
                    ? ($product->manual_overrides['images'] ?? ($normalized['images'] ?? $originalSnapshot['images'] ?? []))
                    : ($normalized['images'] ?? $originalSnapshot['images'] ?? []),
                $actor
            );
        }

        $this->syncScores($product->fresh(['ingredients', 'nutrition']));

        if (($context['audit'] ?? true) === true) {
            $this->recordAudit(
                $actor,
                $product,
                $context['contribution'] ?? null,
                $context['audit_action'] ?? 'product_updated',
                $context['audit_description'] ?? 'Product updated',
                [
                    'old' => $originalSnapshot,
                    'new' => $this->productPayloadService->snapshotProduct($product),
                ],
                $context['audit_metadata'] ?? []
            );
        }

        return $product->fresh([
            'ingredients',
            'nutrition',
            'foodScore',
            'cosmeticScore',
            'images',
            'barcodes',
        ]);
    }

    public function deleteProduct(Product $product, ?User $actor = null): void
    {
        $this->recordAudit(
            $actor,
            $product,
            null,
            'product_deleted',
            'Product deleted',
            ['old' => $this->productPayloadService->snapshotProduct($product)]
        );

        $product->delete();
    }

    public function syncScores(Product $product): void
    {
        $family = $this->productFamilyResolver->resolveFromProduct($product);

        if ($this->productFamilyResolver->supportsFoodScore($family)) {
            $scores = $this->scoreCalculationService->calculateFoodScores($product);

            FoodScore::updateOrCreate(
                ['product_id' => $product->id],
                [
                    'overall_score' => $scores['overall'],
                    'nutrition_score' => $scores['nutrition'],
                    'ingredient_score' => $scores['ingredient'],
                    'additive_score' => $scores['additive'],
                    'processing_score' => $scores['processing'],
                    'nova_group' => $scores['nova_group'],
                    'nutriscore_grade' => $scores['nutriscore_grade'],
                    'score_breakdown' => $scores['score_breakdown'],
                    'explanation_text' => $scores['explanation'],
                    'warnings' => $scores['warnings'],
                    'benefits' => $scores['benefits'],
                    'calculated_at' => now(),
                ]
            );

            $product->cosmeticScore()->delete();

            return;
        }

        if ($this->productFamilyResolver->supportsCosmeticScore($family)) {
            $scores = $this->scoreCalculationService->calculateCosmeticScores($product);

            CosmeticScore::updateOrCreate(
                ['product_id' => $product->id],
                [
                    'overall_score' => $scores['overall'],
                    'irritant_score' => $scores['irritant'],
                    'endocrine_score' => $scores['endocrine'],
                    'allergen_score' => $scores['allergen'],
                    'environmental_score' => $scores['environmental'],
                    'score_breakdown' => $scores['score_breakdown'],
                    'explanation_text' => $scores['explanation'],
                    'warnings' => $scores['warnings'],
                    'benefits' => $scores['benefits'],
                    'skin_types_suitable' => $scores['skin_types_suitable'],
                    'calculated_at' => now(),
                ]
            );

            $product->foodScore()->delete();

            return;
        }

        $product->foodScore()->delete();
        $product->cosmeticScore()->delete();
    }

    public function recordAudit(
        ?User $actor,
        ?Product $product,
        ?ProductContribution $contribution,
        string $action,
        ?string $description = null,
        ?array $changes = null,
        ?array $metadata = null
    ): ProductAuditLog {
        return ProductAuditLog::create([
            'actor_id' => $actor?->id,
            'product_id' => $product?->id,
            'contribution_id' => $contribution?->id,
            'action' => $action,
            'description' => $description,
            'changes' => $changes,
            'metadata' => $metadata,
        ]);
    }

    protected function normalizePayload(array $payload, ?Product $existing = null): array
    {
        $payload = $this->productPayloadService->fromInput($payload);

        $resolvedName = $this->resolvePayloadString($payload, [
            'name',
            'product_name',
            'generic_name',
            'abbreviated_product_name',
        ], $payload['raw_data'] ?? []);

        if ($resolvedName !== null) {
            $payload['name'] = $resolvedName;
        } elseif ($existing?->name) {
            $payload['name'] = $existing->name;
        }

        $resolvedBarcode = $this->resolvePayloadString($payload, ['barcode', 'code'], $payload['raw_data'] ?? []);

        if ($resolvedBarcode !== null) {
            $payload['barcode'] = $resolvedBarcode;
        } elseif ($existing?->barcode) {
            $payload['barcode'] = $existing->barcode;
        }

        $resolvedFamily = $payload['product_family']
            ?? $existing?->resolved_product_family
            ?? $this->productFamilyResolver->resolveFromNormalized($payload);

        $payload['product_family'] = $resolvedFamily;

        if (!array_key_exists('category_id', $payload)) {
            $payload['category_id'] = $existing?->category_id
                ?? $this->productFamilyResolver->categoryIdForFamily($resolvedFamily, $payload);
        }

        if (!array_key_exists('category_name', $payload) && $existing?->category_name) {
            $payload['category_name'] = $existing->category_name;
        }

        if (!array_key_exists('image_url', $payload) && $existing?->image_url) {
            $payload['image_url'] = $existing->image_url;
        }

        if (!array_key_exists('ingredients_text', $payload)) {
            if (!empty($payload['ingredients'] ?? [])) {
                $payload['ingredients_text'] = implode(', ', array_column($payload['ingredients'], 'name'));
            } elseif ($existing?->ingredients_text) {
                $payload['ingredients_text'] = $existing->ingredients_text;
            }
        }

        if (!array_key_exists('additives', $payload) && !empty($payload['ingredients'] ?? [])) {
            $payload['additives'] = collect($payload['ingredients'])
                ->filter(fn ($ingredient) => (bool) ($ingredient['is_additive'] ?? false))
                ->pluck('name')
                ->values()
                ->all();
        }

        return $payload;
    }

    protected function assertRequiredProductFields(array $payload): void
    {
        $missingFields = [];

        foreach (['barcode', 'name'] as $field) {
            if (trim((string) ($payload[$field] ?? '')) === '') {
                $missingFields[] = $field;
            }
        }

        if ($missingFields !== []) {
            throw new InvalidArgumentException('Product payload missing required fields: '.implode(', ', $missingFields));
        }
    }

    protected function resolvePayloadString(array $payload, array $keys, array $rawData = []): ?string
    {
        foreach ($keys as $key) {
            $value = $payload[$key] ?? data_get($rawData, $key);
            $resolved = trim((string) $value);

            if ($resolved !== '') {
                return $resolved;
            }
        }

        return null;
    }

    protected function syncIngredients(Product $product, array $ingredients): void
    {
        $syncPayload = [];

        foreach ($ingredients as $ingredientData) {
            $name = trim((string) ($ingredientData['name'] ?? ''));

            if ($name === '') {
                continue;
            }

            $ingredient = Ingredient::firstOrCreate(
                ['name' => $name],
                [
                    'category' => $this->determineIngredientCategory($name),
                    'risk_level' => $this->determineRiskLevel($name),
                ]
            );

            $syncPayload[$ingredient->id] = [
                'percentage' => $ingredientData['percentage'] ?? null,
                'is_additive' => (bool) ($ingredientData['is_additive'] ?? $this->looksLikeAdditive($name)),
                'origin' => $ingredientData['origin'] ?? null,
            ];
        }

        $product->ingredients()->sync($syncPayload);
    }

    protected function syncNutrition(Product $product, array $nutrition): void
    {
        if ($nutrition === []) {
            $product->nutrition()->delete();

            return;
        }

        FoodNutrition::updateOrCreate(
            ['product_id' => $product->id],
            array_merge($nutrition, ['product_id' => $product->id])
        );
    }

    protected function syncBarcodes(Product $product, array $barcodes, string $primaryBarcode, ?User $actor = null): void
    {
        $normalized = collect($barcodes)
            ->map(function ($barcode) {
                if (is_string($barcode)) {
                    return [
                        'barcode' => $barcode,
                        'label' => null,
                        'is_primary' => false,
                    ];
                }

                return $barcode;
            })
            ->filter(fn ($barcode) => !empty($barcode['barcode']))
            ->unique('barcode')
            ->values();

        if (!$normalized->contains(fn ($barcode) => $barcode['barcode'] === $primaryBarcode)) {
            $normalized->prepend([
                'barcode' => $primaryBarcode,
                'label' => 'primary',
                'is_primary' => true,
            ]);
        }

        $normalized = $normalized
            ->map(function ($barcode) use ($primaryBarcode) {
                $barcode['is_primary'] = $barcode['barcode'] === $primaryBarcode;

                return $barcode;
            })
            ->values();

        ProductBarcode::where('product_id', $product->id)
            ->whereNotIn('barcode', $normalized->pluck('barcode'))
            ->delete();

        foreach ($normalized as $barcodeData) {
            ProductBarcode::updateOrCreate(
                ['barcode' => $barcodeData['barcode']],
                [
                    'product_id' => $product->id,
                    'label' => $barcodeData['label'] ?? null,
                    'is_primary' => $barcodeData['is_primary'],
                    'created_by' => $actor?->id,
                ]
            );
        }
    }

    protected function syncImages(Product $product, array $images, ?User $actor = null): void
    {
        ProductImage::where('product_id', $product->id)->delete();

        foreach (collect($images)->values() as $index => $image) {
            ProductImage::create([
                'product_id' => $product->id,
                'disk' => $image['disk'] ?? null,
                'path' => $image['path'] ?? null,
                'url' => $image['url'] ?? null,
                'source' => $image['source'] ?? 'manual',
                'is_primary' => (bool) ($image['is_primary'] ?? $index === 0),
                'sort_order' => $image['sort_order'] ?? $index,
                'uploaded_by' => $actor?->id,
            ]);
        }
    }

    protected function buildRawData(?Product $product, array $payload, array $manualOverrides, array $context): array
    {
        $existingRawData = $product?->raw_data ?? [];
        $incomingRawData = $payload['raw_data'] ?? [];
        $source = $context['source'] ?? ($payload['source'] ?? $product?->source ?? 'manual');

        $rawData = array_replace_recursive($existingRawData, $incomingRawData);
        $rawData['_scanwell'] = array_merge($existingRawData['_scanwell'] ?? [], $incomingRawData['_scanwell'] ?? [], [
            'source' => $source,
            'product_family' => $payload['product_family'],
            'manual_overrides' => $manualOverrides,
            'updated_via' => $context['audit_action'] ?? ($product ? 'update' : 'create'),
        ]);

        return $rawData;
    }

    protected function buildManualOverrides(array $existingOverrides, array $payload): array
    {
        foreach ($payload as $field => $value) {
            if (in_array($field, ['source', 'raw_data'], true)) {
                continue;
            }

            $existingOverrides[$field] = $value;
        }

        return $existingOverrides;
    }

    protected function applyStoredManualOverridesToAttributes(array $attributes, array $manualOverrides): array
    {
        foreach ($manualOverrides as $field => $value) {
            if (in_array($field, ['name', 'brand', 'category_id', 'category_name', 'product_family', 'image_url', 'ingredients_text', 'additives', 'allergens', 'region_availability'], true)) {
                $attributes[$field] = $value;
            }
        }

        return $attributes;
    }

    protected function determineIngredientCategory(string $ingredientName): string
    {
        $ingredientName = strtolower($ingredientName);

        return match (true) {
            str_contains($ingredientName, 'sugar'),
            str_contains($ingredientName, 'syrup'),
            str_contains($ingredientName, 'sweetener') => 'sweetener',
            str_contains($ingredientName, 'oil'),
            str_contains($ingredientName, 'fat'),
            str_contains($ingredientName, 'butter') => 'fat',
            str_contains($ingredientName, 'preserv') => 'preservative',
            str_contains($ingredientName, 'color'),
            (bool) preg_match('/\be\d{3}\b/i', $ingredientName) => 'additive',
            default => 'other',
        };
    }

    protected function determineRiskLevel(string $ingredientName): string
    {
        $ingredientName = strtolower($ingredientName);

        foreach ([
            'aspartame',
            'saccharin',
            'msg',
            'monosodium glutamate',
            'sodium nitrite',
            'potassium bromate',
            'bha',
            'bht',
            'red 40',
            'yellow 5',
            'blue 1',
            'triclosan',
            'phthalate',
            'paraben',
        ] as $keyword) {
            if (str_contains($ingredientName, $keyword)) {
                return 'high';
            }
        }

        foreach ([
            'high fructose corn syrup',
            'partially hydrogenated',
            'carrageenan',
            'sodium benzoate',
            'potassium sorbate',
            'fragrance',
            'parfum',
        ] as $keyword) {
            if (str_contains($ingredientName, $keyword)) {
                return 'medium';
            }
        }

        return 'low';
    }

    protected function looksLikeAdditive(string $ingredientName): bool
    {
        $ingredientName = strtolower($ingredientName);

        foreach (['lecithin', 'emulsifier', 'preserv', 'color', 'flavor', 'flavour', 'stabilizer'] as $keyword) {
            if (str_contains($ingredientName, $keyword)) {
                return true;
            }
        }

        return (bool) preg_match('/\be\d{3}\b/i', $ingredientName);
    }
}
