<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductContribution;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductContributionFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_product_contribution_stays_pending_until_admin_approval(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $barcode = '9900012345678';

        $response = $this->postJson("/api/v1/products/{$barcode}/contribute", [
            'change_type' => 'add',
            'product_name' => 'Community Juice',
            'brand' => 'Scanwell Labs',
            'ingredients' => [
                ['name' => 'Water'],
                ['name' => 'Apple Juice'],
            ],
            'nutrition' => [
                'calories' => 80,
                'sugars' => 12,
            ],
            'reason' => 'This product is missing from the catalogue.',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.contribution.status', 'pending');

        $this->assertDatabaseMissing('products', [
            'barcode' => $barcode,
        ]);

        $this->assertDatabaseHas('product_contributions', [
            'barcode' => $barcode,
            'status' => 'pending',
            'change_type' => 'add',
        ]);
    }

    public function test_scan_returns_pending_contribution_when_product_is_not_approved_yet(): void
    {
        Http::fake([
            '*' => Http::response(['status' => 0], 404),
        ]);

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $barcode = '9900098765432';

        ProductContribution::create([
            'user_id' => $user->id,
            'change_type' => 'add',
            'new_data' => [
                'barcode' => $barcode,
                'name' => 'Pending Cereal',
            ],
            'reason' => 'Pending admin approval for this barcode.',
            'status' => 'pending',
            'barcode' => $barcode,
            'product_name' => 'Pending Cereal',
        ]);

        $response = $this->postJson('/api/v1/scan', [
            'barcode' => $barcode,
        ]);

        $response->assertStatus(404)
            ->assertJsonPath('success', false)
            ->assertJsonPath('data.pending_contribution.barcode', $barcode)
            ->assertJsonPath('data.pending_contribution.status', 'pending');
    }

    public function test_admin_approval_creates_product_and_updates_reputation_and_leaderboard(): void
    {
        $contributor = User::factory()->create();
        Sanctum::actingAs($contributor);

        $barcode = '9900011122233';

        $submitResponse = $this->postJson("/api/v1/products/{$barcode}/contribute", [
            'change_type' => 'add',
            'product_name' => 'Approved Yogurt',
            'brand' => 'Farm Fresh',
            'ingredients' => [
                ['name' => 'Milk'],
                ['name' => 'Culture'],
            ],
            'nutrition' => [
                'calories' => 95,
                'protein' => 6,
                'sugars' => 8,
            ],
            'reason' => 'This is a valid product that should be listed.',
        ]);

        $contributionId = $submitResponse->json('data.contribution.id');

        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $approveResponse = $this->postJson("/api/v1/admin/contributions/{$contributionId}/approve", [
            'notes' => 'Verified against packaging.',
        ]);

        $approveResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.contribution.status', 'approved')
            ->assertJsonPath('data.product.barcode', $barcode);

        $this->assertDatabaseHas('products', [
            'barcode' => $barcode,
            'source' => 'community',
        ]);

        $contributor->refresh();
        $this->assertSame(25, $contributor->reputation_points);
        $this->assertSame(1, $contributor->approved_contributions_count);

        Sanctum::actingAs($contributor);

        $leaderboardResponse = $this->getJson('/api/v1/contributions/leaderboard');

        $leaderboardResponse->assertOk()
            ->assertJsonPath('data.0.id', $contributor->id)
            ->assertJsonPath('data.0.level', 'Scout');
    }

    public function test_existing_product_correction_requires_approval_before_product_changes(): void
    {
        $product = Product::create([
            'barcode' => '8800012345678',
            'name' => 'Original Snack',
            'brand' => 'Old Brand',
            'category_id' => 30,
            'product_family' => 'food',
            'source' => 'manual',
        ]);

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $submitResponse = $this->postJson("/api/v1/products/{$product->barcode}/contribute", [
            'change_type' => 'correct',
            'field_name' => 'brand',
            'new_value' => 'Fixed Brand',
            'reason' => 'The product brand on pack is different from the current record.',
        ]);

        $contributionId = $submitResponse->json('data.contribution.id');

        $product->refresh();
        $this->assertSame('Old Brand', $product->brand);

        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $approveResponse = $this->postJson("/api/v1/admin/contributions/{$contributionId}/approve", [
            'notes' => 'Brand verified from shelf image.',
        ]);

        $approveResponse->assertOk()
            ->assertJsonPath('data.contribution.status', 'approved')
            ->assertJsonPath('data.product.brand', 'Fixed Brand');

        $product->refresh();
        $this->assertSame('Fixed Brand', $product->brand);
        $this->assertSame('Fixed Brand', $product->manual_overrides['brand'] ?? null);
    }

    public function test_admin_dashboard_approval_keeps_uploaded_contribution_images(): void
    {
        Storage::fake('public');

        $contributor = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $barcode = '7700011122233';
        $storedPath = UploadedFile::fake()->image('front.jpg')->store('product-contributions', 'public');

        $contribution = ProductContribution::create([
            'user_id' => $contributor->id,
            'change_type' => 'add',
            'new_data' => [
                'barcode' => $barcode,
                'name' => 'Photo Yogurt',
                'brand' => 'Farm Fresh',
                'images' => [[
                    'disk' => 'public',
                    'path' => $storedPath,
                    'source' => 'contribution_upload',
                    'is_primary' => true,
                    'sort_order' => 0,
                ]],
                'ingredients' => [
                    ['name' => 'Milk'],
                    ['name' => 'Culture'],
                ],
                'nutrition' => [
                    'calories' => 95,
                    'protein' => 6,
                ],
            ],
            'reason' => 'Includes package photos for moderation.',
            'status' => 'pending',
            'barcode' => $barcode,
            'product_name' => 'Photo Yogurt',
        ]);

        $response = $this->actingAs($admin)->post(
            "/admin/contributions/{$contribution->id}/approve",
            ['notes' => 'Verified against submitted images.']
        );

        $response->assertRedirect("/admin/contributions/{$contribution->id}");

        $product = Product::with(['images', 'ingredients', 'nutrition'])->where('barcode', $barcode)->firstOrFail();

        $this->assertSame(1, $product->images->count());
        $this->assertSame(Storage::disk('public')->url($storedPath), $product->images->first()->resolved_url);
        $this->assertSame(['Milk', 'Culture'], $product->ingredients->pluck('name')->all());
        $this->assertSame(95.0, $product->nutrition?->calories);
        $this->assertSame('approved', $contribution->fresh()->status);
    }

    public function test_admin_contribution_review_page_displays_uploaded_contribution_images(): void
    {
        Storage::fake('public');

        $admin = User::factory()->admin()->create();
        $contributor = User::factory()->create();
        $storedPath = UploadedFile::fake()->image('front.jpg')->store('product-contributions', 'public');

        $contribution = ProductContribution::create([
            'user_id' => $contributor->id,
            'change_type' => 'add',
            'new_data' => [
                'barcode' => '7712312312312',
                'name' => 'Preview Juice',
                'images' => [[
                    'disk' => 'public',
                    'path' => $storedPath,
                    'source' => 'contribution_upload',
                    'is_primary' => true,
                    'sort_order' => 0,
                ]],
            ],
            'reason' => 'Preview images should be visible in moderation.',
            'status' => 'pending',
            'barcode' => '7712312312312',
            'product_name' => 'Preview Juice',
        ]);

        $response = $this->actingAs($admin)->get("/admin/contributions/{$contribution->id}");

        $response->assertOk();
        $response->assertSee(Storage::disk('public')->url($storedPath), false);
        $response->assertSee('Submitted Images');
        $response->assertSee('Review Images');
    }
}
