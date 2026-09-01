<?php

namespace Tests\Fixtures;

use App\Contracts\ProductCatalogImportProvider;
use App\Contracts\ProductCatalogProvider;
use RuntimeException;

class FakeCatalogProvider implements ProductCatalogImportProvider, ProductCatalogProvider
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

    public function searchProducts(string $query, int $page = 1, int $pageSize = 20, array $settings = [], array $credentials = []): array
    {
        if (($settings['import_mode'] ?? 'results') === 'empty') {
            return [
                'products' => [],
                'total' => 0,
                'page' => $page,
                'page_count' => 0,
            ];
        }

        $products = collect($settings['import_products'] ?? [[
            'barcode' => $settings['import_barcode'] ?? '1122334455',
            'name' => $settings['import_name'] ?? 'Imported Product',
            'brand' => $settings['import_brand'] ?? 'Imported Brand',
            'source' => $settings['source'] ?? 'fake_import_source',
            'product_type' => $settings['product_type'] ?? 'food',
            'image_url' => $settings['image_url'] ?? 'https://example.com/imported-product.jpg',
            'ingredients' => $settings['ingredients'] ?? [['name' => 'Water']],
            'nutrition' => $settings['nutrition'] ?? ['calories' => 10],
            'raw_data' => $settings['raw_data'] ?? ['categories' => 'Imported'],
        ]]);

        return [
            'products' => $products->forPage($page, $pageSize)->values()->all(),
            'total' => $products->count(),
            'page' => $page,
            'page_count' => (int) ceil($products->count() / max($pageSize, 1)),
        ];
    }
}
