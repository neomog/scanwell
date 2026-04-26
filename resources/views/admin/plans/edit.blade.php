<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800">Edit {{ $plan->name }}</h2>
                <p class="text-sm text-gray-500 mt-1">Adjust access rules and pricing for this plan.</p>
            </div>
            <div class="flex gap-3">
                <a href="{{ route('admin.plans.prices.create', $plan) }}" class="inline-flex items-center rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition">Add price</a>
                <a href="{{ route('admin.plans.index') }}" class="inline-flex items-center rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition">Back</a>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <form method="POST" action="{{ route('admin.plans.update', $plan) }}" class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 space-y-6">
                @csrf
                @include('admin.plans.partials.form-fields', ['plan' => $plan, 'featureCatalog' => $featureCatalog])

                <div class="flex justify-end gap-3">
                    <a href="{{ route('admin.plans.index') }}" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition">Cancel</a>
                    <button type="submit" class="rounded-lg bg-[#1FA774] px-4 py-2 text-sm font-medium text-white hover:bg-[#0D8B5E] transition">Save changes</button>
                </div>
            </form>

            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">Pricing entries</h3>
                        <p class="text-sm text-gray-500 mt-1">Each plan can have multiple billable options.</p>
                    </div>
                    <a href="{{ route('admin.plans.prices.create', $plan) }}" class="inline-flex items-center rounded-lg bg-[#1FA774] px-4 py-2 text-sm font-medium text-white hover:bg-[#0D8B5E] transition">
                        Add price
                    </a>
                </div>

                <div class="mt-5 space-y-3">
                    @forelse($plan->prices as $price)
                        <div class="rounded-xl border border-gray-100 p-4 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                            <div>
                                <div class="flex items-center gap-2">
                                    <p class="font-semibold text-gray-900">{{ $price->name }}</p>
                                    @if($price->is_default)
                                        <span class="inline-flex rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-semibold text-gray-700">Default</span>
                                    @endif
                                    @unless($price->is_active)
                                        <span class="inline-flex rounded-full bg-red-50 px-2 py-0.5 text-[11px] font-semibold text-red-700">Inactive</span>
                                    @endunless
                                </div>
                                <p class="text-sm text-gray-500 mt-1">
                                    {{ $price->formattedAmount() }} / {{ $price->billing_interval_count > 1 ? $price->billing_interval_count.' '.$price->billing_interval.'s' : $price->billing_interval }}
                                </p>
                            </div>
                            <div class="flex flex-wrap items-center gap-2">
                                <a href="{{ route('admin.prices.edit', $price) }}" class="text-sm font-medium text-blue-600 hover:text-blue-800 transition">
                                    Edit price
                                </a>
                                <form method="POST" action="{{ route('admin.prices.toggle-active', $price) }}">
                                    @csrf
                                    <button type="submit" class="inline-flex items-center rounded-lg border border-amber-300 px-3 py-1.5 text-sm font-medium text-amber-800 hover:bg-amber-50 transition">
                                        {{ $price->is_active ? 'Archive' : 'Activate' }}
                                    </button>
                                </form>
                                @if($price->subscriptions()->count() === 0)
                                    <form method="POST" action="{{ route('admin.prices.destroy', $price) }}" onsubmit="return confirm('Delete this unused price permanently?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="inline-flex items-center rounded-lg border border-red-300 px-3 py-1.5 text-sm font-medium text-red-700 hover:bg-red-50 transition">
                                            Delete
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="rounded-xl border border-dashed border-gray-200 px-4 py-5 text-sm text-gray-500">
                            This plan has no pricing entries yet.
                        </div>
                    @endforelse
                </div>
            </div>

            @unless($plan->is_default)
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                    <h3 class="text-lg font-semibold text-gray-900">Plan lifecycle</h3>
                    <p class="text-sm text-gray-500 mt-1">Archive plans to retire them safely. Hard delete is only available for unused plans with no prices or subscriptions.</p>
                    <div class="mt-4 flex flex-wrap gap-3">
                        <form method="POST" action="{{ route('admin.plans.toggle-active', $plan) }}">
                            @csrf
                            <button type="submit" class="inline-flex items-center rounded-lg border border-amber-300 px-4 py-2 text-sm font-medium text-amber-800 hover:bg-amber-50 transition">
                                {{ $plan->is_active ? 'Archive plan' : 'Activate plan' }}
                            </button>
                        </form>
                        @if($plan->prices_count === 0 && $plan->subscriptions_count === 0)
                            <form method="POST" action="{{ route('admin.plans.destroy', $plan) }}" onsubmit="return confirm('Delete this unused plan permanently?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="inline-flex items-center rounded-lg border border-red-300 px-4 py-2 text-sm font-medium text-red-700 hover:bg-red-50 transition">
                                    Delete plan
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            @endunless
        </div>
    </div>
</x-app-layout>
