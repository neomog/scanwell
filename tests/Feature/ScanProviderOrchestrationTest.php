<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ScanProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fixtures\FakeCatalogProvider;
use Tests\TestCase;

class ScanProviderOrchestrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_active_providers_are_checked_before_returning_not_found(): void
    {
        ScanProvider::query()->update(['is_active' => false]);

        ScanProvider::create([
            'name' => 'Provider A',
            'provider_key' => 'provider_a',
            'driver' => FakeCatalogProvider::class,
            'is_active' => true,
            'priority' => 10,
            'supported_families' => ['food', 'general'],
            'settings' => ['mode' => 'not_found'],
            'credentials' => [],
            'timeout_seconds' => 5,
            'retry_attempts' => 0,
            'cache_ttl_minutes' => 0,
            'health_status' => 'healthy',
        ]);

        ScanProvider::create([
            'name' => 'Provider B',
            'provider_key' => 'provider_b',
            'driver' => FakeCatalogProvider::class,
            'is_active' => true,
            'priority' => 20,
            'supported_families' => ['food', 'general'],
            'settings' => ['mode' => 'not_found'],
            'credentials' => [],
            'timeout_seconds' => 5,
            'retry_attempts' => 0,
            'cache_ttl_minutes' => 0,
            'health_status' => 'healthy',
        ]);

        $barcode = '99887766';

        $this->getJson("/api/v1/products/barcode/{$barcode}")
            ->assertStatus(404)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Product not found');

        $this->assertDatabaseCount('scan_provider_lookups', 2);
        $this->assertDatabaseHas('scan_provider_lookups', [
            'barcode' => $barcode,
            'status' => 'not_found',
            'matched' => false,
        ]);
    }

    public function test_second_active_provider_can_resolve_a_product_after_first_misses(): void
    {
        ScanProvider::query()->update(['is_active' => false]);

        ScanProvider::create([
            'name' => 'Provider A',
            'provider_key' => 'provider_a',
            'driver' => FakeCatalogProvider::class,
            'is_active' => true,
            'priority' => 10,
            'supported_families' => ['food'],
            'settings' => ['mode' => 'not_found'],
            'credentials' => [],
            'timeout_seconds' => 5,
            'retry_attempts' => 0,
            'cache_ttl_minutes' => 0,
            'health_status' => 'healthy',
        ]);

        ScanProvider::create([
            'name' => 'Provider B',
            'provider_key' => 'provider_b',
            'driver' => FakeCatalogProvider::class,
            'is_active' => true,
            'priority' => 20,
            'supported_families' => ['food'],
            'settings' => [
                'mode' => 'match',
                'source' => 'provider_b_source',
                'name' => 'Resolved by Provider B',
                'brand' => 'Fallback Labs',
            ],
            'credentials' => [],
            'timeout_seconds' => 5,
            'retry_attempts' => 0,
            'cache_ttl_minutes' => 0,
            'health_status' => 'healthy',
        ]);

        $barcode = '55667788';

        $this->getJson("/api/v1/products/barcode/{$barcode}")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Resolved by Provider B')
            ->assertJsonPath('data.source', 'provider_b_source');

        $product = Product::query()->where('barcode', $barcode)->firstOrFail();

        $this->assertSame(2, data_get($product->raw_data, '_scanwell.lookup_summary.attempts_count'));
        $this->assertDatabaseCount('scan_provider_lookups', 2);
        $this->assertDatabaseHas('scan_provider_lookups', [
            'barcode' => $barcode,
            'status' => 'success',
            'matched' => true,
        ]);
    }
}
