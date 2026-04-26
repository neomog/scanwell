<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800">Subscriptions</h2>
                <p class="text-sm text-gray-500 mt-1">View active subscriptions, cancellation states, and billing actions.</p>
            </div>
            <a href="{{ route('admin.plans.index') }}" class="inline-flex items-center rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition">
                Manage plans
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="rounded-2xl bg-white border border-gray-100 shadow-sm p-5">
                    <p class="text-sm text-gray-500">Current</p>
                    <p class="mt-2 text-3xl font-bold text-gray-900">{{ $metrics['current'] }}</p>
                </div>
                <div class="rounded-2xl bg-white border border-gray-100 shadow-sm p-5">
                    <p class="text-sm text-gray-500">Active</p>
                    <p class="mt-2 text-3xl font-bold text-emerald-700">{{ $metrics['active'] }}</p>
                </div>
                <div class="rounded-2xl bg-white border border-gray-100 shadow-sm p-5">
                    <p class="text-sm text-gray-500">Canceling</p>
                    <p class="mt-2 text-3xl font-bold text-amber-700">{{ $metrics['canceling'] }}</p>
                </div>
                <div class="rounded-2xl bg-white border border-gray-100 shadow-sm p-5">
                    <p class="text-sm text-gray-500">Ended / refunded</p>
                    <p class="mt-2 text-3xl font-bold text-gray-700">{{ $metrics['ended'] }}</p>
                </div>
            </div>

            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4">
                <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-3">
                    <input
                        type="text"
                        name="search"
                        value="{{ request('search') }}"
                        placeholder="Search by user name or email"
                        class="rounded-lg border-gray-300 focus:border-[#1FA774] focus:ring-[#1FA774]"
                    >
                    <select name="status" class="rounded-lg border-gray-300 focus:border-[#1FA774] focus:ring-[#1FA774]">
                        <option value="current" {{ request('status', 'current') === 'current' ? 'selected' : '' }}>Current only</option>
                        <option value="all" {{ request('status') === 'all' ? 'selected' : '' }}>All statuses</option>
                        <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
                        <option value="trialing" {{ request('status') === 'trialing' ? 'selected' : '' }}>Trialing</option>
                        <option value="canceling" {{ request('status') === 'canceling' ? 'selected' : '' }}>Canceling</option>
                        <option value="canceled" {{ request('status') === 'canceled' ? 'selected' : '' }}>Canceled</option>
                        <option value="refunded" {{ request('status') === 'refunded' ? 'selected' : '' }}>Refunded</option>
                    </select>
                    <select name="plan" class="rounded-lg border-gray-300 focus:border-[#1FA774] focus:ring-[#1FA774]">
                        <option value="">All plans</option>
                        @foreach($plans as $plan)
                            <option value="{{ $plan->slug }}" {{ request('plan') === $plan->slug ? 'selected' : '' }}>{{ $plan->name }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="rounded-lg bg-[#1FA774] px-4 py-2 text-sm font-medium text-white hover:bg-[#0D8B5E] transition">
                        Apply filters
                    </button>
                </form>
            </div>

            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-100">
                        <thead class="bg-gray-50">
                            <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                <th class="px-6 py-3">Subscriber</th>
                                <th class="px-6 py-3">Plan</th>
                                <th class="px-6 py-3">Price</th>
                                <th class="px-6 py-3">Status</th>
                                <th class="px-6 py-3">Renewal / End</th>
                                <th class="px-6 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($subscriptions as $subscription)
                                <tr class="hover:bg-gray-50 transition">
                                    <td class="px-6 py-4">
                                        <div class="text-sm font-medium text-gray-900">{{ $subscription->user?->name }}</div>
                                        <div class="text-sm text-gray-500">{{ $subscription->user?->email }}</div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm font-medium text-gray-900">{{ $subscription->plan?->name }}</div>
                                        <div class="text-xs text-gray-500">{{ $subscription->provider }}</div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm text-gray-900">{{ $subscription->price?->name ?? 'Included' }}</div>
                                        <div class="text-sm text-gray-500">{{ $subscription->amount ? strtoupper($subscription->currency).' '.number_format($subscription->amount / 100, 2) : 'USD 0.00' }}</div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold
                                            {{ $subscription->status === 'active' ? 'bg-emerald-50 text-emerald-700' : '' }}
                                            {{ $subscription->status === 'trialing' ? 'bg-blue-50 text-blue-700' : '' }}
                                            {{ $subscription->status === 'canceling' ? 'bg-amber-50 text-amber-700' : '' }}
                                            {{ in_array($subscription->status, ['canceled', 'replaced', 'refunded'], true) ? 'bg-gray-100 text-gray-700' : '' }}">
                                            {{ ucfirst(str_replace('_', ' ', $subscription->status)) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-500">
                                        {{ $subscription->current_period_ends_at?->format('M d, Y') ?? $subscription->ends_at?->format('M d, Y') ?? 'No expiry' }}
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <a href="{{ route('admin.subscriptions.show', $subscription) }}" class="text-sm font-medium text-[#1FA774] hover:text-[#0D8B5E] transition">
                                            View
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-6 py-10 text-center text-sm text-gray-500">No subscriptions found for the selected filters.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($subscriptions->hasPages())
                    <div class="px-6 py-4 border-t border-gray-100">
                        {{ $subscriptions->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
