<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-2">Slug</label>
        <input
            type="text"
            name="slug"
            value="{{ old('slug', $plan?->slug) }}"
            class="w-full rounded-lg border-gray-300 focus:border-[#1FA774] focus:ring-[#1FA774]"
            placeholder="free"
            required
        >
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-2">Name</label>
        <input
            type="text"
            name="name"
            value="{{ old('name', $plan?->name) }}"
            class="w-full rounded-lg border-gray-300 focus:border-[#1FA774] focus:ring-[#1FA774]"
            placeholder="Free"
            required
        >
    </div>
</div>

<div>
    <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
    <textarea
        name="description"
        rows="3"
        class="w-full rounded-lg border-gray-300 focus:border-[#1FA774] focus:ring-[#1FA774]"
        placeholder="Brief plan summary"
    >{{ old('description', $plan?->description) }}</textarea>
</div>

<div class="grid grid-cols-1 md:grid-cols-3 gap-6">
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-2">Display order</label>
        <input
            type="number"
            min="0"
            name="display_order"
            value="{{ old('display_order', $plan?->display_order ?? 0) }}"
            class="w-full rounded-lg border-gray-300 focus:border-[#1FA774] focus:ring-[#1FA774]"
        >
    </div>
    <label class="flex items-center gap-3 rounded-xl border border-gray-200 px-4 py-3">
        <input type="checkbox" name="is_active" value="1" class="rounded border-gray-300 text-[#1FA774] focus:ring-[#1FA774]" {{ old('is_active', $plan?->is_active ?? true) ? 'checked' : '' }}>
        <span>
            <span class="block text-sm font-medium text-gray-900">Active plan</span>
            <span class="block text-xs text-gray-500">Can be offered to users.</span>
        </span>
    </label>
    <label class="flex items-center gap-3 rounded-xl border border-gray-200 px-4 py-3">
        <input type="checkbox" name="is_default" value="1" class="rounded border-gray-300 text-[#1FA774] focus:ring-[#1FA774]" {{ old('is_default', $plan?->is_default ?? false) ? 'checked' : '' }}>
        <span>
            <span class="block text-sm font-medium text-gray-900">Default plan</span>
            <span class="block text-xs text-gray-500">Assigned to new users automatically.</span>
        </span>
    </label>
</div>

<div class="border-t border-gray-100 pt-6">
    <div class="mb-4">
        <h3 class="text-lg font-semibold text-gray-900">Feature access</h3>
        <p class="text-sm text-gray-500 mt-1">These flags drive the app’s plan gating logic.</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        @foreach($featureCatalog as $featureKey => $definition)
            @php($type = $definition['type'] ?? 'boolean')
            @php($currentValue = data_get(old('features', $plan?->features ?? []), $featureKey))
            <div class="rounded-xl border border-gray-200 p-4">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-sm font-medium text-gray-900">{{ $definition['label'] }}</p>
                        <p class="text-xs text-gray-500 mt-1">{{ $featureKey }}</p>
                    </div>
                    @if($type === 'integer')
                        <input
                            type="number"
                            min="0"
                            name="features[{{ str_replace('.', '][', $featureKey) }}]"
                            value="{{ $currentValue }}"
                            class="w-28 rounded-lg border-gray-300 text-sm focus:border-[#1FA774] focus:ring-[#1FA774]"
                            placeholder="Unlimited"
                        >
                    @else
                        <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                            <input
                                type="checkbox"
                                name="features[{{ str_replace('.', '][', $featureKey) }}]"
                                value="1"
                                class="rounded border-gray-300 text-[#1FA774] focus:ring-[#1FA774]"
                                {{ $currentValue ? 'checked' : '' }}
                            >
                            Enabled
                        </label>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</div>
