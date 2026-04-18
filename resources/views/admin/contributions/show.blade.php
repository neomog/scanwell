@php
    $reviewData = $contribution->moderated_data ?? $contribution->new_data ?? [];
    $ingredientLines = collect(data_get($reviewData, 'ingredients', []))->pluck('name')->implode(PHP_EOL);
    $nutritionLines = collect(data_get($reviewData, 'nutrition', []))->map(fn ($value, $key) => $key . ': ' . $value)->implode(PHP_EOL);
    $additiveLines = collect(data_get($reviewData, 'additives', []))->implode(PHP_EOL);
    $allergenLines = collect(data_get($reviewData, 'allergens', []))->implode(PHP_EOL);
    $regionLines = collect(data_get($reviewData, 'region_availability', []))->implode(PHP_EOL);
    $barcodeLines = collect(data_get($reviewData, 'barcodes', []))->map(fn ($item) => is_array($item) ? ($item['barcode'] ?? '') : $item)->filter()->implode(PHP_EOL);
    $imageUrlLines = collect(data_get($reviewData, 'images', []))->map(fn ($item) => is_array($item) ? ($item['url'] ?? '') : $item)->filter()->implode(PHP_EOL);
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Contribution Review</h2>
                <p class="text-sm text-gray-500">{{ ucfirst(str_replace('_', ' ', $contribution->change_type)) }} • {{ $contribution->barcode }}</p>
            </div>
            <a href="{{ route('admin.contributions.index') }}" class="px-4 py-2 rounded-lg border border-gray-300 text-sm text-gray-700">Back</a>
        </div>
    </x-slot>

    <div class="max-w-7xl mx-auto space-y-6">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 bg-white rounded-xl shadow-sm p-6 space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <p class="text-xs uppercase text-gray-400">Contributor</p>
                        <p class="font-medium">{{ $contribution->user?->name ?: 'Unknown' }}</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase text-gray-400">Status</p>
                        <p class="font-medium">{{ ucfirst($contribution->status) }}</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase text-gray-400">Submitted</p>
                        <p class="font-medium">{{ $contribution->created_at?->diffForHumans() }}</p>
                    </div>
                </div>

                <div>
                    <p class="text-xs uppercase text-gray-400 mb-2">Reason</p>
                    <p class="text-sm text-gray-700">{{ $contribution->reason }}</p>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div>
                        <p class="text-xs uppercase text-gray-400 mb-2">Current Product Snapshot</p>
                        <pre class="bg-gray-50 rounded-lg p-4 text-xs overflow-x-auto">{{ json_encode($contribution->old_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                    </div>
                    <div>
                        <p class="text-xs uppercase text-gray-400 mb-2">Submitted Change</p>
                        <pre class="bg-gray-50 rounded-lg p-4 text-xs overflow-x-auto">{{ json_encode($contribution->new_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm p-6 space-y-4">
                <h3 class="text-lg font-semibold text-gray-900">Moderation Actions</h3>

                <form method="POST" action="{{ route('admin.contributions.approve', $contribution) }}">
                    @csrf
                    <textarea name="notes" rows="3" placeholder="Approval notes" class="w-full rounded-lg border-gray-300"></textarea>
                    <button class="w-full mt-3 px-4 py-2 rounded-lg bg-[#1FA774] text-white font-medium">Approve</button>
                </form>

                <form method="POST" action="{{ route('admin.contributions.reject', $contribution) }}">
                    @csrf
                    <textarea name="reason" rows="3" placeholder="Reject reason" class="w-full rounded-lg border-gray-300" required></textarea>
                    <button class="w-full mt-3 px-4 py-2 rounded-lg bg-red-600 text-white font-medium">Reject</button>
                </form>

                <form method="POST" action="{{ route('admin.contributions.flag', $contribution) }}">
                    @csrf
                    <textarea name="reason" rows="3" placeholder="Flag reason" class="w-full rounded-lg border-gray-300" required></textarea>
                    <button class="w-full mt-3 px-4 py-2 rounded-lg bg-yellow-500 text-white font-medium">Flag</button>
                </form>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Edit Before Approval</h3>
            <form method="POST" action="{{ route('admin.contributions.update', $contribution) }}" enctype="multipart/form-data" class="space-y-4">
                @csrf
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <input type="text" name="barcode" value="{{ old('barcode', data_get($reviewData, 'barcode', $contribution->barcode)) }}" placeholder="Barcode" class="rounded-lg border-gray-300">
                    <input type="text" name="name" value="{{ old('name', data_get($reviewData, 'name', $contribution->product_name)) }}" placeholder="Product name" class="rounded-lg border-gray-300">
                    <input type="text" name="brand" value="{{ old('brand', data_get($reviewData, 'brand')) }}" placeholder="Brand" class="rounded-lg border-gray-300">
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <input type="text" name="product_family" value="{{ old('product_family', data_get($reviewData, 'product_family')) }}" placeholder="Family" class="rounded-lg border-gray-300">
                    <input type="number" name="category_id" value="{{ old('category_id', data_get($reviewData, 'category_id')) }}" placeholder="Category ID" class="rounded-lg border-gray-300">
                    <input type="text" name="category_name" value="{{ old('category_name', data_get($reviewData, 'category_name')) }}" placeholder="Category name" class="rounded-lg border-gray-300">
                </div>

                <input type="url" name="image_url" value="{{ old('image_url', data_get($reviewData, 'image_url')) }}" placeholder="Primary image URL" class="w-full rounded-lg border-gray-300">
                <textarea name="ingredients_text" rows="3" placeholder="Ingredients text" class="w-full rounded-lg border-gray-300">{{ old('ingredients_text', data_get($reviewData, 'ingredients_text')) }}</textarea>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                    <textarea name="ingredients" rows="5" placeholder="Ingredients, one per line" class="rounded-lg border-gray-300">{{ old('ingredients', $ingredientLines) }}</textarea>
                    <textarea name="nutrition" rows="5" placeholder="calories: 120" class="rounded-lg border-gray-300">{{ old('nutrition', $nutritionLines) }}</textarea>
                    <textarea name="additives" rows="4" placeholder="Additives" class="rounded-lg border-gray-300">{{ old('additives', $additiveLines) }}</textarea>
                    <textarea name="allergens" rows="4" placeholder="Allergens" class="rounded-lg border-gray-300">{{ old('allergens', $allergenLines) }}</textarea>
                    <textarea name="region_availability" rows="4" placeholder="Region availability" class="rounded-lg border-gray-300">{{ old('region_availability', $regionLines) }}</textarea>
                    <textarea name="barcodes" rows="4" placeholder="Barcodes" class="rounded-lg border-gray-300">{{ old('barcodes', $barcodeLines) }}</textarea>
                </div>

                <textarea name="image_urls" rows="3" placeholder="Image URLs" class="w-full rounded-lg border-gray-300">{{ old('image_urls', $imageUrlLines) }}</textarea>
                <input type="file" name="image_files[]" multiple class="w-full rounded-lg border-gray-300">
                <textarea name="review_notes" rows="3" placeholder="Internal moderation notes" class="w-full rounded-lg border-gray-300">{{ old('review_notes', $contribution->review_notes) }}</textarea>

                <div class="flex justify-end">
                    <button class="px-4 py-2 rounded-lg bg-gray-900 text-white font-medium">Save Moderation Draft</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
