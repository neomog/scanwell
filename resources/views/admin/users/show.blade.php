<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-4">
                <a href="{{ route('admin.users.index') }}" class="text-gray-500 hover:text-[#1FA774] transition">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                </a>
                <h2 class="font-semibold text-xl text-gray-800">User Details</h2>
            </div>
            <span class="text-sm font-mono bg-gray-100 px-2 py-1 rounded">{{ substr($user->id, 0, 8) }}...</span>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white rounded-xl shadow-sm p-6">
                <div class="flex flex-col md:flex-row md:items-center gap-6">
                    <div class="w-24 h-24 rounded-full bg-gradient-to-br from-[#1FA774] to-[#0D8B5E] flex items-center justify-center text-white text-3xl font-bold">
                        {{ strtoupper(substr($user->name, 0, 2)) }}
                    </div>
                    <div class="flex-1">
                        <h3 class="text-2xl font-bold text-gray-900">{{ $user->name }}</h3>
                        <p class="text-gray-500">{{ $user->email }}</p>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm {{ $user->is_banned ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800' }}">
                                {{ $user->is_banned ? 'Banned' : 'Active' }}
                            </span>
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm bg-purple-100 text-purple-800">
                                {{ $user->roleDefinition?->name ?? ucfirst(str_replace('_', ' ', $user->role)) }}
                            </span>
                        </div>
                    </div>
                    @can('users.manage')
                        <a href="{{ route('admin.users.edit', $user) }}" class="px-4 py-2 bg-[#1FA774] text-white rounded-lg hover:bg-[#0D8B5E] transition">Edit User</a>
                    @endcan
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="bg-white rounded-xl shadow-sm p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">User Overview</h3>
                    <div class="space-y-3 text-sm">
                        <div class="flex justify-between gap-4"><span class="text-gray-500">Joined</span><span class="text-gray-900">{{ $user->created_at->format('F d, Y') }}</span></div>
                        <div class="flex justify-between gap-4"><span class="text-gray-500">Email status</span><span class="text-gray-900">{{ $user->email_verified_at ? 'Verified' : 'Unverified' }}</span></div>
                        <div class="flex justify-between gap-4"><span class="text-gray-500">Total scans</span><span class="text-gray-900">{{ $totalScans }}</span></div>
                        <div class="flex justify-between gap-4"><span class="text-gray-500">Today scans</span><span class="text-gray-900">{{ $todayScans }}</span></div>
                        <div class="flex justify-between gap-4"><span class="text-gray-500">This month scans</span><span class="text-gray-900">{{ $thisMonthScans }}</span></div>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-sm p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Contribution Summary</h3>
                    <div class="grid grid-cols-3 gap-4 text-center">
                        <div><div class="text-2xl font-bold text-gray-900">{{ $totalContributions }}</div><p class="text-xs text-gray-500 mt-1">Total</p></div>
                        <div><div class="text-2xl font-bold text-yellow-600">{{ $pendingContributions }}</div><p class="text-xs text-gray-500 mt-1">Pending</p></div>
                        <div><div class="text-2xl font-bold text-green-600">{{ $approvedContributions }}</div><p class="text-xs text-gray-500 mt-1">Approved</p></div>
                    </div>
                </div>

                @if($canManageSubscriptions || $canViewBilling)
                    <div class="bg-white rounded-xl shadow-sm p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Subscription Overview</h3>
                        <div class="space-y-3 text-sm">
                            <div class="flex justify-between gap-4"><span class="text-gray-500">Plan</span><span class="text-gray-900">{{ $currentSubscription?->plan?->name ?? 'N/A' }}</span></div>
                            <div class="flex justify-between gap-4"><span class="text-gray-500">Status</span><span class="text-gray-900">{{ $currentSubscription ? ucfirst(str_replace('_', ' ', $currentSubscription->status)) : 'N/A' }}</span></div>
                            <div class="flex justify-between gap-4"><span class="text-gray-500">Billing option</span><span class="text-gray-900">{{ $currentSubscription?->price?->name ?? 'Included' }}</span></div>
                            <div class="flex justify-between gap-4"><span class="text-gray-500">Period end</span><span class="text-gray-900">{{ $currentSubscription?->display_expiry_at?->format('M d, Y') ?? 'No expiry' }}</span></div>
                        </div>
                        @if($canManageSubscriptions && $currentSubscription)
                            <a href="{{ route('admin.subscriptions.show', $currentSubscription) }}" class="inline-flex mt-4 text-sm font-medium text-[#1FA774] hover:text-[#0D8B5E]">View subscription details</a>
                        @endif
                    </div>
                @endif
            </div>

            @if($recentContributions->isNotEmpty())
                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-100">
                        <h3 class="text-lg font-semibold text-gray-800">Recent Contributions</h3>
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
                                    <tr>
                                        <td class="px-6 py-3">
                                            <div class="text-sm font-medium text-gray-900">{{ $contribution->product_name ?? 'N/A' }}</div>
                                            <div class="text-xs text-gray-500">{{ $contribution->barcode }}</div>
                                        </td>
                                        <td class="px-6 py-3 text-sm text-gray-700">{{ ucfirst($contribution->change_type) }}</td>
                                        <td class="px-6 py-3 text-sm text-gray-700">{{ ucfirst($contribution->status) }}</td>
                                        <td class="px-6 py-3 text-sm text-gray-500">{{ $contribution->created_at->diffForHumans() }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            @can('users.manage')
                <div class="bg-white rounded-xl shadow-sm p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Admin Actions</h3>
                    <div class="flex flex-wrap gap-3">
                        <form method="POST" action="{{ route('admin.users.ban', $user) }}">
                            @csrf
                            <button type="submit" class="px-4 py-2 {{ $user->is_banned ? 'bg-green-600 hover:bg-green-700' : 'bg-red-600 hover:bg-red-700' }} text-white rounded-lg transition">
                                {{ $user->is_banned ? 'Unban User' : 'Ban User' }}
                            </button>
                        </form>

                        @if(!$user->email_verified_at)
                            <form method="POST" action="{{ route('admin.users.verify', $user) }}">
                                @csrf
                                <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition">Verify Email</button>
                            </form>
                        @endif

                        <form method="POST" action="{{ route('admin.users.reset-password', $user) }}">
                            @csrf
                            <button type="submit" class="px-4 py-2 bg-yellow-600 hover:bg-yellow-700 text-white rounded-lg transition">Reset Password</button>
                        </form>
                    </div>
                </div>
            @endcan

            @if($canManageSubscriptions)
                <div class="bg-white rounded-xl shadow-sm p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Change User Plan</h3>

                    <form method="POST" action="{{ route('admin.users.subscription.change', $user) }}" class="space-y-4">
                        @csrf

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Target plan / price</label>
                                <select name="price_id" class="w-full rounded-lg border-gray-300 focus:border-[#1FA774] focus:ring-[#1FA774]" required>
                                    @foreach($assignablePlans as $plan)
                                        <optgroup label="{{ $plan->name }}">
                                            @foreach($plan->prices as $price)
                                                <option value="{{ $price->id }}" {{ (string) old('price_id', $currentSubscription?->price_id ?? '') === (string) $price->id ? 'selected' : '' }}>
                                                    {{ $plan->name }} / {{ $price->name }} / {{ $price->formattedAmount() }}
                                                </option>
                                            @endforeach
                                        </optgroup>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Transition type</label>
                                <select name="transition_type" class="w-full rounded-lg border-gray-300 focus:border-[#1FA774] focus:ring-[#1FA774]" required>
                                    <option value="billing_now" {{ old('transition_type', $currentSubscription?->provider === 'stripe' ? 'billing_now' : 'manual_override') === 'billing_now' ? 'selected' : '' }}>Billing change now</option>
                                    <option value="billing_next_cycle" {{ old('transition_type') === 'billing_next_cycle' ? 'selected' : '' }}>Billing change next cycle</option>
                                    <option value="manual_override" {{ old('transition_type', $currentSubscription?->provider === 'stripe' ? 'billing_now' : 'manual_override') === 'manual_override' ? 'selected' : '' }}>Manual override</option>
                                </select>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Reason</label>
                            <textarea name="reason" rows="3" class="w-full rounded-lg border-gray-300 focus:border-[#1FA774] focus:ring-[#1FA774]"></textarea>
                        </div>

                        <label class="flex items-start gap-3 rounded-lg border border-gray-200 p-4">
                            <input type="checkbox" name="cancel_current_stripe" value="1" class="mt-1 rounded border-gray-300 text-[#1FA774] focus:ring-[#1FA774]" {{ old('cancel_current_stripe') ? 'checked' : '' }}>
                            <span class="text-sm text-gray-700">Cancel active Stripe subscription immediately when using manual override.</span>
                        </label>

                        <div class="flex justify-end">
                            <button type="submit" class="px-4 py-2 bg-[#1FA774] hover:bg-[#0D8B5E] text-white rounded-lg transition">Apply plan change</button>
                        </div>
                    </form>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
