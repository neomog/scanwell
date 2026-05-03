<?php

namespace App\Http\Controllers;

use App\Models\NotificationCampaign;
use App\Models\Product;
use App\Models\ProductContribution;
use App\Models\User;
use App\Services\ContributionReputationService;
use App\Services\ProductContributionService;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function __construct(
        protected ContributionReputationService $contributionReputationService,
        protected ProductContributionService $productContributionService
    ) {
    }

    public function index()
    {
        $totalProducts = Product::count();
        $pendingContributions = ProductContribution::where('status', 'pending')->count();
        $approvedToday = ProductContribution::where('status', 'approved')
            ->whereDate('updated_at', Carbon::today())
            ->count();
        $totalUsers = User::count();
        $notificationsSentToday = NotificationCampaign::whereDate('sent_at', Carbon::today())->count();
        $scheduledNotifications = NotificationCampaign::where('status', NotificationCampaign::STATUS_SCHEDULED)->count();

        $contributionsChart = collect();
        $userGrowthChart = collect();

        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i);

            $contributionsChart->push((object) [
                'date' => $date->format('M d'),
                'count' => ProductContribution::whereDate('created_at', $date)->count(),
            ]);

            $userGrowthChart->push((object) [
                'date' => $date->format('M d'),
                'count' => User::whereDate('created_at', $date)->count(),
            ]);
        }

        $recentContributions = ProductContribution::with('user')
            ->latest()
            ->take(5)
            ->get();

        $recentActivities = collect();

        ProductContribution::with('user')
            ->latest()
            ->take(10)
            ->get()
            ->each(function (ProductContribution $contribution) use ($recentActivities) {
                $recentActivities->push((object) [
                    'message' => ($contribution->user?->name ?? 'Someone') . ' submitted a '
                        . $contribution->change_type . ' contribution for '
                        . ($contribution->product_name ?? 'a product'),
                    'time' => $contribution->created_at->diffForHumans(),
                    'type' => 'contribution',
                    'timestamp' => $contribution->created_at->timestamp,
                ]);
            });

        User::latest()
            ->take(10)
            ->get()
            ->each(function (User $user) use ($recentActivities) {
                $recentActivities->push((object) [
                    'message' => 'New user registered: ' . $user->name,
                    'time' => $user->created_at->diffForHumans(),
                    'type' => 'user',
                    'timestamp' => $user->created_at->timestamp,
                ]);
            });

        NotificationCampaign::with('creator')
            ->latest()
            ->take(10)
            ->get()
            ->each(function (NotificationCampaign $campaign) use ($recentActivities) {
                $recentActivities->push((object) [
                    'message' => ($campaign->creator?->name ?? 'An admin') . ' published '
                        . $campaign->type . ': '
                        . $campaign->title,
                    'time' => $campaign->created_at->diffForHumans(),
                    'type' => 'notification',
                    'timestamp' => $campaign->created_at->timestamp,
                ]);
            });

        $activityFeed = $recentActivities
            ->sortByDesc('timestamp')
            ->take(10)
            ->values();

        $topContributors = $this->productContributionService->leaderboardBaseQuery()
            ->orderByDesc('reputation_points')
            ->orderByDesc('approved_contributions_count')
            ->orderByDesc('contributions_count')
            ->take(5)
            ->get()
            ->values()
            ->map(function (User $user, int $index) {
                return (object) [
                    'rank' => $index + 1,
                    'name' => $user->name,
                    'email' => $user->email,
                    'avatar' => $user->avatar,
                    'reputation_points' => (int) $user->reputation_points,
                    'approved_contributions_count' => (int) $user->approved_contributions_count,
                    'rejected_contributions_count' => (int) $user->rejected_contributions_count,
                    'level' => $this->contributionReputationService->levelForPoints((int) $user->reputation_points),
                ];
            });

        return view('dashboard', compact(
            'totalProducts',
            'pendingContributions',
            'approvedToday',
            'totalUsers',
            'notificationsSentToday',
            'scheduledNotifications',
            'contributionsChart',
            'userGrowthChart',
            'recentContributions',
            'activityFeed',
            'topContributors'
        ));
    }
}
