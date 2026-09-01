<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Contributor Leaderboard</h2>
                <p class="text-sm text-gray-500">Track trusted contributors, approval volume, and reputation progress.</p>
            </div>
        </div>
    </x-slot>

    <div class="max-w-7xl mx-auto space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="bg-white rounded-xl shadow-sm p-5 border border-gray-100">
                <p class="text-sm text-gray-500">Contributors</p>
                <p class="text-2xl font-bold text-gray-900 mt-2">{{ number_format($summary['contributors']) }}</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-5 border border-gray-100">
                <p class="text-sm text-gray-500">Reputation Points</p>
                <p class="text-2xl font-bold text-gray-900 mt-2">{{ number_format($summary['reputation_points']) }}</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-5 border border-gray-100">
                <p class="text-sm text-gray-500">Approved Contributions</p>
                <p class="text-2xl font-bold text-gray-900 mt-2">{{ number_format($summary['approved_contributions']) }}</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-5 border border-gray-100">
                <p class="text-sm text-gray-500">Top Score</p>
                <p class="text-2xl font-bold text-gray-900 mt-2">{{ number_format($summary['top_score']) }}</p>
            </div>
        </div>

        <form method="GET" class="bg-white rounded-xl shadow-sm p-4 grid grid-cols-1 md:grid-cols-4 gap-4">
            <input
                type="text"
                name="search"
                value="{{ request('search') }}"
                placeholder="Search contributor name or email"
                class="rounded-lg border-gray-300"
            >
            <select name="level" class="rounded-lg border-gray-300">
                <option value="">All levels</option>
                @foreach($levels as $level)
                    <option value="{{ $level['name'] }}" @selected(request('level') === $level['name'])>
                        {{ $level['name'] }}
                    </option>
                @endforeach
            </select>
            <select name="sort" class="rounded-lg border-gray-300">
                <option value="reputation" @selected($sort === 'reputation')>Sort by reputation</option>
                <option value="approved" @selected($sort === 'approved')>Sort by approvals</option>
                <option value="recent" @selected($sort === 'recent')>Sort by recent activity</option>
                <option value="rejected" @selected($sort === 'rejected')>Sort by rejections</option>
            </select>
            <div class="flex items-center gap-3">
                <button class="px-4 py-2 rounded-lg bg-gray-900 text-white text-sm">Filter</button>
                <a href="{{ route('admin.leaderboard.index') }}" class="text-sm text-gray-500 hover:text-gray-700">Reset</a>
            </div>
        </form>

        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-500">
                <tr>
                    <th class="px-6 py-3 text-left">Rank</th>
                    <th class="px-6 py-3 text-left">Contributor</th>
                    <th class="px-6 py-3 text-left">Level</th>
                    <th class="px-6 py-3 text-left">Points</th>
                    <th class="px-6 py-3 text-left">Approved</th>
                    <th class="px-6 py-3 text-left">Rejected</th>
                    <th class="px-6 py-3 text-left">Total Submissions</th>
                    <th class="px-6 py-3 text-left">Last Contribution</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                @forelse($leaders as $leader)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4">
                            {{ (($leaders->currentPage() - 1) * $leaders->perPage()) + $loop->iteration }}
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-full bg-gray-200 flex items-center justify-center text-sm font-semibold text-gray-700 overflow-hidden">
                                    @if($leader->avatar)
                                        <img src="{{ $leader->avatar }}" alt="{{ $leader->name }}" class="w-full h-full object-cover">
                                    @else
                                        {{ strtoupper(substr($leader->name, 0, 1)) }}
                                    @endif
                                </div>
                                <div>
                                    <div class="font-medium text-gray-900">{{ $leader->name }}</div>
                                    <div class="text-xs text-gray-500">{{ $leader->email }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <span class="px-2 py-1 rounded-full bg-emerald-50 text-emerald-700 text-xs font-medium">
                                {{ $leader->level }}
                            </span>
                        </td>
                        <td class="px-6 py-4 font-semibold text-gray-900">{{ number_format($leader->reputation_points) }}</td>
                        <td class="px-6 py-4 text-gray-700">{{ number_format($leader->approved_contributions_count) }}</td>
                        <td class="px-6 py-4 text-gray-700">{{ number_format($leader->rejected_contributions_count) }}</td>
                        <td class="px-6 py-4 text-gray-700">{{ number_format($leader->contributions_count) }}</td>
                        <td class="px-6 py-4 text-gray-500">
                            {{ $leader->contributions_max_updated_at ? \Illuminate\Support\Carbon::parse($leader->contributions_max_updated_at)->diffForHumans() : 'No activity yet' }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-6 py-10 text-center text-gray-500">No contributors match the current filters.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        {{ $leaders->links() }}
    </div>
</x-app-layout>
