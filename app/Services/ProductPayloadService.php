<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Facades\Storage;

class ProductPayloadService
{
    public function fromInput(array $input): array
    {
        $payload = [];

        if ($this->hasValue($input, 'barcode')) {
            $payload['barcode'] = trim((string) $input['barcode']);
        }

        if ($this->hasValue($input, 'name')) {
            $payload['name'] = trim((string) $input['name']);
        } elseif ($this->hasValue($input, 'product_name')) {
            $payload['name'] = trim((string) $input['product_name']);
        }

        if (array_key_exists('brand', $input)) {
            $payload['brand'] = $this->nullableString($input['brand']);
        }

        if (array_key_exists('category_id', $input)) {
            $payload['category_id'] = $input['category_id'] !== null ? (int) $input['category_id'] : null;
        }

        if (array_key_exists('category_name', $input)) {
            $payload['category_name'] = $this->nullableString($input['category_name']);
        }

        if (array_key_exists('product_family', $input)) {
            $payload['product_family'] = $this->nullableString($input['product_family']);
        }

        if (array_key_exists('image_url', $input)) {
            $payload['image_url'] = $this->nullableString($input['image_url']);
        }

        if (array_key_exists('ingredients_text', $input)) {
            $payload['ingredients_text'] = $this->nullableString($input['ingredients_text']);
        }

        if (array_key_exists('additives', $input)) {
            $payload['additives'] = $this->normalizeStringList($input['additives']);
        }

        if (array_key_exists('allergens', $input)) {
            $payload['allergens'] = $this->normalizeStringList($input['allergens']);
        }

        if (array_key_exists('region_availability', $input)) {
            $payload['region_availability'] = $this->normalizeStringList($input['region_availability']);
        }

        if (array_key_exists('raw_data', $input) && is_array($input['raw_data'])) {
            $payload['raw_data'] = $input['raw_data'];
        }

        if (array_key_exists('source', $input)) {
            $payload['source'] = $this->nullableString($input['source']);
        }

        if (array_key_exists('ingredients', $input)) {
            $payload['ingredients'] = $this->normalizeIngredients($input['ingredients']);
        }

        if (array_key_exists('nutrition', $input)) {
            $payload['nutrition'] = $this->normalizeNutrition($input['nutrition']);
        }

        if (array_key_exists('barcodes', $input)) {
            $payload['barcodes'] = $this->normalizeBarcodes($input['barcodes']);
        }

        if (array_key_exists('images', $input)) {
            $payload['images'] = $this->normalizeImages($input['images']);
        } elseif (array_key_exists('image_urls', $input)) {
            $payload['images'] = $this->normalizeImages($input['image_urls']);
        }

        if (
            array_key_exists('image_url', $payload)
            && $payload['image_url']
            && !array_key_exists('images', $payload)
        ) {
            $payload['images'] = [[
                'url' => $payload['image_url'],
                'source' => $payload['source'] ?? 'manual',
                'is_primary' => true,
                'sort_order' => 0,
            ]];
        }

        return $payload;
    }

    public function snapshotProduct(Product $product): array
    {
        $product->loadMissing(['ingredients', 'nutrition', 'barcodes', 'images']);

        return [
            'barcode' => $product->barcode,
            'name' => $product->name,
            'brand' => $product->brand,
            'category_id' => $product->category_id,
            'category_name' => $product->category_name,
            'product_family' => $product->resolved_product_family,
            'image_url' => $product->primary_image_url,
            'ingredients_text' => $product->ingredients_text,
            'additives' => $product->additives ?? [],
            'allergens' => $product->allergens ?? [],
            'region_availability' => $product->region_availability ?? [],
            'raw_data' => $product->raw_data ?? [],
            'ingredients' => $product->ingredients->map(fn ($ingredient) => [
                'name' => $ingredient->name,
                'percentage' => $ingredient->pivot?->percentage,
                'origin' => $ingredient->pivot?->origin,
                'is_additive' => (bool) ($ingredient->pivot?->is_additive),
            ])->values()->all(),
            'nutrition' => $product->nutrition ? array_filter([
                'calories' => $product->nutrition->calories,
                'fat' => $product->nutrition->fat,
                'saturated_fat' => $product->nutrition->saturated_fat,
                'trans_fat' => $product->nutrition->trans_fat,
                'cholesterol' => $product->nutrition->cholesterol,
                'sodium' => $product->nutrition->sodium,
                'carbohydrates' => $product->nutrition->carbohydrates,
                'fiber' => $product->nutrition->fiber,
                'sugars' => $product->nutrition->sugars,
                'added_sugars' => $product->nutrition->added_sugars,
                'protein' => $product->nutrition->protein,
                'vitamin_d' => $product->nutrition->vitamin_d,
                'calcium' => $product->nutrition->calcium,
                'iron' => $product->nutrition->iron,
                'potassium' => $product->nutrition->potassium,
                'vitamins' => $product->nutrition->vitamins,
                'minerals' => $product->nutrition->minerals,
                'serving_size' => $product->nutrition->serving_size,
                'servings_per_container' => $product->nutrition->servings_per_container,
            ], fn ($value) => $value !== null && $value !== '') : [],
            'barcodes' => $product->barcodes->map(fn ($barcode) => [
                'barcode' => $barcode->barcode,
                'label' => $barcode->label,
                'is_primary' => $barcode->is_primary,
            ])->values()->all(),
            'images' => $product->images->map(fn ($image) => [
                'url' => $image->resolved_url,
                'disk' => $image->disk,
                'path' => $image->path,
                'source' => $image->source,
                'is_primary' => $image->is_primary,
                'sort_order' => $image->sort_order,
            ])->values()->all(),
        ];
    }

