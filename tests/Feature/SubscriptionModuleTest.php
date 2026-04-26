<?php

namespace Tests\Feature;

use App\Models\Scan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SubscriptionModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_recommendations_are_blocked_for_the_default_free_plan(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/recommendations')
            ->assertForbidden()
            ->assertJsonPath('feature', 'recommendations.enabled');
    }

    public function test_scan_limit_is_enforced_for_the_current_plan(): void
    {
        $user = User::factory()->create();
        $subscription = $user->subscriptions()->with('plan')->current()->firstOrFail();
        $features = $subscription->plan->features;

        data_set($features, 'scans.monthly_limit', 1);
        $subscription->plan->update(['features' => $features]);

        Scan::create([
            'user_id' => $user->id,
            'barcode' => '12345678',
            'scan_timestamp' => now(),
            'device_type' => 'phpunit',
            'status' => 'completed',
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/scan', [
            'barcode' => '12345678',
        ])->assertForbidden()
            ->assertJsonPath('feature', 'scans.monthly_limit');
    }

    public function test_admin_can_view_plan_management_page(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.plans.index'))
            ->assertOk()
            ->assertSee('Plan Management')
            ->assertSee('Free')
            ->assertSee('Pro')
            ->assertSee('Team');
    }
}
