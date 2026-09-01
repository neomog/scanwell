<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800">Billing Overview</h2>
                <p class="text-sm text-gray-500 mt-1">Review invoice, payment, refund, and failure activity across subscriptions.</p>
            </div>
            @include('admin.billing.partials.nav')
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-3 xl:grid-cols-6 gap-4">
                <div class="rounded-2xl bg-white border border-gray-100 shadow-sm p-5">
                    <p class="text-sm text-gray-500">Paid invoices</p>
                    <p class="mt-2 text-3xl font-bold text-gray-900">{{ $metrics['paid_invoices'] }}</p>
                </div>
                <div class="rounded-2xl bg-white border border-gray-100 shadow-sm p-5">
                    <p class="text-sm text-gray-500">Open invoices</p>
                    <p class="mt-2 text-3xl font-bold text-amber-700">{{ $metrics['open_invoices'] }}</p>
                </div>
                <div class="rounded-2xl bg-white border border-gray-100 shadow-sm p-5">
                    <p class="text-sm text-gray-500">Failed payments</p>
                    <p class="mt-2 text-3xl font-bold text-red-700">{{ $metrics['failed_payments'] }}</p>
                </div>
                <div class="rounded-2xl bg-white border border-gray-100 shadow-sm p-5">
                    <p class="text-sm text-gray-500">Refunds issued</p>
                    <p class="mt-2 text-3xl font-bold text-gray-900">{{ $metrics['refunds_issued'] }}</p>
                </div>
                <div class="rounded-2xl bg-white border border-gray-100 shadow-sm p-5">
                    <p class="text-sm text-gray-500">Revenue</p>
                    <p class="mt-2 text-3xl font-bold text-emerald-700">USD {{ number_format($metrics['revenue_cents'] / 100, 2) }}</p>
                </div>
                <div class="rounded-2xl bg-white border border-gray-100 shadow-sm p-5">
                    <p class="text-sm text-gray-500">Refund total</p>
                    <p class="mt-2 text-3xl font-bold text-gray-900">USD {{ number_format($metrics['refund_total_cents'] / 100, 2) }}</p>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-100">
                        <h3 class="text-lg font-semibold text-gray-900">Recent failed payments</h3>
                    </div>
                    <div class="divide-y divide-gray-100">
                        @forelse($recentFailures as $invoice)
                            <div class="px-6 py-4 flex items-center justify-between gap-4">
                                <div>
                                    <p class="text-sm font-medium text-gray-900">{{ $invoice->user?->name }}</p>
                                    <p class="text-sm text-gray-500">{{ $invoice->subscription?->plan?->name ?? 'Unknown plan' }}</p>
                                </div>
                                <div class="text-right">
                                    <p class="text-sm font-semibold text-red-700">{{ strtoupper($invoice->currency) }} {{ number_format($invoice->amount_due / 100, 2) }}</p>
                                    <p class="text-xs text-gray-500">{{ $invoice->failed_at?->format('M d, Y H:i') }}</p>
                                </div>
                            </div>
                        @empty
                            <div class="px-6 py-10 text-center text-sm text-gray-500">No failed payments recorded yet.</div>
                        @endforelse
                    </div>
                </div>

                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-100">
                        <h3 class="text-lg font-semibold text-gray-900">Recent refunds</h3>
                    </div>
                    <div class="divide-y divide-gray-100">
                        @forelse($recentRefunds as $refund)
                            <div class="px-6 py-4 flex items-center justify-between gap-4">
                                <div>
                                    <p class="text-sm font-medium text-gray-900">{{ $refund->user?->name }}</p>
                                    <p class="text-sm text-gray-500">{{ $refund->reason ?: 'No reason provided' }}</p>
                                </div>
                                <div class="text-right">
                                    <p class="text-sm font-semibold text-gray-900">{{ strtoupper($refund->currency) }} {{ number_format($refund->amount / 100, 2) }}</p>
                                    <p class="text-xs text-gray-500">{{ $refund->refunded_at?->format('M d, Y H:i') }}</p>
                                </div>
                            </div>
                        @empty
                            <div class="px-6 py-10 text-center text-sm text-gray-500">No refunds recorded yet.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
