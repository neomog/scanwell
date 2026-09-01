<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800">Plan Management</h2>
                <p class="text-sm text-gray-500 mt-1">Edit plan access, pricing, and the default Free / Pro / Team catalog.</p>
            </div>
            <div class="flex gap-3">
                <a href="{{ route('admin.subscriptions.index') }}" class="inline-flex items-center rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition">
                    View subscriptions
                </a>
                <a href="{{ route('admin.plans.create') }}" class="inline-flex items-center rounded-lg bg-[#1FA774] px-4 py-2 text-sm font-medium text-white hover:bg-[#0D8B5E] transition">
                    Add plan
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 grid grid-cols-1 xl:grid-cols-3 gap-6">
            @foreach($plans as $plan)
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <div class="flex items-center gap-2">
                                <h3 class="text-2xl font-semibold text-gray-900">{{ $plan->name }}</h3>
                                @if($plan->is_default)
                                    <span class="inline-flex rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">Default</span>
                                @endif
                            </div>
                            <p class="text-sm text-gray-500 mt-2">{{ $plan->description }}</p>
                        </div>
                        <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $plan->is_active ? 'bg-blue-50 text-blue-700' : 'bg-gray-100 text-gray-600' }}">
                            {{ $plan->is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </div>

                    <div class="mt-5 space-y-2">
                        @foreach($featureCatalog as $featureKey => $definition)
                            @php($value = data_get($plan->features ?? [], $featureKey))
                            <div class="flex items-center justify-between text-sm rounded-lg bg-gray-50 px-3 py-2">
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

                    <div class="mt-6">
                        <div class="flex items-center justify-between">
                            <h4 class="text-sm font-semibold text-gray-900">Pricing</h4>
                            <a href="{{ route('admin.plans.prices.create', $plan) }}" class="text-sm font-medium text-[#1FA774] hover:text-[#0D8B5E] transition">
                                Add price
                            </a>
                        </div>
                        <div class="mt-3 space-y-3">
                            @forelse($plan->prices as $price)
                                <div class="rounded-xl border border-gray-100 p-4">
                                    <div class="flex items-start justify-between gap-4">
                                        <div>
                                            <div class="flex items-center gap-2">
                                                <p class="font-semibold text-gray-900">{{ $price->name }}</p>
                                                @if($price->is_default)
                                                    <span class="inline-flex rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-semibold text-gray-700">Default</span>
                                                @endif
                                            </div>
                                            <p class="text-sm text-gray-500 mt-1">
                                                {{ $price->formattedAmount() }} / {{ $price->billing_interval_count > 1 ? $price->billing_interval_count.' '.$price->billing_interval.'s' : $price->billing_interval }}
                                            </p>
                                            @if($price->stripe_price_id)
                                                <p class="text-xs text-gray-400 mt-1">Stripe: {{ $price->stripe_price_id }}</p>
                                            @endif
                                        </div>
                                        <a href="{{ route('admin.prices.edit', $price) }}" class="text-sm font-medium text-blue-600 hover:text-blue-800 transition">
                                            Edit
                                        </a>
                                    </div>
                                </div>
                            @empty
                                <div class="rounded-xl border border-dashed border-gray-200 px-4 py-5 text-sm text-gray-500">
                                    No prices configured yet.
                                </div>
                            @endforelse
                        </div>
                    </div>

                    <div class="mt-6">
                        <div class="flex flex-wrap gap-2">
                            <a href="{{ route('admin.plans.edit', $plan) }}" class="inline-flex items-center rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition">
                                Edit plan
                            </a>
                            @unless($plan->is_default)
                                <form method="POST" action="{{ route('admin.plans.toggle-active', $plan) }}">
                                    @csrf
                                    <button type="submit" class="inline-flex items-center rounded-lg border border-amber-300 px-4 py-2 text-sm font-medium text-amber-800 hover:bg-amber-50 transition">
                                        {{ $plan->is_active ? 'Archive' : 'Activate' }}
                                    </button>
                                </form>
                            @endunless
                            @if(!$plan->is_default && $plan->prices_count === 0 && $plan->subscriptions_count === 0)
                                <form method="POST" action="{{ route('admin.plans.destroy', $plan) }}" onsubmit="return confirm('Delete this unused plan permanently?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="inline-flex items-center rounded-lg border border-red-300 px-4 py-2 text-sm font-medium text-red-700 hover:bg-red-50 transition">
                                        Delete
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</x-app-layout>
