<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RbacAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);
    }

    public function test_admin_can_moderate_but_cannot_view_billing(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get('/admin/contributions')
            ->assertOk();

        $this->actingAs($admin)
            ->get('/admin/billing')
            ->assertForbidden();
    }

    public function test_support_can_view_users_but_cannot_review_contributions(): void
    {
        $support = User::factory()->support()->create();

        $this->actingAs($support)
            ->get('/admin/users')
            ->assertOk();

        $this->actingAs($support)
            ->get('/admin/contributions')
            ->assertForbidden();
    }

    public function test_super_admin_can_access_role_and_permission_management(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();

        $this->actingAs($superAdmin)
            ->get('/admin/roles')
            ->assertOk();

        $this->actingAs($superAdmin)
            ->get('/admin/permissions')
            ->assertOk();
    }

    public function test_scanning_provider_management_is_restricted_to_super_admin(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $admin = User::factory()->admin()->create();

        $this->actingAs($superAdmin)
            ->get('/admin/scanning/providers')
            ->assertOk();

        $this->actingAs($admin)
            ->get('/admin/scanning/providers')
            ->assertForbidden();
    }
}
