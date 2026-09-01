<?php

namespace Tests\Feature;

use App\Mail\NotificationCampaignMail;
use App\Models\NotificationCampaign;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);
    }

    public function test_admin_can_create_campaign_and_mobile_user_can_read_it(): void
    {
        config([
            'notifications.push.enabled' => true,
            'notifications.push.endpoint' => 'https://push.example.test/send',
            'notifications.push.token' => 'secret',
        ]);

        Http::fake([
            'https://push.example.test/send' => Http::response(['ok' => true], 200),
        ]);
        Mail::fake();

        $admin = User::factory()->admin()->create();
        $recipient = User::factory()->create();
        $recipient->pushTokens()->create([
            'platform' => 'android',
            'token' => 'device-token-1',
            'is_active' => true,
        ]);

        Sanctum::actingAs($admin);

        $createResponse = $this->postJson('/api/v1/admin/notifications', [
            'type' => 'announcement',
            'title' => 'Maintenance window',
            'subject' => 'Maintenance window',
            'body' => 'We are performing scheduled maintenance tonight.',
            'channels' => ['in_app', 'email', 'push'],
            'audience_type' => 'users',
            'audience' => [
                'user_ids' => [$recipient->id],
            ],
        ]);

        $createResponse->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.notification.title', 'Maintenance window')
            ->assertJsonPath('data.notification.recipients_count', 1);

        Mail::assertSent(NotificationCampaignMail::class, 1);

        Sanctum::actingAs($recipient);

        $indexResponse = $this->getJson('/api/v1/notifications');
        $notificationId = $indexResponse->json('data.notifications.0.id');

        $indexResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.unread_count', 1)
            ->assertJsonPath('data.notifications.0.title', 'Maintenance window')
            ->assertJsonPath('data.notifications.0.is_read', false);

        $this->postJson("/api/v1/notifications/{$notificationId}/read")
            ->assertOk()
            ->assertJsonPath('data.notification.is_read', true);

        $this->getJson('/api/v1/notifications/unread-count')
            ->assertOk()
            ->assertJsonPath('data.unread_count', 0);
    }

    public function test_support_can_view_notifications_but_cannot_create_them(): void
    {
        $support = User::factory()->support()->create();
        $campaign = NotificationCampaign::create([
            'created_by' => $support->id,
            'type' => 'announcement',
            'status' => 'draft',
            'title' => 'Read-only notice',
            'body' => 'Support can review this.',
            'audience_type' => 'all_users',
            'channels' => ['in_app'],
        ]);

        $this->actingAs($support)
            ->get('/admin/notifications')
            ->assertOk();

        $this->actingAs($support)
            ->get(route('admin.notifications.show', $campaign))
            ->assertOk();

        $this->actingAs($support)
            ->get('/admin/notifications/create')
            ->assertForbidden();
    }
}
