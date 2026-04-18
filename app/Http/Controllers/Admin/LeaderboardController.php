<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ContributionReputationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class LeaderboardController extends Controller
{
    public function __construct(
        protected ContributionReputationService $contributionReputationService
    ) {
    }

    public function index(Request $request)
    {
        $levels = collect(config('contributions.levels', []))->values();
        $query = User::query()
            ->where(function (Builder $builder) {
                $builder->where('reputation_points', '>', 0)
                    ->orWhere('approved_contributions_count', '>', 0);
            })
            ->withCount('contributions')
            ->withMax('contributions', 'updated_at');

        if ($search = trim((string) $request->get('search'))) {
            $query->where(function (Builder $builder) use ($search) {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($level = $request->get('level')) {
            $this->applyLevelFilter($query, (string) $level, $levels);
        }

        $sort = (string) $request->get('sort', 'reputation');

        $filteredSummaryQuery = clone $query;
        $summary = [
            'contributors' => (clone $filteredSummaryQuery)->count(),
            'reputation_points' => (int) (clone $filteredSummaryQuery)->sum('reputation_points'),
            'approved_contributions' => (int) (clone $filteredSummaryQuery)->sum('approved_contributions_count'),
            'top_score' => (int) ((clone $filteredSummaryQuery)->max('reputation_points') ?? 0),
        ];

        $this->applySort($query, $sort);

        $leaders = $query
            ->paginate(20)
            ->withQueryString();

        $leaders->getCollection()->transform(function (User $user) {
            $user->level = $this->contributionReputationService->levelForPoints((int) $user->reputation_points);

            return $user;
        });

        return view('admin.leaderboard.index', [
            'leaders' => $leaders,
            'levels' => $levels,
            'sort' => $sort,
            'summary' => $summary,
        ]);
    }

    protected function applyLevelFilter(Builder $query, string $selectedLevel, Collection $levels): void
    {
        $selectedIndex = $levels->search(function (array $level) use ($selectedLevel) {
            return ($level['name'] ?? null) === $selectedLevel;
        });

        if ($selectedIndex === false) {
            return;
        }

        $selected = $levels->get($selectedIndex);
        $next = $levels->get($selectedIndex + 1);
        $minPoints = (int) ($selected['points'] ?? 0);

        $query->where('reputation_points', '>=', $minPoints);

        if ($next) {
            $query->where('reputation_points', '<', (int) ($next['points'] ?? 0));
        }
    }

    protected function applySort(Builder $query, string $sort): void
    {
        match ($sort) {
            'approved' => $query
                ->orderByDesc('approved_contributions_count')
                ->orderByDesc('reputation_points'),
            'recent' => $query
                ->orderByDesc('contributions_max_updated_at')
                ->orderByDesc('reputation_points'),
            'rejected' => $query
                ->orderByDesc('rejected_contributions_count')
                ->orderByDesc('reputation_points'),
            default => $query
                ->orderByDesc('reputation_points')
                ->orderByDesc('approved_contributions_count'),
        };
    }
}
