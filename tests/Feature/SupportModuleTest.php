<?php

namespace Tests\Feature;

use App\Models\SupportCase;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SupportModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);
    }

    public function test_mobile_user_can_create_and_reply_to_support_case(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $createResponse = $this->postJson('/api/v1/support/cases', [
            'type' => 'bug_report',
            'subject' => 'App crashes on scan',
            'description' => 'The app crashes whenever I scan a barcode.',
            'priority' => 'high',
            'attachments' => [
                'https://example.com/screenshot.png',
            ],
            'metadata' => [
                'platform' => 'android',
                'app_version' => '1.4.2',
                'device_name' => 'Pixel 8',
            ],
        ]);

        $caseId = $createResponse->json('data.case.id');

        $createResponse->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.case.type', 'bug_report')
            ->assertJsonPath('data.case.status', 'pending_support');

        $this->postJson("/api/v1/support/cases/{$caseId}/messages", [
            'message' => 'Additional detail: it happens after login.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.case.status', 'pending_support');

        $this->getJson("/api/v1/support/cases/{$caseId}")
            ->assertOk()
            ->assertJsonPath('data.case.id', $caseId);
    }

    public function test_support_agent_can_view_and_manage_support_queue(): void
    {
        $customer = User::factory()->create();
        $support = User::factory()->support()->create();

        $case = SupportCase::create([
            'reference' => 'SUP-20260503-ABC123',
            'user_id' => $customer->id,
            'type' => 'complaint',
            'subject' => 'Refund complaint',
            'description' => 'I am unhappy with the billing change.',
            'status' => 'pending_support',
            'priority' => 'normal',
            'source' => 'mobile',
            'last_message_at' => now(),
        ]);

        $this->actingAs($support)
            ->get('/admin/support')
            ->assertOk();

        $this->actingAs($support)
            ->get(route('admin.support.show', $case))
            ->assertOk();

        Sanctum::actingAs($support);

        $this->postJson("/api/v1/admin/support/cases/{$case->id}", [
            'status' => 'resolved',
            'priority' => 'high',
            'assigned_to' => $support->id,
        ])
            ->assertOk()
            ->assertJsonPath('data.case.status', 'resolved')
            ->assertJsonPath('data.case.priority', 'high');

        $this->postJson("/api/v1/admin/support/cases/{$case->id}/messages", [
            'message' => 'We have reviewed the issue and resolved it.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.case.status', 'pending_user');
    }
}
