<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800">Edit {{ $price->name }} Pricing</h2>
                <p class="text-sm text-gray-500 mt-1">Update billing details for the {{ $plan->name }} plan.</p>
            </div>
            <a href="{{ route('admin.plans.edit', $plan) }}" class="text-sm font-medium text-gray-600 hover:text-gray-900 transition">Back to plan</a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <form method="POST" action="{{ route('admin.prices.update', $price) }}" class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 space-y-6">
                @csrf
                @include('admin.plans.partials.price-fields', ['price' => $price, 'plan' => $plan])

                <div class="flex justify-end gap-3">
                    <a href="{{ route('admin.plans.edit', $plan) }}" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition">Cancel</a>
                    <button type="submit" class="rounded-lg bg-[#1FA774] px-4 py-2 text-sm font-medium text-white hover:bg-[#0D8B5E] transition">Save price</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
