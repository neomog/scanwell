<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-2">Price label</label>
        <input
            type="text"
            name="name"
            value="{{ old('name', $price?->name) }}"
            class="w-full rounded-lg border-gray-300 focus:border-[#1FA774] focus:ring-[#1FA774]"
            placeholder="Monthly"
            required
        >
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-2">Amount (smallest currency unit)</label>
        <input
            type="number"
            min="0"
            name="amount"
            value="{{ old('amount', $price?->amount ?? 0) }}"
            class="w-full rounded-lg border-gray-300 focus:border-[#1FA774] focus:ring-[#1FA774]"
            required
        >
        <p class="mt-1 text-xs text-gray-500">Example: `1900` = USD 19.00.</p>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-4 gap-6">
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-2">Currency</label>
        <input
            type="text"
            name="currency"
            value="{{ old('currency', $price?->currency ?? 'usd') }}"
            class="w-full rounded-lg border-gray-300 uppercase focus:border-[#1FA774] focus:ring-[#1FA774]"
            maxlength="3"
            required
        >
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-2">Interval</label>
        <select name="billing_interval" class="w-full rounded-lg border-gray-300 focus:border-[#1FA774] focus:ring-[#1FA774]">
            <option value="month" {{ old('billing_interval', $price?->billing_interval ?? 'month') === 'month' ? 'selected' : '' }}>Month</option>
            <option value="year" {{ old('billing_interval', $price?->billing_interval ?? 'month') === 'year' ? 'selected' : '' }}>Year</option>
        </select>
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-2">Interval count</label>
        <input
            type="number"
            min="1"
            max="12"
            name="billing_interval_count"
            value="{{ old('billing_interval_count', $price?->billing_interval_count ?? 1) }}"
            class="w-full rounded-lg border-gray-300 focus:border-[#1FA774] focus:ring-[#1FA774]"
            required
        >
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-2">Trial days</label>
        <input
            type="number"
            min="0"
            max="60"
            name="trial_days"
            value="{{ old('trial_days', $price?->trial_days ?? 0) }}"
            class="w-full rounded-lg border-gray-300 focus:border-[#1FA774] focus:ring-[#1FA774]"
        >
    </div>
</div>

<div>
    <label class="block text-sm font-medium text-gray-700 mb-2">Stripe price ID</label>
    <input
        type="text"
        name="stripe_price_id"
        value="{{ old('stripe_price_id', $price?->stripe_price_id) }}"
        class="w-full rounded-lg border-gray-300 focus:border-[#1FA774] focus:ring-[#1FA774]"
        placeholder="price_..."
    >
    <p class="mt-1 text-xs text-gray-500">Optional. Leave empty to let checkout use the local price definition.</p>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <label class="flex items-center gap-3 rounded-xl border border-gray-200 px-4 py-3">
        <input type="checkbox" name="is_active" value="1" class="rounded border-gray-300 text-[#1FA774] focus:ring-[#1FA774]" {{ old('is_active', $price?->is_active ?? true) ? 'checked' : '' }}>
        <span>
            <span class="block text-sm font-medium text-gray-900">Active price</span>
            <span class="block text-xs text-gray-500">Users can purchase this option.</span>
        </span>
    </label>
    <label class="flex items-center gap-3 rounded-xl border border-gray-200 px-4 py-3">
        <input type="checkbox" name="is_default" value="1" class="rounded border-gray-300 text-[#1FA774] focus:ring-[#1FA774]" {{ old('is_default', $price?->is_default ?? false) ? 'checked' : '' }}>
        <span>
            <span class="block text-sm font-medium text-gray-900">Default price</span>
            <span class="block text-xs text-gray-500">Primary option shown first for this plan.</span>
        </span>
    </label>
</div>
