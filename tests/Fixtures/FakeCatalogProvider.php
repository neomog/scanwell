<?php

namespace Tests\Fixtures;

use App\Contracts\ProductCatalogProvider;
use RuntimeException;

class FakeCatalogProvider implements ProductCatalogProvider
{
    public function providerKey(): string
    {
        return 'fake_catalog';
    }

    public function findByBarcode(string $barcode, array $settings = [], array $credentials = []): ?array
    {
        $mode = $settings['mode'] ?? 'not_found';

        if ($mode === 'error') {
            throw new RuntimeException($settings['error_message'] ?? 'Simulated provider failure.');
        }

        if ($mode !== 'match') {
            return null;
        }

        return [
            'barcode' => $barcode,
            'name' => $settings['name'] ?? 'Provider Match Product',
            'brand' => $settings['brand'] ?? 'Provider Match Brand',
            'category_id' => $settings['category_id'] ?? null,
            'image_url' => $settings['image_url'] ?? 'https://example.com/fake-product.jpg',
            'source' => $settings['source'] ?? 'fake_source',
            'product_type' => $settings['product_type'] ?? 'food',
            'ingredients' => $settings['ingredients'] ?? [
                ['name' => 'Water'],
                ['name' => 'Natural Flavor'],
            ],
            'nutrition' => $settings['nutrition'] ?? [
                'calories' => 25,
                'sugars' => 3,
                'protein' => 0,
            ],
            'packaging' => $settings['packaging'] ?? [
                'description' => 'Bottle',
                'materials' => ['plastic'],
                'is_plastic' => true,
            ],
            'warnings' => $settings['warnings'] ?? [],
            'raw_data' => $settings['raw_data'] ?? [
                'categories' => 'Beverages',
                'categories_tags' => ['en:beverages'],
            ],
        ];
    }
}
