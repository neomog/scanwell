<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div class="flex items-center space-x-4">
                <a href="{{ route('admin.users.index') }}" class="text-gray-500 hover:text-[#1FA774] transition">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                </a>
                <h2 class="font-semibold text-xl text-gray-800">User Details</h2>
            </div>
            <div class="flex items-center space-x-2">
                <span class="text-sm text-gray-500">User ID:</span>
                <span class="text-sm font-mono bg-gray-100 px-2 py-1 rounded">{{ substr($user->id, 0, 8) }}...</span>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            <!-- User Profile Card -->
            <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                <div class="bg-gradient-to-r from-[#1FA774] to-[#0D8B5E] h-24"></div>
                <div class="relative px-6 pb-6">
                    <div class="flex flex-col md:flex-row items-start md:items-center -mt-12 mb-6">
                        <div class="w-24 h-24 rounded-full bg-white p-1 shadow-lg">
                            <div class="w-full h-full rounded-full bg-gradient-to-br from-[#1FA774] to-[#0D8B5E] flex items-center justify-center text-white text-3xl font-bold">
                                {{ strtoupper(substr($user->name, 0, 2)) }}
                            </div>
                        </div>
                        <div class="mt-4 md:mt-0 md:ml-6 flex-1">
                            <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                                <div>
                                    <h3 class="text-2xl font-bold text-white">{{ $user->name }}</h3>
                                    <p class="text-gray-500">{{ $user->email }}</p>
                                </div>
                                <div class="mt-3 md:mt-0 flex items-center space-x-2">
                                    @if($user->is_banned)
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-red-100 text-red-800">
                                            <span class="w-2 h-2 bg-red-500 rounded-full mr-2"></span>
                                            Banned
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800">
                                            <span class="w-2 h-2 bg-green-500 rounded-full mr-2"></span>
                                            Active
                                        </span>
                                    @endif

                                    @if($user->role === 'admin')
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-purple-100 text-purple-800">
                                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0zm6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                            </svg>
                                            Administrator
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- User Info Grid -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                        <div class="flex items-center space-x-3 p-3 bg-gray-50 rounded-lg">
                            <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                                <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                                </svg>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">Email Status</p>
                                <p class="text-sm font-semibold text-gray-900">
                                    {{ $user->email_verified_at ? 'Verified' : 'Unverified' }}
                                </p>
                            </div>
                        </div>

                        <div class="flex items-center space-x-3 p-3 bg-gray-50 rounded-lg">
                            <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center">
                                <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">Member Since</p>
                                <p class="text-sm font-semibold text-gray-900">
                                    {{ $user->created_at->format('F d, Y') }}
                                </p>
                            </div>
                        </div>

                        <div class="flex items-center space-x-3 p-3 bg-gray-50 rounded-lg">
                            <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                                <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">Last Updated</p>
                                <p class="text-sm font-semibold text-gray-900">
                                    {{ $user->updated_at->format('F d, Y') }}
                                </p>
                            </div>
                        </div>

                        <div class="flex items-center space-x-3 p-3 bg-gray-50 rounded-lg">
                            <div class="w-10 h-10 bg-orange-100 rounded-lg flex items-center justify-center">
                                <svg class="w-5 h-5 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                </svg>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">User Role</p>
                                <p class="text-sm font-semibold text-gray-900 capitalize">
                                    {{ $user->role }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Stats Section -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Contribution Stats -->
                <div class="bg-white rounded-xl shadow-sm p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-800">Contributions Overview</h3>
                        <div class="w-10 h-10 bg-green-50 rounded-lg flex items-center justify-center">
                            <svg class="w-5 h-5 text-[#1FA774]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-4">
                        <div class="text-center">
                            <div class="text-2xl font-bold text-gray-900">{{ $totalContributions }}</div>
                            <p class="text-xs text-gray-500 mt-1">Total</p>
                        </div>
                        <div class="text-center">
                            <div class="text-2xl font-bold text-yellow-600">{{ $pendingContributions }}</div>
                            <p class="text-xs text-gray-500 mt-1">Pending</p>
                        </div>
                        <div class="text-center">
                            <div class="text-2xl font-bold text-green-600">{{ $approvedContributions }}</div>
                            <p class="text-xs text-gray-500 mt-1">Approved</p>
                        </div>
                    </div>

                    <!-- Progress Bar -->
                    @if($totalContributions > 0)
                        <div class="mt-4">
                            <div class="flex justify-between text-xs text-gray-500 mb-1">
                                <span>Approval Rate</span>
                                <span>{{ round(($approvedContributions / $totalContributions) * 100) }}%</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div class="bg-green-500 h-2 rounded-full" style="width: {{ ($approvedContributions / $totalContributions) * 100 }}%"></div>
                            </div>
                        </div>
                    @endif
                </div>

                <!-- Scan Stats -->
                <div class="bg-white rounded-xl shadow-sm p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-800">Scan Activity</h3>
                        <div class="w-10 h-10 bg-blue-50 rounded-lg flex items-center justify-center">
                            <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            </svg>
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-4">
                        <div class="text-center">
                            <div class="text-2xl font-bold text-gray-900">{{ $totalScans }}</div>
                            <p class="text-xs text-gray-500 mt-1">Total Scans</p>
                        </div>
                        <div class="text-center">
                            <div class="text-2xl font-bold text-blue-600">{{ $todayScans }}</div>
                            <p class="text-xs text-gray-500 mt-1">Today</p>
                        </div>
                        <div class="text-center">
                            <div class="text-2xl font-bold text-purple-600">{{ $thisMonthScans }}</div>
                            <p class="text-xs text-gray-500 mt-1">This Month</p>
                        </div>
                    </div>

                    <!-- Trend Indicator -->
                    @if($thisMonthScans > 0)
                        <div class="mt-4 p-3 bg-gray-50 rounded-lg">
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-gray-600">Monthly Average</span>
                                <span class="font-semibold text-gray-900">{{ round($thisMonthScans / 30) }} scans/day</span>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Recent Activity Preview -->
            @if(isset($recentContributions) && $recentContributions->count() > 0)
                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center">
                        <h3 class="text-lg font-semibold text-gray-800">Recent Contributions</h3>
{{--                        <a href="{{ route('admin.contributions', ['user' => $user->id]) }}" class="text-sm text-[#1FA774] hover:text-[#0D8B5E]">--}}
                        <a href="#" class="text-sm text-[#1FA774] hover:text-[#0D8B5E]">
                            View All →
                        </a>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500">Product</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500">Type</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500">Date</th>
                            </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                            @foreach($recentContributions as $contribution)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-3">
                                        <div class="text-sm font-medium text-gray-900">{{ $contribution->product_name ?? 'N/A' }}</div>
                                        <div class="text-xs text-gray-500">{{ $contribution->barcode }}</div>
                                    </td>
                                    <td class="px-6 py-3">
                                    <span class="inline-flex px-2 py-1 text-xs rounded-full {{ $contribution->change_type === 'add' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800' }}">
                                        {{ ucfirst($contribution->change_type) }}
                                    </span>
                                    </td>
                                    <td class="px-6 py-3">
                                    <span class="inline-flex items-center px-2 py-1 text-xs rounded-full
                                        @if($contribution->status === 'approved') bg-green-100 text-green-800
                                        @elseif($contribution->status === 'pending') bg-yellow-100 text-yellow-800
                                        @else bg-red-100 text-red-800 @endif">
                                        <span class="w-1.5 h-1.5 rounded-full mr-1
                                            @if($contribution->status === 'approved') bg-green-500
                                            @elseif($contribution->status === 'pending') bg-yellow-500
                                            @else bg-red-500 @endif"></span>
                                        {{ ucfirst($contribution->status) }}
                                    </span>
                                    </td>
                                    <td class="px-6 py-3 text-sm text-gray-500">
                                        {{ $contribution->created_at->diffForHumans() }}
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            <!-- Action Buttons -->
            <div class="bg-white rounded-xl shadow-sm p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Admin Actions</h3>
                <div class="flex flex-wrap gap-3">
                    <!-- Ban/Unban Button -->
                    <form method="POST" action="{{ route('admin.users.ban', $user) }}" class="inline">
                        @csrf
                        @method('POST')
                        <input type="hidden" name="_token" value="{{ csrf_token() }}" />
                        <button type="submit"
                                class="inline-flex items-center px-4 py-2 {{ $user->is_banned ? 'bg-green-600 hover:bg-green-700' : 'bg-red-600 hover:bg-red-700' }} text-white rounded-lg transition shadow-sm">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                @if($user->is_banned)
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                @else
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"></path>
                                @endif
                            </svg>
                            {{ $user->is_banned ? 'Unban User' : 'Ban User' }}
                        </button>
                    </form>

                    <!-- Verify Email Button -->
                    @if(!$user->email_verified_at)
                        <form method="POST" action="{{ route('admin.users.verify', $user) }}" class="inline">
                            @csrf
                            @method('POST')
                            <input type="hidden" name="_token" value="{{ csrf_token() }}" />
                            <button type="submit"
                                    class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition shadow-sm">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                Verify Email
                            </button>
                        </form>
                    @endif

                    <!-- Reset Password Button -->
                    <form method="POST" action="{{ route('admin.users.reset-password', $user) }}" class="inline">
                        @csrf
                        @method('POST')
                        <input type="hidden" name="_token" value="{{ csrf_token() }}" />
                        <button type="submit"
                                onclick="return confirm('Are you sure you want to reset this user\'s password? They will receive an email with reset instructions.')"
                                class="inline-flex items-center px-4 py-2 bg-yellow-600 hover:bg-yellow-700 text-white rounded-lg transition shadow-sm">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"></path>
                            </svg>
                            Reset Password
                        </button>
                    </form>

                    <!-- Make Admin / Remove Admin -->
                    @if(auth()->id() !== $user->id)
                        <form method="POST" action="{{ route('admin.users.toggle-role', $user) }}" class="inline">
                            @csrf
                            @method('POST')
                            <input type="hidden" name="_token" value="{{ csrf_token() }}" />
                            <button type="submit"
                                    class="inline-flex items-center px-4 py-2 {{ $user->role === 'admin' ? 'bg-gray-600 hover:bg-gray-700' : 'bg-purple-600 hover:bg-purple-700' }} text-white rounded-lg transition shadow-sm">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                                </svg>
                                {{ $user->role === 'admin' ? 'Remove Admin' : 'Make Admin' }}
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