    public function presentContributionPayload(?array $payload): ?array
    {
        if (!is_array($payload)) {
            return $payload;
        }

        $presented = $this->fromInput($payload);

        if (!array_key_exists('images', $presented) || !is_array($presented['images'])) {
            return $presented;
        }

        $presented['images'] = collect($presented['images'])
            ->map(function ($image, int $index) {
                if (!is_array($image)) {
                    return null;
                }

                $image['url'] = $image['url'] ?? $this->resolveStoredImageUrl(
                    $image['disk'] ?? null,
                    $image['path'] ?? null
                );
                $image['is_primary'] = (bool) ($image['is_primary'] ?? $index === 0);
                $image['sort_order'] = isset($image['sort_order']) ? (int) $image['sort_order'] : $index;

                return $image['url'] || ($image['disk'] ?? null) || ($image['path'] ?? null)
                    ? $image
                    : null;
            })
            ->filter()
            ->values()
            ->all();

        if (
            (!array_key_exists('image_url', $presented) || !$presented['image_url'])
            && !empty($presented['images'])
        ) {
            $primaryImage = collect($presented['images'])->firstWhere('is_primary', true)
                ?? $presented['images'][0];

            $presented['image_url'] = $primaryImage['url'] ?? null;
        }

        return $presented;
    }

    public function mergeForContribution(Product $product, array $input): array
    {
        $current = $this->snapshotProduct($product);
        $incoming = $this->fromInput($input);

        foreach ($incoming as $key => $value) {
            if ($key === 'nutrition') {
                $current['nutrition'] = array_merge($current['nutrition'] ?? [], $value);
                continue;
            }

            $current[$key] = $value;
        }

        return $current;
    }

    protected function normalizeIngredients(mixed $ingredients): array
    {
        if (!is_array($ingredients)) {
            return [];
        }

        return collect($ingredients)
            ->map(function ($ingredient) {
                if (is_string($ingredient)) {
                    $name = trim($ingredient);

                    return $name === '' ? null : [
                        'name' => $name,
                        'percentage' => null,
                        'origin' => null,
                        'is_additive' => false,
                    ];
                }

                if (!is_array($ingredient)) {
                    return null;
                }

                $name = trim((string) ($ingredient['name'] ?? ''));

                if ($name === '') {
                    return null;
                }

                return [
                    'name' => $name,
                    'percentage' => isset($ingredient['percentage']) && $ingredient['percentage'] !== ''
                        ? (float) $ingredient['percentage']
                        : (isset($ingredient['percent']) && $ingredient['percent'] !== '' ? (float) $ingredient['percent'] : null),
                    'origin' => $this->nullableString($ingredient['origin'] ?? null),
                    'is_additive' => (bool) ($ingredient['is_additive'] ?? false),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    protected function normalizeNutrition(mixed $nutrition): array
    {
        if (!is_array($nutrition)) {
            return [];
        }

        $normalized = [];

        foreach ($nutrition as $key => $value) {
            if ($value === '' || $value === null) {
                $normalized[$key] = null;
                continue;
            }

            $normalized[$key] = is_numeric($value) ? (float) $value : $value;
        }

        return $normalized;
    }

    protected function normalizeBarcodes(mixed $barcodes): array
    {
        if (!is_array($barcodes)) {
            return [];
        }

        return collect($barcodes)
            ->map(function ($barcode) {
                if (is_string($barcode)) {
                    $value = trim($barcode);

                    return $value === '' ? null : [
                        'barcode' => $value,
                        'label' => null,
                        'is_primary' => false,
                    ];
                }

                if (!is_array($barcode)) {
                    return null;
                }

                $value = trim((string) ($barcode['barcode'] ?? ''));

                if ($value === '') {
                    return null;
                }

                return [
                    'barcode' => $value,
                    'label' => $this->nullableString($barcode['label'] ?? null),
                    'is_primary' => (bool) ($barcode['is_primary'] ?? false),
                ];
            })
            ->filter()
            ->unique('barcode')
            ->values()
            ->all();
    }

    protected function normalizeImages(mixed $images): array
    {
        if (!is_array($images)) {
            return [];
        }

        return collect($images)
            ->map(function ($image, int $index) {
                if (is_string($image)) {
                    $value = trim($image);

                    return $value === '' ? null : [
                        'url' => $value,
                        'disk' => null,
                        'path' => null,
                        'source' => 'manual',
                        'is_primary' => $index === 0,
                        'sort_order' => $index,
                    ];
                }

                if (!is_array($image)) {
                    return null;
                }

                $url = $this->nullableString($image['url'] ?? null);
                $disk = $this->nullableString($image['disk'] ?? null);
                $path = $this->nullableString($image['path'] ?? null);

                if (!$url && !$path) {
                    return null;
                }

                $url ??= $this->resolveStoredImageUrl($disk, $path);

                return [
                    'url' => $url,
                    'disk' => $disk,
                    'path' => $path,
                    'source' => $this->nullableString($image['source'] ?? 'manual') ?? 'manual',
                    'is_primary' => (bool) ($image['is_primary'] ?? $index === 0),
                    'sort_order' => isset($image['sort_order']) ? (int) $image['sort_order'] : $index,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    protected function normalizeStringList(mixed $values): array
    {
        if (!is_array($values)) {
            return [];
        }

        return collect($values)
            ->map(fn ($value) => is_string($value) ? trim($value) : null)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    protected function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    protected function hasValue(array $input, string $key): bool
    {
        return array_key_exists($key, $input)
            && $input[$key] !== null
            && trim((string) $input[$key]) !== '';
    }

    protected function resolveStoredImageUrl(?string $disk, ?string $path): ?string
    {
        if (!$disk || !$path) {
            return null;
        }

        return Storage::disk($disk)->url($path);
    }
}
