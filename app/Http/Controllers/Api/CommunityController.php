<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponse;
use App\Services\ContributionReputationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommunityController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected ContributionReputationService $contributionReputationService
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

        return $this->success([
            'contributions_total' => (int) ($user->contributions_total ?? 0),
            'approved_contributions_count' => $approvedCount,
            'pending_contributions_count' => (int) ($user->pending_contributions_count ?? 0),
            'rejected_contributions_count' => $rejectedCount,
            'flagged_contributions_count' => (int) ($user->flagged_contributions_count ?? 0),
            'reputation_points' => $reputationPoints,
            'reputation_level' => $this->contributionReputationService->levelForPoints($reputationPoints),
            'unread_notifications_count' => $user->notifications()->whereNull('read_at')->count(),
        ], 'Community summary loaded');
    }
}
