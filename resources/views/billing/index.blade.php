<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800">Subscription & Billing</h2>
                <p class="text-sm text-gray-500 mt-1">Manage your plan, pricing, and access to premium features.</p>
            </div>
            <div class="text-sm text-gray-500">
                Registered users start on the <span class="font-semibold text-gray-700">Free</span> plan.
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            @if($checkoutState === 'success')
                <div class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-green-800">
                    Checkout completed. Stripe will confirm the subscription shortly.
                </div>
            @elseif($checkoutState === 'cancelled')
                <div class="rounded-xl border border-yellow-200 bg-yellow-50 px-4 py-3 text-yellow-800">
                    Checkout was canceled before payment completion.
                </div>
            @endif

            @unless($stripeConfigured)
                <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-amber-900">
                    Stripe is not configured yet. Plan management is available, but paid checkout is disabled until `STRIPE_KEY`, `STRIPE_SECRET`, and `STRIPE_WEBHOOK_SECRET` are set.
                </div>
            @endunless

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                    <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                        <div>
                            <p class="text-sm uppercase tracking-wide text-gray-400">Current plan</p>
                            <h3 class="text-3xl font-bold text-gray-900 mt-2">{{ $currentSubscription->plan?->name }}</h3>
                            <p class="text-sm text-gray-500 mt-2">{{ $currentSubscription->plan?->description }}</p>
                        </div>
                        <div class="flex gap-2">
                            <span class="inline-flex items-center rounded-full bg-emerald-50 px-3 py-1 text-sm font-medium text-emerald-700">
                                {{ ucfirst(str_replace('_', ' ', $currentSubscription->status)) }}
                            </span>
                            @if($currentSubscription->isPaid())
                                <span class="inline-flex items-center rounded-full bg-blue-50 px-3 py-1 text-sm font-medium text-blue-700">
                                    Paid
                                </span>
                            @else
                                <span class="inline-flex items-center rounded-full bg-gray-100 px-3 py-1 text-sm font-medium text-gray-700">
                                    Default
                                </span>
                            @endif
                        </div>
                    </div>

                    <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="rounded-xl bg-gray-50 p-4">
                            <p class="text-xs uppercase tracking-wide text-gray-400">Billing option</p>
                            <p class="mt-2 text-lg font-semibold text-gray-900">
                                {{ $currentSubscription->price?->name ?? 'Included' }}
                            </p>
                            <p class="text-sm text-gray-500 mt-1">
                                {{ $currentSubscription->price?->formattedAmount() ?? 'USD 0.00' }}
                            </p>
                        </div>
                        <div class="rounded-xl bg-gray-50 p-4">
                            <p class="text-xs uppercase tracking-wide text-gray-400">Current period end</p>
                            <p class="mt-2 text-lg font-semibold text-gray-900">
                                {{ $currentSubscription->current_period_ends_at?->format('M d, Y') ?? 'No expiry' }}
                            </p>
                            <p class="text-sm text-gray-500 mt-1">
                                {{ $currentSubscription->ends_at?->format('M d, Y') ?? 'Auto-renews while active' }}
                            </p>
                        </div>
                        <div class="rounded-xl bg-gray-50 p-4">
                            <p class="text-xs uppercase tracking-wide text-gray-400">Monthly scan balance</p>
                            <p class="mt-2 text-lg font-semibold text-gray-900">
                                {{ $remainingMonthlyScans === null ? 'Unlimited' : $remainingMonthlyScans }}
                            </p>
                            <p class="text-sm text-gray-500 mt-1">
                                {{ $remainingMonthlyScans === null ? 'No monthly cap on this plan.' : 'Remaining this month.' }}
                            </p>
                        </div>
                    </div>

                    @if($currentSubscription->isPaid() && $currentSubscription->stripe_subscription_id)
                        <div class="mt-6 border-t border-gray-100 pt-6 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                            <div>
                                <h4 class="text-sm font-semibold text-gray-900">Cancel subscription</h4>
                                <p class="text-sm text-gray-500">Canceling keeps access until the current billing period ends.</p>
                            </div>
                            <form method="POST" action="{{ route('billing.cancel') }}">
                                @csrf
                                <button
                                    type="submit"
                                    class="inline-flex items-center rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 transition"
                                >
                                    Cancel plan
                                </button>
                            </form>
                        </div>
                    @endif
                </div>

                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                    <h3 class="text-lg font-semibold text-gray-900">Plan access</h3>
                    <p class="text-sm text-gray-500 mt-1">What your current subscription unlocks.</p>
                    <div class="mt-5 space-y-3">
                        @foreach($featureCatalog as $featureKey => $definition)
                            @php($value = data_get($currentSubscription->plan?->features ?? [], $featureKey))
                            <div class="flex items-start justify-between gap-4 rounded-xl bg-gray-50 px-4 py-3">
                                <div>
                                    <p class="text-sm font-medium text-gray-900">{{ $definition['label'] }}</p>
                                    <p class="text-xs text-gray-500">{{ $featureKey }}</p>
                                </div>
                                <div class="text-sm font-semibold {{ is_bool($value) ? ($value ? 'text-emerald-700' : 'text-gray-500') : 'text-gray-900' }}">
                                    @if(is_bool($value))
                                        {{ $value ? 'Included' : 'Not included' }}
                                    @elseif($value === null || $value === '')
                                        Unlimited
                                    @else
                                        {{ $value }}
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                @foreach($plans as $plan)
                    <div class="bg-white rounded-2xl shadow-sm border {{ $currentSubscription->plan?->is($plan) ? 'border-[#1FA774]' : 'border-gray-100' }} p-6 flex flex-col">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <h3 class="text-2xl font-semibold text-gray-900">{{ $plan->name }}</h3>
                                <p class="text-sm text-gray-500 mt-2">{{ $plan->description }}</p>
                            </div>
                            @if($plan->is_default)
                                <span class="inline-flex rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-700">Default</span>
                            @endif
                        </div>

                        <div class="mt-5 space-y-2">
                            @foreach($featureCatalog as $featureKey => $definition)
                                @php($value = data_get($plan->features ?? [], $featureKey))
                                <div class="flex items-center justify-between text-sm">
                                    <span class="text-gray-600">{{ $definition['label'] }}</span>
                                    <span class="font-medium text-gray-900">
                                        @if(is_bool($value))
                                            {{ $value ? 'Yes' : 'No' }}
                                        @elseif($value === null || $value === '')
                                            Unlimited
                                        @else
                                            {{ $value }}
                                        @endif
                                    </span>
                                </div>
                            @endforeach
                        </div>

                        <div class="mt-6 space-y-3">
                            @forelse($plan->prices as $price)
                                <div class="rounded-xl border border-gray-100 p-4">
                                    <div class="flex items-center justify-between gap-3">
                                        <div>
                                            <p class="font-semibold text-gray-900">{{ $price->name }}</p>
                                            <p class="text-sm text-gray-500">
                                                {{ $price->formattedAmount() }} / {{ $price->billing_interval_count > 1 ? $price->billing_interval_count.' '.$price->billing_interval.'s' : $price->billing_interval }}
                                            </p>
                                            @if($price->trial_days > 0)
                                                <p class="text-xs text-emerald-700 mt-1">{{ $price->trial_days }} day trial</p>
                                            @endif
                                        </div>
                                        @if($currentSubscription->price?->is($price) || ($currentSubscription->plan?->is($plan) && !$currentSubscription->isPaid() && $price->isFree()))
                                            <span class="inline-flex rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">Current</span>
                                        @elseif($price->isFree())
                                            <span class="inline-flex rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-700">Included</span>
                                        @else
                                            <form method="POST" action="{{ route('billing.checkout', $price) }}">
                                                @csrf
                                                <button
                                                    type="submit"
                                                    class="inline-flex items-center rounded-lg bg-[#1FA774] px-4 py-2 text-sm font-medium text-white hover:bg-[#0D8B5E] transition disabled:cursor-not-allowed disabled:bg-gray-300"
                                                    @disabled(!$stripeConfigured)
                                                >
                                                    Upgrade
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </div>
                            @empty
                                <div class="rounded-xl border border-dashed border-gray-200 p-4 text-sm text-gray-500">
                                    No active pricing configured for this plan.
                                </div>
                            @endforelse
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</x-app-layout>
