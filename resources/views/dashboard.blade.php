<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Dashboard Overview
            </h2>
            <div class="text-sm text-gray-500">
                {{ now()->format('F j, Y') }}
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <div class="bg-white rounded-xl shadow-sm p-6 border-l-4 border-[#1FA774] hover:shadow-md transition">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-500 mb-1">Total Products</p>
                            <p class="text-2xl font-bold text-gray-800">{{ $totalProducts ?? 0 }}</p>
                            <p class="text-xs text-gray-400 mt-2">In database</p>
                        </div>
                        <div class="w-12 h-12 bg-green-50 rounded-xl flex items-center justify-center">
                            <svg class="w-6 h-6 text-[#1FA774]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                            </svg>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-sm p-6 border-l-4 border-yellow-500 hover:shadow-md transition">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-500 mb-1">Pending Contributions</p>
                            <p class="text-2xl font-bold text-yellow-600">{{ $pendingContributions ?? 0 }}</p>
                            <p class="text-xs text-gray-400 mt-2">Awaiting review</p>
                        </div>
                        <div class="w-12 h-12 bg-yellow-50 rounded-xl flex items-center justify-center">
                            <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-sm p-6 border-l-4 border-green-500 hover:shadow-md transition">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-500 mb-1">Approved Today</p>
                            <p class="text-2xl font-bold text-green-600">{{ $approvedToday ?? 0 }}</p>
                            <p class="text-xs text-gray-400 mt-2">Last 24 hours</p>
                        </div>
                        <div class="w-12 h-12 bg-green-50 rounded-xl flex items-center justify-center">
                            <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-sm p-6 border-l-4 border-purple-500 hover:shadow-md transition">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-500 mb-1">Total Users</p>
                            <p class="text-2xl font-bold text-gray-800">{{ $totalUsers ?? 0 }}</p>
                            <p class="text-xs text-gray-400 mt-2">Registered accounts</p>
                        </div>
                        <div class="w-12 h-12 bg-purple-50 rounded-xl flex items-center justify-center">
                            <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                            </svg>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="bg-white rounded-xl shadow-sm p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="font-semibold text-gray-800">Contributions Trend</h3>
                        <div class="flex items-center space-x-2">
                            <span class="w-3 h-3 bg-[#1FA774] rounded-full"></span>
                            <span class="text-xs text-gray-500">Last 7 days</span>
                        </div>
                    </div>
                    <div class="h-64">
                        <canvas id="contributionsChart"></canvas>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-sm p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="font-semibold text-gray-800">User Growth</h3>
                        <div class="flex items-center space-x-2">
                            <span class="w-3 h-3 bg-blue-500 rounded-full"></span>
                            <span class="text-xs text-gray-500">Last 7 days</span>
                        </div>
                    </div>
                    <div class="h-64">
                        <canvas id="usersChart"></canvas>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center">
                        <h3 class="font-semibold text-gray-800">Recent Contributions</h3>
                        @if(isset($recentContributions) && $recentContributions->count() > 0)
                            <a href="{{ route('admin.contributions.index') }}" class="text-xs text-[#1FA774] hover:text-[#0D8B5E] font-medium">
                                View all ->
                            </a>
                        @endif
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50">
                            <tr class="text-left text-gray-500">
                                <th class="px-6 py-3 text-xs font-medium">User</th>
                                <th class="px-6 py-3 text-xs font-medium">Product</th>
                                <th class="px-6 py-3 text-xs font-medium">Status</th>
                                <th class="px-6 py-3 text-xs font-medium">Date</th>
                            </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                            @forelse($recentContributions ?? [] as $item)
                                <tr class="hover:bg-gray-50 transition">
                                    <td class="px-6 py-3">
                                        <div class="flex items-center space-x-2">
                                            <div class="w-6 h-6 rounded-full bg-gray-200 flex items-center justify-center text-xs font-semibold">
                                                {{ substr($item->user->name ?? 'U', 0, 1) }}
                                            </div>
                                            <span class="text-sm text-gray-700">{{ $item->user->name ?? 'Unknown' }}</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-3">
                                        <div>
                                            <p class="text-sm font-medium text-gray-900">{{ $item->product_name ?? 'N/A' }}</p>
                                            <p class="text-xs text-gray-500">{{ $item->barcode ?? '' }}</p>
                                        </div>
                                    </td>
                                    <td class="px-6 py-3">
                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium
                                            {{ $item->status == 'pending' ? 'bg-yellow-100 text-yellow-700' : '' }}
                                            {{ $item->status == 'approved' ? 'bg-green-100 text-green-700' : '' }}
                                            {{ $item->status == 'rejected' ? 'bg-red-100 text-red-700' : '' }}">
                                            <span class="w-1.5 h-1.5 rounded-full mr-1
                                                {{ $item->status == 'pending' ? 'bg-yellow-500' : '' }}
                                                {{ $item->status == 'approved' ? 'bg-green-500' : '' }}
                                                {{ $item->status == 'rejected' ? 'bg-red-500' : '' }}"></span>
                                            {{ ucfirst($item->status) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-3 text-sm text-gray-500">
                                        {{ isset($item->created_at) ? $item->created_at->diffForHumans() : 'N/A' }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-6 py-8 text-center">
                                        <svg class="w-12 h-12 mx-auto text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                        </svg>
                                        <p class="text-gray-400 text-sm">No contributions yet</p>
                                    </td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-100">
                        <h3 class="font-semibold text-gray-800">Recent Activity</h3>
                    </div>
                    <div class="p-4 space-y-3 max-h-96 overflow-y-auto">
                        @forelse($activityFeed ?? [] as $activity)
                            <div class="flex items-start space-x-3 p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition">
                                <div class="flex-shrink-0">
                                    @if(($activity->type ?? 'default') === 'contribution')
                                        <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
                                            <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                            </svg>
                                        </div>
                                    @elseif(($activity->type ?? 'default') === 'user')
                                        <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                                            <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                            </svg>
                                        </div>
                                    @else
                                        <div class="w-8 h-8 bg-purple-100 rounded-full flex items-center justify-center">
                                            <svg class="w-4 h-4 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                            </svg>
                                        </div>
                                    @endif
                                </div>
                                <div class="flex-1">
                                    <p class="text-sm text-gray-800">{{ $activity->message ?? 'Activity recorded' }}</p>
                                    <p class="text-xs text-gray-400 mt-1">{{ $activity->time ?? now()->diffForHumans() }}</p>
                                </div>
                            </div>
                        @empty
                            <div class="text-center py-8">
                                <svg class="w-12 h-12 mx-auto text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                                </svg>
                                <p class="text-gray-400 text-sm">No recent activity</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                    <div>
                        <h3 class="font-semibold text-gray-800">Top Contributors</h3>
                        <p class="text-xs text-gray-500 mt-1">Most trusted contributors by approval history and reputation</p>
                    </div>
                    <a href="{{ route('admin.leaderboard.index') }}" class="text-xs text-[#1FA774] hover:text-[#0D8B5E] font-medium">
                        Full leaderboard ->
                    </a>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50">
                        <tr class="text-left text-gray-500">
                            <th class="px-6 py-3 text-xs font-medium">Rank</th>
                            <th class="px-6 py-3 text-xs font-medium">Contributor</th>
                            <th class="px-6 py-3 text-xs font-medium">Level</th>
                            <th class="px-6 py-3 text-xs font-medium">Approvals</th>
                            <th class="px-6 py-3 text-xs font-medium text-right">Points</th>
                        </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                        @forelse($topContributors ?? [] as $contributor)
                            <tr class="hover:bg-gray-50 transition">
                                <td class="px-6 py-4">
                                    <span class="inline-flex items-center justify-center w-8 h-8 rounded-full text-xs font-semibold
                                        {{ $contributor->rank === 1 ? 'bg-yellow-100 text-yellow-700' : ($contributor->rank === 2 ? 'bg-gray-100 text-gray-700' : ($contributor->rank === 3 ? 'bg-orange-100 text-orange-700' : 'bg-emerald-50 text-emerald-700')) }}">
                                        {{ $contributor->rank }}
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center space-x-3">
                                        <div class="w-10 h-10 rounded-full bg-gray-200 flex items-center justify-center text-sm font-semibold text-gray-700 overflow-hidden">
                                            @if($contributor->avatar)
                                                <img src="{{ $contributor->avatar }}" alt="{{ $contributor->name }}" class="w-full h-full object-cover">
                                            @else
                                                {{ strtoupper(substr($contributor->name, 0, 1)) }}
                                            @endif
                                        </div>
                                        <div>
                                            <p class="font-medium text-gray-900">{{ $contributor->name }}</p>
                                            <p class="text-xs text-gray-500">{{ $contributor->email }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex px-2.5 py-1 rounded-full bg-emerald-50 text-emerald-700 text-xs font-medium">
                                        {{ $contributor->level }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-gray-700">
                                    {{ number_format($contributor->approved_contributions_count) }}
                                </td>
                                <td class="px-6 py-4 text-right font-semibold text-gray-900">
                                    {{ number_format($contributor->reputation_points) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-10 text-center text-gray-500">
                                    No contributor data yet.
                                </td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <a href="{{ route('admin.contributions.index', ['status' => 'pending']) }}"
                   class="bg-gradient-to-r from-yellow-500 to-yellow-600 rounded-xl shadow-sm p-6 text-white hover:shadow-md transition">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm opacity-90 mb-1">Pending Reviews</p>
                            <p class="text-2xl font-bold">{{ $pendingContributions ?? 0 }}</p>
                        </div>
                        <svg class="w-8 h-8 opacity-80" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <p class="text-sm mt-2 opacity-90">Review pending contributions -></p>
                </a>

                <a href="{{ route('admin.users.index') }}"
                   class="bg-gradient-to-r from-blue-500 to-blue-600 rounded-xl shadow-sm p-6 text-white hover:shadow-md transition">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm opacity-90 mb-1">Total Users</p>
                            <p class="text-2xl font-bold">{{ $totalUsers ?? 0 }}</p>
                        </div>
                        <svg class="w-8 h-8 opacity-80" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                        </svg>
                    </div>
                    <p class="text-sm mt-2 opacity-90">Manage users -></p>
                </a>

                <a href="{{ route('admin.products.index') }}"
                   class="bg-gradient-to-r from-purple-500 to-purple-600 rounded-xl shadow-sm p-6 text-white hover:shadow-md transition">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm opacity-90 mb-1">Total Products</p>
                            <p class="text-2xl font-bold">{{ $totalProducts ?? 0 }}</p>
                        </div>
                        <svg class="w-8 h-8 opacity-80" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                        </svg>
                    </div>
                    <p class="text-sm mt-2 opacity-90">Browse products -></p>
                </a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const contributionsCtx = document.getElementById('contributionsChart');
            if (contributionsCtx) {
                new Chart(contributionsCtx, {
                    type: 'line',
                    data: {
                        labels: @json($contributionsChart->pluck('date') ?? []),
                        datasets: [{
                            label: 'Contributions',
                            data: @json($contributionsChart->pluck('count') ?? []),
                            borderColor: '#1FA774',
                            backgroundColor: 'rgba(31, 167, 116, 0.1)',
                            borderWidth: 2,
                            tension: 0.4,
                            fill: true,
                            pointBackgroundColor: '#1FA774',
                            pointBorderColor: '#fff',
                            pointBorderWidth: 2,
                            pointRadius: 4,
                            pointHoverRadius: 6
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            },
                            tooltip: {
                                mode: 'index',
                                intersect: false
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                grid: {
                                    color: '#E5E7EB'
                                },
                                ticks: {
                                    stepSize: 1
                                }
                            },
                            x: {
                                grid: {
                                    display: false
                                }
                            }
                        }
                    }
                });
            }

            const usersCtx = document.getElementById('usersChart');
            if (usersCtx) {
                new Chart(usersCtx, {
                    type: 'line',
                    data: {
                        labels: @json($userGrowthChart->pluck('date') ?? []),
                        datasets: [{
                            label: 'New Users',
                            data: @json($userGrowthChart->pluck('count') ?? []),
                            borderColor: '#3B82F6',
                            backgroundColor: 'rgba(59, 130, 246, 0.1)',
                            borderWidth: 2,
                            tension: 0.4,
                            fill: true,
                            pointBackgroundColor: '#3B82F6',
                            pointBorderColor: '#fff',
                            pointBorderWidth: 2,
                            pointRadius: 4,
                            pointHoverRadius: 6
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            },
                            tooltip: {
                                mode: 'index',
                                intersect: false
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                grid: {
                                    color: '#E5E7EB'
                                },
                                ticks: {
                                    stepSize: 1
                                }
                            },
                            x: {
                                grid: {
                                    display: false
                                }
                            }
                        }
                    }
                });
            }
        });
    </script>
</x-app-layout>
