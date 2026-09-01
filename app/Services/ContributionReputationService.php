<?php

namespace App\Services;

use App\Models\ProductContribution;
use App\Models\User;

class ContributionReputationService
{
    public function applyApproval(User $user, ProductContribution $contribution): int
    {
        $points = (int) config("contributions.reputation.approved.{$contribution->change_type}", 0);

        $user->increment('reputation_points', $points);
        $user->increment('approved_contributions_count');

        return $points;
    }

    public function applyRejection(User $user, ProductContribution $contribution): int
    {
        $points = (int) config("contributions.reputation.rejected.{$contribution->change_type}", 0);

        $user->forceFill([
            'reputation_points' => max(0, $user->reputation_points + $points),
            'rejected_contributions_count' => $user->rejected_contributions_count + 1,
        ])->save();

        return $points;
    }

    public function levelForPoints(int $points): string
    {
        $levels = config('contributions.levels', []);
        $current = 'Scout';

        foreach ($levels as $level) {
            if ($points >= ($level['points'] ?? 0)) {
                $current = $level['name'] ?? $current;
            }
        }

        return $current;
    }
}
