<?php

namespace Tests\Feature;

use App\Jobs\ImportScanProviderProducts;
use App\Models\Product;
use App\Models\ScanProvider;
use App\Models\User;
use App\Services\ScanProviderImportService;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\Fixtures\FakeCatalogProvider;
use Tests\TestCase;

class ScanProviderImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);
    }

    public function test_super_admin_can_queue_a_provider_product_sync(): void
    {
        Bus::fake();

        $superAdmin = User::factory()->superAdmin()->create();
        $provider = ScanProvider::create([
            'name' => 'Import Provider',
            'provider_key' => 'import_provider',
            'driver' => FakeCatalogProvider::class,
            'is_active' => true,
            'priority' => 10,
            'supported_families' => ['food'],
            'settings' => [
                'import' => [
                    'query' => 'breakfast',
                    'page_size' => 10,
                    'max_pages' => 1,
                ],
            ],
            'credentials' => [],
            'timeout_seconds' => 5,
            'retry_attempts' => 0,
            'cache_ttl_minutes' => 0,
            'health_status' => 'healthy',
        ]);

        $this->actingAs($superAdmin)
            ->post(route('admin.scanning.providers.sync-products', $provider))
            ->assertRedirect()
            ->assertSessionHas('success');

        Bus::assertDispatched(ImportScanProviderProducts::class, function (ImportScanProviderProducts $job) use ($provider, $superAdmin) {
            return $job->scanProviderId === $provider->id
                && $job->actorId === $superAdmin->id;
        });
    }

    public function test_sync_action_requires_import_query_configuration(): void
    {
        Bus::fake();

        $superAdmin = User::factory()->superAdmin()->create();
        $provider = ScanProvider::create([
            'name' => 'Import Provider',
            'provider_key' => 'import_provider',
            'driver' => FakeCatalogProvider::class,
            'is_active' => true,
            'priority' => 10,
            'supported_families' => ['food'],
            'settings' => [],
            'credentials' => [],
            'timeout_seconds' => 5,
            'retry_attempts' => 0,
            'cache_ttl_minutes' => 0,
            'health_status' => 'healthy',
        ]);

        $this->actingAs($superAdmin)
            ->post(route('admin.scanning.providers.sync-products', $provider))
            ->assertRedirect()
            ->assertSessionHas('error');

        Bus::assertNotDispatched(ImportScanProviderProducts::class);
    }

    public function test_import_service_creates_and_updates_products_from_provider_results(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();

        $existingProduct = Product::create([
            'barcode' => '9988776655',
            'name' => 'Old Product',
            'brand' => 'Old Brand',
            'category_id' => 1,
            'product_family' => 'food',
            'source' => 'manual',
            'raw_data' => [],
        ]);

        $provider = ScanProvider::create([
            'name' => 'Import Provider',
            'provider_key' => 'import_provider',
            'driver' => FakeCatalogProvider::class,
            'is_active' => true,
            'priority' => 10,
            'supported_families' => ['food'],
            'settings' => [
                'import' => [
                    'query' => 'snacks',
                    'page_size' => 20,
                    'max_pages' => 1,
                ],
                'import_products' => [
                    [
                        'barcode' => $existingProduct->barcode,
                        'name' => 'Updated Imported Product',
                        'brand' => 'Updated Imported Brand',
                        'source' => 'fake_import_source',
                        'product_type' => 'food',
                        'ingredients' => [
                            ['name' => 'Corn'],
                            ['name' => 'Salt'],
                        ],
                        'nutrition' => [
                            'calories' => 150,
                        ],
                        'raw_data' => [
                            'categories' => 'Snacks',
                        ],
                    ],
                    [
                        'barcode' => '2233445566',
                        'name' => 'Brand New Imported Product',
                        'brand' => 'Fresh Import Brand',
                        'source' => 'fake_import_source',
                        'product_type' => 'food',
                        'ingredients' => [
                            ['name' => 'Oats'],
                        ],
                        'nutrition' => [
                            'calories' => 210,
                        ],
                        'raw_data' => [
                            'categories' => 'Breakfast',
                        ],
                    ],
                ],
            ],
            'credentials' => [],
            'timeout_seconds' => 5,
            'retry_attempts' => 0,
            'cache_ttl_minutes' => 0,
            'health_status' => 'healthy',
        ]);

        $summary = app(ScanProviderImportService::class)->import($provider, $superAdmin);

        $this->assertSame(1, $summary['created']);
        $this->assertSame(1, $summary['updated']);
        $this->assertSame(0, $summary['skipped']);

        $this->assertDatabaseHas('products', [
            'barcode' => $existingProduct->barcode,
            'name' => 'Updated Imported Product',
            'brand' => 'Updated Imported Brand',
            'source' => 'import_provider',
        ]);

        $this->assertDatabaseHas('products', [
            'barcode' => '2233445566',
            'name' => 'Brand New Imported Product',
            'brand' => 'Fresh Import Brand',
            'source' => 'import_provider',
        ]);

        $newProduct = Product::query()->where('barcode', '2233445566')->firstOrFail();

        $this->assertSame('import_provider', data_get($newProduct->raw_data, '_scanwell.provider'));
        $this->assertSame('snacks', data_get($newProduct->raw_data, '_scanwell.import.query'));
    }

    public function test_import_service_skips_products_without_a_usable_name(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();

        $provider = ScanProvider::create([
            'name' => 'Import Provider',
            'provider_key' => 'import_provider',
            'driver' => FakeCatalogProvider::class,
            'is_active' => true,
            'priority' => 10,
            'supported_families' => ['food'],
            'settings' => [
                'import' => [
                    'query' => 'snacks',
                    'page_size' => 20,
                    'max_pages' => 1,
                ],
                'import_products' => [
                    [
                        'barcode' => '1000000001',
                        'name' => '',
                        'raw_data' => [
                            'product_name' => '',
                            'generic_name' => '',
                        ],
                    ],
                    [
                        'barcode' => '1000000002',
                        'name' => 'Valid Imported Product',
                        'source' => 'fake_import_source',
                        'product_type' => 'food',
                    ],
                ],
            ],
            'credentials' => [],
            'timeout_seconds' => 5,
            'retry_attempts' => 0,
            'cache_ttl_minutes' => 0,
            'health_status' => 'healthy',
        ]);

        $summary = app(ScanProviderImportService::class)->import($provider, $superAdmin);

        $this->assertSame(1, $summary['created']);
        $this->assertSame(0, $summary['updated']);
        $this->assertSame(1, $summary['skipped']);
        $this->assertDatabaseMissing('products', [
            'barcode' => '1000000001',
        ]);
        $this->assertDatabaseHas('products', [
            'barcode' => '1000000002',
            'name' => 'Valid Imported Product',
        ]);
    }

    public function test_update_can_save_import_settings_without_editing_raw_json(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $provider = ScanProvider::create([
            'name' => 'Import Provider',
            'provider_key' => 'import_provider',
            'driver' => FakeCatalogProvider::class,
            'is_active' => true,
            'priority' => 10,
            'supported_families' => ['food'],
            'settings' => [
                'base_url' => 'https://api.edamam.com/api/food-database/v2/parser',
            ],
            'credentials' => [],
            'timeout_seconds' => 5,
            'retry_attempts' => 0,
            'cache_ttl_minutes' => 0,
            'health_status' => 'healthy',
        ]);

        $this->actingAs($superAdmin)
            ->post(route('admin.scanning.providers.update', $provider), [
                'name' => 'Import Provider',
                'priority' => 10,
                'supported_families' => 'food',
                'timeout_seconds' => 5,
                'retry_attempts' => 0,
                'cache_ttl_minutes' => 0,
                'import_query' => 'breakfast cereal',
                'import_page_size' => 15,
                'import_max_pages' => 3,
                'edamam_base_url' => 'https://example.com/catalog',
                'edamam_category' => 'packaged-foods',
                'edamam_nutrition_type' => 'cooking',
                'edamam_app_id' => 'demo-app-id',
                'edamam_app_key' => 'demo-app-key',
                'notes' => 'Test provider',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $provider->refresh();

        $this->assertSame('https://example.com/catalog', data_get($provider->settings, 'base_url'));
        $this->assertSame('packaged-foods', data_get($provider->settings, 'category'));
        $this->assertSame('cooking', data_get($provider->settings, 'nutrition_type'));
        $this->assertSame('breakfast cereal', data_get($provider->settings, 'import.query'));
        $this->assertSame(15, data_get($provider->settings, 'import.page_size'));
        $this->assertSame(3, data_get($provider->settings, 'import.max_pages'));
        $this->assertSame('demo-app-id', data_get($provider->credentials, 'app_id'));
        $this->assertSame('demo-app-key', data_get($provider->credentials, 'app_key'));
    }
}
