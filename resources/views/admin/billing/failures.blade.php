<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3">
            <div>
                <h2 class="font-semibold text-xl text-gray-800">Failed Payments</h2>
                <p class="text-sm text-gray-500 mt-1">Operational queue for failed invoice payment attempts and outstanding due amounts.</p>
            </div>
            @include('admin.billing.partials.nav')
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
                <form method="GET" class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Search by user name or email" class="rounded-lg border-gray-300 focus:border-[#1FA774] focus:ring-[#1FA774]">
                    <button type="submit" class="rounded-lg bg-[#1FA774] px-4 py-2 text-sm font-medium text-white hover:bg-[#0D8B5E] transition">Apply filters</button>
                </form>
            </div>

            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-100">
                        <thead class="bg-gray-50">
                            <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                <th class="px-6 py-3">Subscriber</th>
                                <th class="px-6 py-3">Plan</th>
                                <th class="px-6 py-3">Invoice</th>
                                <th class="px-6 py-3">Due amount</th>
                                <th class="px-6 py-3">Failed at</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($failedInvoices as $invoice)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4">
                                        <div class="text-sm font-medium text-gray-900">{{ $invoice->user?->name }}</div>
                                        <div class="text-sm text-gray-500">{{ $invoice->user?->email }}</div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm font-medium text-gray-900">{{ $invoice->subscription?->plan?->name ?? 'Unknown plan' }}</div>
                                        <div class="text-xs text-gray-500">{{ $invoice->subscription?->price?->name ?? 'Included' }}</div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm font-medium text-gray-900">{{ $invoice->provider_invoice_id }}</div>
                                        <div class="text-xs text-gray-500">{{ $invoice->provider_payment_intent_id ?? 'No payment intent' }}</div>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-red-700 font-medium">
                                        {{ strtoupper($invoice->currency) }} {{ number_format($invoice->amount_due / 100, 2) }}
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-500">
                                        {{ $invoice->failed_at?->format('M d, Y H:i') ?? 'N/A' }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-6 py-10 text-center text-sm text-gray-500">No failed payments found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($failedInvoices->hasPages())
                    <div class="px-6 py-4 border-t border-gray-100">
                        {{ $failedInvoices->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
