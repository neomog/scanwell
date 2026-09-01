<?php

namespace Tests\Feature;

use App\Models\BillingInvoice;
use App\Models\SubscriptionEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BillingAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_billing_subscription_api_returns_display_expiry_and_history_preview(): void
    {
        $user = User::factory()->create();
        $subscription = $user->subscriptions()->with(['plan', 'price'])->current()->firstOrFail();
        $expiry = now()->addDays(12)->startOfDay();

        $subscription->update([
            'current_period_ends_at' => null,
            'ends_at' => $expiry,
        ]);

        SubscriptionEvent::create([
            'user_id' => $user->id,
            'subscription_id' => $subscription->id,
            'event_type' => 'manual_override_applied',
            'source' => 'admin',
            'to_plan_id' => $subscription->plan_id,
            'to_price_id' => $subscription->price_id,
            'status_after' => $subscription->status,
            'effective_at' => now(),
        ]);

        BillingInvoice::create([
            'user_id' => $user->id,
            'subscription_id' => $subscription->id,
            'provider_invoice_id' => 'in_test_123',
            'provider_payment_intent_id' => 'pi_test_123',
            'currency' => 'usd',
            'subtotal' => 1900,
            'total' => 1900,
            'amount_paid' => 1900,
            'amount_due' => 0,
            'status' => 'paid',
            'issued_at' => now(),
            'paid_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/billing/subscription')
            ->assertOk()
            ->assertJsonPath('data.subscription.display_expiry_at', $expiry->toJSON())
            ->assertJsonPath('data.subscription.latest_invoice.provider_invoice_id', 'in_test_123')
            ->assertJsonPath('data.subscription.history_preview.0.event_type', 'manual_override_applied');
    }

    public function test_billing_history_endpoint_returns_subscription_events(): void
    {
        $user = User::factory()->create();
        $subscription = $user->subscriptions()->current()->firstOrFail();

        SubscriptionEvent::create([
            'user_id' => $user->id,
            'subscription_id' => $subscription->id,
            'event_type' => 'admin_scheduled_plan_change_requested',
            'source' => 'admin',
            'to_plan_id' => $subscription->plan_id,
            'to_price_id' => $subscription->price_id,
            'status_before' => 'active',
            'status_after' => 'canceling',
            'reason' => 'Upgrade next cycle',
            'effective_at' => now()->addMonth(),
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/billing/history')
            ->assertOk()
            ->assertJsonPath('data.history.0.event_type', 'admin_scheduled_plan_change_requested')
            ->assertJsonPath('data.history.0.reason', 'Upgrade next cycle');
    }

    public function test_admin_user_page_uses_display_expiry_and_preselects_current_price(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $subscription = $user->subscriptions()->with('price')->current()->firstOrFail();
        $expiry = now()->addDays(30)->startOfDay();

        $subscription->update([
            'current_period_ends_at' => null,
            'ends_at' => $expiry,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.users.show', $user));

        $response->assertOk();
        $response->assertSee($expiry->format('M d, Y'));
        $response->assertSeeHtml('value="'.$subscription->price_id.'" selected');
    }
}
