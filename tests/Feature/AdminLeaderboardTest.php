<?php

namespace Tests\Feature;

use App\Models\ProductContribution;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminLeaderboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_leaderboard_includes_historical_approved_contributions_without_cached_user_stats(): void
    {
        $admin = User::factory()->admin()->create();
        $contributor = User::factory()->create([
            'reputation_points' => 0,
            'approved_contributions_count' => 0,
            'rejected_contributions_count' => 0,
        ]);

        ProductContribution::create([
            'user_id' => $contributor->id,
            'change_type' => 'add',
            'new_data' => [
                'barcode' => '9900012233445',
                'name' => 'Legacy Soup',
            ],
            'reason' => 'Legacy approved contribution.',
            'status' => 'approved',
            'barcode' => '9900012233445',
            'product_name' => 'Legacy Soup',
            'reviewed_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get('/admin/leaderboard')
            ->assertOk()
            ->assertViewHas('leaders', function ($leaders) use ($contributor) {
                return $leaders->getCollection()->contains(function ($leader) use ($contributor) {
                    return $leader->id === $contributor->id
                        && (int) $leader->reputation_points === 25
                        && (int) $leader->approved_contributions_count === 1
                        && (int) $leader->contributions_count === 1;
                });
            });
    }

    public function test_admin_leaderboard_rejected_sort_includes_rejected_only_contributors(): void
    {
        $admin = User::factory()->admin()->create();
        $rejectedOnlyContributor = User::factory()->create();
        $approvedContributor = User::factory()->create();

        ProductContribution::create([
            'user_id' => $rejectedOnlyContributor->id,
            'change_type' => 'add',
            'new_data' => [
                'barcode' => '9900012233446',
                'name' => 'Rejected Juice',
            ],
            'reason' => 'Rejected contribution for leaderboard coverage.',
            'status' => 'rejected',
            'barcode' => '9900012233446',
            'product_name' => 'Rejected Juice',
            'review_notes' => 'Rejected during moderation.',
            'reviewed_at' => now(),
        ]);

        ProductContribution::create([
            'user_id' => $approvedContributor->id,
            'change_type' => 'add',
            'new_data' => [
                'barcode' => '9900012233447',
                'name' => 'Approved Cereal',
            ],
            'reason' => 'Approved contribution for leaderboard coverage.',
            'status' => 'approved',
            'barcode' => '9900012233447',
            'product_name' => 'Approved Cereal',
            'reviewed_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get('/admin/leaderboard?sort=rejected')
            ->assertOk()
            ->assertViewHas('leaders', function ($leaders) use ($rejectedOnlyContributor) {
                $collection = $leaders->getCollection();
                $firstLeader = $collection->first();

                return $firstLeader?->id === $rejectedOnlyContributor->id
                    && $collection->contains(function ($leader) use ($rejectedOnlyContributor) {
                        return $leader->id === $rejectedOnlyContributor->id
                            && (int) $leader->reputation_points === 0
                            && (int) $leader->rejected_contributions_count === 1
                            && (int) $leader->contributions_count === 1;
                    });
            });
    }
}
