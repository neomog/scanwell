<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductContribution;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class DashboardController extends Controller
{
//    public function index()
//    {
//        $stats = Cache::remember('dashboard.stats', 60, function () {
//
//            // ========== KPI CARDS ==========
//            $totalProducts = Product::count();
//
//            $pendingContributions = ProductContribution::where('status', 'pending')->count();
//
//            $approvedToday = ProductContribution::where('status', 'approved')
//                ->whereDate('updated_at', Carbon::today())
//                ->count();
//
//            $totalUsers = User::count();
//
//            // ========== CHART DATA ==========
//            $contributionsChart = ProductContribution::selectRaw('DATE(created_at) as date, COUNT(*) as count')
//                ->where('created_at', '>=', Carbon::now()->subDays(7))
//                ->groupBy('date')
//                ->orderBy('date')
//                ->get();
//
//            // ========== RECENT CONTRIBUTIONS ==========
//            $recentContributions = ProductContribution::with('user')
//                ->latest()
//                ->limit(10)
//                ->get();
//
//            // ========== ACTIVITY FEED ==========
//            $activityFeed = collect();
//
//            // Recent contributions activity
//            foreach ($recentContributions as $item) {
//                $activityFeed->push((object)[
//                    'message' => ($item->user->name ?? 'Someone') . ' submitted a contribution',
//                    'time' => $item->created_at->diffForHumans(),
//                ]);
//            }
//
//            // Recent approvals
//            $recentApproved = ProductContribution::where('status', 'approved')
//                ->latest('updated_at')
//                ->limit(5)
//                ->get();
//
//            foreach ($recentApproved as $item) {
//                $activityFeed->push((object)[
//                    'message' => 'A contribution was approved',
//                    'time' => $item->updated_at->diffForHumans(),
//                ]);
//            }
//
//            // Sort activity by latest
//            $activityFeed = $activityFeed->sortByDesc('time')->values();
//
//            // ========== USER GROWTH CHART ==========
//            $userGrowthChart = User::selectRaw('DATE(created_at) as date, COUNT(*) as count')
//                ->where('created_at', '>=', Carbon::now()->subDays(30))
//                ->groupBy('date')
//                ->orderBy('date')
//                ->get();
//
//
//            return [
//                'totalProducts' => $totalProducts,
//                'pendingContributions' => $pendingContributions,
//                'approvedToday' => $approvedToday,
//                'totalUsers' => $totalUsers,
//                'contributionsChart' => $contributionsChart,
//                'recentContributions' => $recentContributions,
//                'activityFeed' => $activityFeed,
//                'userGrowthChart' => $userGrowthChart,
//            ];
//        });
//
//        return view('dashboard', $stats);
//    }
    public function index()
    {
        // Basic Stats
        $totalProducts = Product::count();
        $pendingContributions = productContribution::where('status', 'pending')->count();
        $approvedToday = productContribution::where('status', 'approved')
            ->whereDate('created_at', Carbon::today())
            ->count();
        $totalUsers = User::count();

        // Chart Data - Last 7 days
        $contributionsChart = collect();
        $userGrowthChart = collect();

        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i);

            // Contributions data
            $contributionsChart->push((object)[
                'date' => $date->format('M d'),
                'count' => productContribution::whereDate('created_at', $date)->count()
            ]);

            // Users data
            $userGrowthChart->push((object)[
                'date' => $date->format('M d'),
                'count' => User::whereDate('created_at', $date)->count()
            ]);
        }

        // Recent Contributions
        $recentContributions = productContribution::with('user')
            ->latest()
            ->take(5)
            ->get();

        // Activity Feed (combine recent contributions and user registrations)
        $recentActivities = collect();

        // Add recent contributions to activity feed
        productContribution::with('user')
            ->latest()
            ->take(10)
            ->get()
            ->each(function ($contribution) use (&$recentActivities) {
                $recentActivities->push((object)[
                    'message' => $contribution->user->name . ' submitted a ' . $contribution->change_type . ' contribution for ' . ($contribution->product_name ?? 'a product'),
                    'time' => $contribution->created_at->diffForHumans(),
                    'type' => 'contribution'
                ]);
            });

        // Add recent user registrations to activity feed
        User::latest()
            ->take(10)
            ->get()
            ->each(function ($user) use (&$recentActivities) {
                $recentActivities->push((object)[
                    'message' => 'New user registered: ' . $user->name,
                    'time' => $user->created_at->diffForHumans(),
                    'type' => 'user'
                ]);
            });

        // Sort by time (most recent first) and take top 10
        $activityFeed = $recentActivities->sortByDesc(function($activity) {
            return strtotime($activity->time);
        })->take(10);

        return view('dashboard', compact(
            'totalProducts',
            'pendingContributions',
            'approvedToday',
            'totalUsers',
            'contributionsChart',
            'userGrowthChart',
            'recentContributions',
            'activityFeed'
        ));
    }
}
