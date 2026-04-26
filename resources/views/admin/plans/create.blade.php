<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800">Create Plan</h2>
                <p class="text-sm text-gray-500 mt-1">Add a new subscription plan and its feature access rules.</p>
            </div>
            <a href="{{ route('admin.plans.index') }}" class="text-sm font-medium text-gray-600 hover:text-gray-900 transition">Back to plans</a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
            <form method="POST" action="{{ route('admin.plans.store') }}" class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 space-y-6">
                @csrf
                @include('admin.plans.partials.form-fields', ['plan' => null, 'featureCatalog' => $featureCatalog])

                <div class="flex justify-end gap-3">
                    <a href="{{ route('admin.plans.index') }}" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition">Cancel</a>
                    <button type="submit" class="rounded-lg bg-[#1FA774] px-4 py-2 text-sm font-medium text-white hover:bg-[#0D8B5E] transition">Create plan</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
