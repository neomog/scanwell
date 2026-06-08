<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ContributionReputationService;
use App\Services\ProductContributionService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommunityController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected ContributionReputationService $contributionReputationService,
        protected ProductContributionService $productContributionService
    ) {
    }

    public function summary(Request $request): JsonResponse
    {
        $user = $request->user()->loadCount([
            'contributions as contributions_total',
            'contributions as pending_contributions_count' => fn ($query) => $query->whereIn('status', ['pending', 'changes_requested']),
            'contributions as flagged_contributions_count' => fn ($query) => $query->where('status', 'flagged'),
        ]);

        $approvedCount = (int) ($user->approved_contributions_count ?? 0);
        $rejectedCount = (int) ($user->rejected_contributions_count ?? 0);
        $reputationPoints = (int) ($user->reputation_points ?? 0);
        $leaderboardQuery = $this->productContributionService->leaderboardBaseQuery();
        $leaderboardSummary = $this->productContributionService->leaderboardSummary(clone $leaderboardQuery);
        $userRank = null;

        if ((int) ($user->contributions_total ?? 0) > 0) {
            $userRank = (clone $leaderboardQuery)
                ->reorder()
                ->orderByDesc('reputation_points')
                ->orderByDesc('approved_contributions_count')
                ->orderByDesc('contributions_count')
                ->pluck('id')
                ->search($user->id);

            $userRank = $userRank === false ? null : $userRank + 1;
        }

        return $this->success([
            'contributions_total' => (int) ($user->contributions_total ?? 0),
            'approved_contributions_count' => $approvedCount,
            'pending_contributions_count' => (int) ($user->pending_contributions_count ?? 0),
            'rejected_contributions_count' => $rejectedCount,
            'flagged_contributions_count' => (int) ($user->flagged_contributions_count ?? 0),
            'reputation_points' => $reputationPoints,
            'reputation_level' => $this->contributionReputationService->levelForPoints($reputationPoints),
            'user_rank' => $userRank,
            'total_contributors' => $leaderboardSummary['contributors'],
            'top_reputation_points' => $leaderboardSummary['top_score'],
            'unread_notifications_count' => $user->notifications()->whereNull('read_at')->count(),
        ], 'Community summary loaded');
    }
}
