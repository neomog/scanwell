<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800">Subscription Details</h2>
                <p class="text-sm text-gray-500 mt-1">Review billing state and take admin actions for this subscriber.</p>
            </div>
            <a href="{{ route('admin.subscriptions.index') }}" class="inline-flex items-center rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition">
                Back to subscriptions
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h3 class="text-2xl font-semibold text-gray-900">{{ $subscription->user?->name }}</h3>
                            <p class="text-sm text-gray-500 mt-1">{{ $subscription->user?->email }}</p>
                        </div>
                        <span class="inline-flex rounded-full bg-gray-100 px-3 py-1 text-sm font-semibold text-gray-700">
                            {{ ucfirst(str_replace('_', ' ', $subscription->status)) }}
                        </span>
                    </div>

                    <div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="rounded-xl bg-gray-50 p-4">
                            <p class="text-xs uppercase tracking-wide text-gray-400">Plan</p>
                            <p class="mt-2 text-lg font-semibold text-gray-900">{{ $subscription->plan?->name }}</p>
                            <p class="text-sm text-gray-500 mt-1">{{ $subscription->price?->name ?? 'Included option' }}</p>
                        </div>
                        <div class="rounded-xl bg-gray-50 p-4">
                            <p class="text-xs uppercase tracking-wide text-gray-400">Amount</p>
                            <p class="mt-2 text-lg font-semibold text-gray-900">{{ strtoupper($subscription->currency) }} {{ number_format(($subscription->amount ?? 0) / 100, 2) }}</p>
                            <p class="text-sm text-gray-500 mt-1">{{ ucfirst($subscription->provider) }} managed</p>
                        </div>
                        <div class="rounded-xl bg-gray-50 p-4">
                            <p class="text-xs uppercase tracking-wide text-gray-400">Current period</p>
                            <p class="mt-2 text-lg font-semibold text-gray-900">{{ $subscription->current_period_starts_at?->format('M d, Y') ?? 'N/A' }}</p>
                            <p class="text-sm text-gray-500 mt-1">to {{ $subscription->current_period_ends_at?->format('M d, Y') ?? 'No expiry' }}</p>
                        </div>
                        <div class="rounded-xl bg-gray-50 p-4">
                            <p class="text-xs uppercase tracking-wide text-gray-400">Cancellation / refund</p>
                            <p class="mt-2 text-lg font-semibold text-gray-900">{{ $subscription->canceled_at?->format('M d, Y') ?? 'Not canceled' }}</p>
                            <p class="text-sm text-gray-500 mt-1">{{ $subscription->refunded_at?->format('M d, Y') ?? 'No refunds recorded' }}</p>
                        </div>
                    </div>

                    <div class="mt-6 rounded-2xl border border-gray-100 p-5">
                        <h4 class="text-sm font-semibold text-gray-900">Provider references</h4>
                        <dl class="mt-4 space-y-3 text-sm">
                            <div class="flex justify-between gap-4">
                                <dt class="text-gray-500">Subscription ID</dt>
                                <dd class="font-mono text-gray-900">{{ $subscription->stripe_subscription_id ?? 'N/A' }}</dd>
                            </div>
                            <div class="flex justify-between gap-4">
                                <dt class="text-gray-500">Customer ID</dt>
                                <dd class="font-mono text-gray-900">{{ $subscription->stripe_customer_id ?? 'N/A' }}</dd>
                            </div>
                            <div class="flex justify-between gap-4">
                                <dt class="text-gray-500">Payment intent</dt>
                                <dd class="font-mono text-gray-900">{{ $subscription->stripe_payment_intent_id ?? 'N/A' }}</dd>
                            </div>
                            <div class="flex justify-between gap-4">
                                <dt class="text-gray-500">Invoice ID</dt>
                                <dd class="font-mono text-gray-900">{{ $subscription->stripe_invoice_id ?? 'N/A' }}</dd>
                            </div>
                        </dl>
                    </div>

                    @php($pendingChange = data_get($subscription->metadata, 'pending_change'))
                    @if($pendingChange)
                        <div class="mt-6 rounded-2xl border border-amber-200 bg-amber-50 p-5">
                            <h4 class="text-sm font-semibold text-amber-900">Scheduled change</h4>
                            <p class="mt-2 text-sm text-amber-800">
                                {{ $pendingChange['plan_name'] ?? 'Pending plan change' }}
                                @if(!empty($pendingChange['price_name']))
                                    / {{ $pendingChange['price_name'] }}
                                @endif
                                effective {{ !empty($pendingChange['effective_at']) ? \Illuminate\Support\Carbon::parse($pendingChange['effective_at'])->format('M d, Y') : 'at the end of the billing cycle' }}.
                            </p>
                        </div>
                    @endif
                </div>

                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 space-y-6">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">Admin actions</h3>
                        <p class="text-sm text-gray-500 mt-1">Cancel or refund this subscriber when needed.</p>
                    </div>

                    <div class="space-y-3">
                        <form method="POST" action="{{ route('admin.subscriptions.cancel', $subscription) }}" class="space-y-3">
                            @csrf
                            <label class="flex items-center gap-3 rounded-xl border border-gray-200 px-4 py-3">
                                <input type="checkbox" name="immediately" value="1" class="rounded border-gray-300 text-[#1FA774] focus:ring-[#1FA774]">
                                <span>
                                    <span class="block text-sm font-medium text-gray-900">Cancel immediately</span>
                                    <span class="block text-xs text-gray-500">Otherwise the cancellation is scheduled for period end.</span>
                                </span>
                            </label>
                            <button type="submit" class="w-full rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 transition">
                                Cancel subscription
                            </button>
                        </form>

                        <form method="POST" action="{{ route('admin.subscriptions.refund', $subscription) }}" class="space-y-3 rounded-2xl border border-gray-100 p-4">
                            @csrf
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Refund amount (optional)</label>
                                <input type="number" min="1" name="amount" class="w-full rounded-lg border-gray-300 focus:border-[#1FA774] focus:ring-[#1FA774]" placeholder="Leave empty for full local amount">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Stripe refund reason</label>
                                <select name="reason" class="w-full rounded-lg border-gray-300 focus:border-[#1FA774] focus:ring-[#1FA774]">
                                    <option value="">No reason</option>
                                    <option value="requested_by_customer">Requested by customer</option>
                                    <option value="duplicate">Duplicate</option>
                                    <option value="fraudulent">Fraudulent</option>
                                </select>
                            </div>
                            <button type="submit" class="w-full rounded-lg bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-black transition">
                                Refund payment
                            </button>
                        </form>
                    </div>

                    <div class="rounded-2xl bg-gray-50 p-4">
                        <h4 class="text-sm font-semibold text-gray-900">Subscriber profile</h4>
                        <p class="text-sm text-gray-500 mt-1">Open the user profile for broader account context.</p>
                        <a href="{{ route('admin.users.show', $subscription->user) }}" class="inline-flex items-center mt-3 text-sm font-medium text-[#1FA774] hover:text-[#0D8B5E] transition">
                            View user profile
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
