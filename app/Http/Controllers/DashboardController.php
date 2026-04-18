<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductContribution;
use App\Models\User;
use App\Services\ContributionReputationService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class DashboardController extends Controller
{
    public function __construct(
        protected ContributionReputationService $contributionReputationService
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

        $activityFeed = $recentActivities
            ->sortByDesc('timestamp')
            ->take(10)
            ->values();

        $topContributors = User::query()
            ->where(function (Builder $query) {
                $query->where('reputation_points', '>', 0)
                    ->orWhere('approved_contributions_count', '>', 0);
            })
            ->orderByDesc('reputation_points')
            ->orderByDesc('approved_contributions_count')
            ->take(5)
            ->get()
            ->values()
            ->map(function (User $user, int $index) {
                return (object) [
                    'rank' => $index + 1,
                    'name' => $user->name,
                    'email' => $user->email,
                    'avatar' => $user->avatar,
                    'reputation_points' => $user->reputation_points,
                    'approved_contributions_count' => $user->approved_contributions_count,
                    'rejected_contributions_count' => $user->rejected_contributions_count,
                    'level' => $this->contributionReputationService->levelForPoints((int) $user->reputation_points),
                ];
            });

        return view('dashboard', compact(
            'totalProducts',
            'pendingContributions',
            'approvedToday',
            'totalUsers',
            'contributionsChart',
            'userGrowthChart',
            'recentContributions',
            'activityFeed',
            'topContributors'
        ));
    }
}
