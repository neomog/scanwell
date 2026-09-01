<?php

namespace App\Services;

use App\Models\Product;

class ProductFamilyResolver
{
    public const FOOD = 'food';
    public const COSMETIC = 'cosmetic';
    public const PET_FOOD = 'pet_food';
    public const HOUSEHOLD = 'household';
    public const GENERAL = 'general';

    public function resolveFromNormalized(array $productData): string
    {
        $text = $this->buildSearchText($productData);
        $hint = $productData['product_type'] ?? null;

        if ($this->containsAny($text, [
            'cosmetic', 'beauty', 'skin care', 'skincare', 'face care', 'lotion', 'serum',
            'cleanser', 'moisturizer', 'shampoo', 'conditioner', 'soap', 'makeup', 'parfum',
            'perfume', 'deodorant',
        ])) {
            return self::COSMETIC;
        }

        if ($this->containsAny($text, [
            'pet food', 'dog food', 'cat food', 'pet treat', 'dog treat', 'cat treat',
        ])) {
            return self::PET_FOOD;
        }

        if ($this->containsAny($text, [
            'detergent', 'dishwashing', 'laundry', 'cleaner', 'disinfectant', 'bleach',
            'household', 'paper towel', 'toilet paper',
        ])) {
            return self::HOUSEHOLD;
        }

        if (
            !empty($productData['nutrition'])
            || !empty($productData['ingredients'])
            || $this->containsAny($text, [
                'food', 'drink', 'beverage', 'water', 'juice', 'soda', 'snack', 'cookie',
                'biscuit', 'bread', 'pasta', 'sauce', 'mayonnaise', 'yogurt', 'milk',
            ])
        ) {
            return self::FOOD;
        }

        return match ($hint) {
            self::COSMETIC,
            self::PET_FOOD,
            self::HOUSEHOLD,
            self::GENERAL,
            self::FOOD => $hint,
            default => self::GENERAL,
        };
    }

    public function resolveFromProduct(Product $product): string
    {
        $storedFamily = data_get($product->raw_data, '_scanwell.product_family');

        if (is_string($storedFamily) && $storedFamily !== '') {
            return $storedFamily;
        }

        return match (true) {
            $product->category_id >= 100 && $product->category_id < 200 => self::COSMETIC,
            $product->category_id >= 300 && $product->category_id < 400 => self::PET_FOOD,
            $product->category_id >= 400 && $product->category_id < 500 => self::HOUSEHOLD,
            $product->category_id >= 900 => self::GENERAL,
            default => self::FOOD,
        };
    }

    public function categoryIdForFamily(string $family, array $productData = []): int
    {
        $text = $this->buildSearchText($productData);

        return match ($family) {
            self::COSMETIC => match (true) {
                $this->containsAny($text, ['face', 'skincare', 'skin care', 'cleanser', 'serum', 'moisturizer']) => 110,
                $this->containsAny($text, ['hair', 'shampoo', 'conditioner']) => 120,
                $this->containsAny($text, ['body', 'soap', 'deodorant', 'lotion']) => 130,
                $this->containsAny($text, ['makeup', 'lipstick', 'mascara', 'foundation']) => 140,
                $this->containsAny($text, ['perfume', 'fragrance', 'parfum']) => 150,
                default => 190,
            },
            self::PET_FOOD => match (true) {
                $this->containsAny($text, ['dog']) => 310,
                $this->containsAny($text, ['cat']) => 320,
                default => 390,
            },
            self::HOUSEHOLD => match (true) {
                $this->containsAny($text, ['detergent', 'dishwashing', 'laundry', 'cleaner', 'bleach']) => 410,
                $this->containsAny($text, ['paper towel', 'toilet paper', 'tissue']) => 420,
                default => 490,
            },
            self::GENERAL => 900,
            default => match (true) {
                $this->isWater($productData) => 10,
                $this->containsAny($text, ['drink', 'beverage', 'juice', 'soda']) => 15,
                $this->containsAny($text, ['milk', 'cheese', 'yogurt']) => 20,
                $this->containsAny($text, ['snack', 'chips', 'cookie', 'biscuit']) => 30,
                $this->containsAny($text, ['mayonnaise', 'sauce', 'ketchup', 'mustard', 'condiment', 'dressing']) => 40,
                $this->containsAny($text, ['bread', 'rice', 'pasta', 'grain', 'cereal', 'oat']) => 50,
                $this->containsAny($text, ['fruit', 'vegetable', 'salad']) => 60,
                $this->containsAny($text, ['meat', 'chicken', 'beef', 'fish', 'seafood']) => 70,
                default => 90,
            },
        };
    }

    public function supportsFoodScore(string $family): bool
    {
        return $family === self::FOOD;
    }

    public function supportsCosmeticScore(string $family): bool
    {
        return $family === self::COSMETIC;
    }

    public function isWater(Product|array $subject): bool
    {
        $text = $this->buildSearchText($subject);

        return $this->containsAny($text, [
            'water', 'waters', 'spring water', 'mineral water', 'purified water',
            'sparkling water', 'drinking water', 'bottled water',
        ]);
    }

    public function hasPlasticPackaging(Product|array $subject): bool
    {
        return (bool) data_get($this->packagingMetadata($subject), 'is_plastic', false);
    }

    public function packagingMaterials(Product|array $subject): array
    {
        return data_get($this->packagingMetadata($subject), 'materials', []);
    }

    protected function packagingMetadata(Product|array $subject): array
    {
        if ($subject instanceof Product) {
            return data_get($subject->raw_data, '_scanwell.packaging', []);
        }

        return $subject['packaging'] ?? [];
    }

    protected function buildSearchText(Product|array $subject): string
    {
        if ($subject instanceof Product) {
            $rawData = $subject->raw_data ?? [];

            return strtolower(trim(implode(' ', array_filter([
                $subject->name,
                $subject->brand,
                $rawData['categories'] ?? null,
                is_array($rawData['categories_tags'] ?? null) ? implode(' ', $rawData['categories_tags']) : null,
                is_array($rawData['labels_tags'] ?? null) ? implode(' ', $rawData['labels_tags']) : null,
            ]))));
        }

        $rawData = $subject['raw_data'] ?? [];

        return strtolower(trim(implode(' ', array_filter([
            $subject['name'] ?? null,
            $subject['brand'] ?? null,
            $rawData['categories'] ?? null,
            is_array($rawData['categories_tags'] ?? null) ? implode(' ', $rawData['categories_tags']) : null,
            is_array($rawData['labels_tags'] ?? null) ? implode(' ', $rawData['labels_tags']) : null,
        ]))));
    }

    protected function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if ($needle !== '' && str_contains($haystack, strtolower($needle))) {
                return true;
            }
        }

        return false;
    }
}
