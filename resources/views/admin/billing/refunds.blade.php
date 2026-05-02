<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3">
            <div>
                <h2 class="font-semibold text-xl text-gray-800">Refunds</h2>
                <p class="text-sm text-gray-500 mt-1">Track refund requests created through the admin dashboard and Stripe.</p>
            </div>
            @include('admin.billing.partials.nav')
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
                <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Search by user name or email" class="rounded-lg border-gray-300 focus:border-[#1FA774] focus:ring-[#1FA774]">
                    <select name="status" class="rounded-lg border-gray-300 focus:border-[#1FA774] focus:ring-[#1FA774]">
                        <option value="">All statuses</option>
                        <option value="succeeded" {{ request('status') === 'succeeded' ? 'selected' : '' }}>Succeeded</option>
                        <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Pending</option>
                        <option value="failed" {{ request('status') === 'failed' ? 'selected' : '' }}>Failed</option>
                    </select>
                    <button type="submit" class="rounded-lg bg-[#1FA774] px-4 py-2 text-sm font-medium text-white hover:bg-[#0D8B5E] transition">Apply filters</button>
                </form>
            </div>

            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-100">
                        <thead class="bg-gray-50">
                            <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                <th class="px-6 py-3">Subscriber</th>
                                <th class="px-6 py-3">Refund</th>
                                <th class="px-6 py-3">Invoice</th>
                                <th class="px-6 py-3">Reason</th>
                                <th class="px-6 py-3">Amount</th>
                                <th class="px-6 py-3">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($refunds as $refund)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4">
                                        <div class="text-sm font-medium text-gray-900">{{ $refund->user?->name }}</div>
                                        <div class="text-sm text-gray-500">{{ $refund->user?->email }}</div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm font-medium text-gray-900">{{ $refund->provider_refund_id }}</div>
                                        <div class="text-xs text-gray-500">{{ $refund->requested_by_type ?? 'system' }}</div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm font-medium text-gray-900">{{ $refund->invoice?->provider_invoice_id ?? 'No invoice linked' }}</div>
                                        <div class="text-xs text-gray-500">{{ $refund->subscription?->plan?->name ?? 'Unknown plan' }}</div>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-500">
                                        {{ $refund->reason ?: 'No reason provided' }}
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-500">
                                        {{ strtoupper($refund->currency) }} {{ number_format($refund->amount / 100, 2) }}
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $refund->status === 'succeeded' ? 'bg-emerald-50 text-emerald-700' : ($refund->status === 'failed' ? 'bg-red-50 text-red-700' : 'bg-gray-100 text-gray-700') }}">
                                            {{ ucfirst($refund->status) }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-6 py-10 text-center text-sm text-gray-500">No refunds found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($refunds->hasPages())
                    <div class="px-6 py-4 border-t border-gray-100">
                        {{ $refunds->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
