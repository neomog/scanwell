<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $product->name }}</h2>
                <p class="text-sm text-gray-500">{{ $product->brand ?: 'No brand' }} • {{ $product->barcode }}</p>
            </div>
            <a href="{{ route('admin.products.edit', $product) }}" class="px-4 py-2 rounded-lg bg-[#1FA774] text-white text-sm font-medium">Edit Product</a>
        </div>
    </x-slot>

    <div class="max-w-7xl mx-auto space-y-6">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 bg-white rounded-xl shadow-sm p-6 space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <p class="text-xs uppercase text-gray-400">Family</p>
                        <p class="font-medium capitalize">{{ str_replace('_', ' ', $product->resolved_product_family) }}</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase text-gray-400">Category</p>
                        <p class="font-medium">{{ $product->category_name ?: $product->category_id ?: 'Unspecified' }}</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase text-gray-400">Source</p>
                        <p class="font-medium">{{ $product->source }}</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase text-gray-400">Score</p>
                        <p class="font-medium">{{ $product->score ?? 'N/A' }}</p>
                    </div>
                </div>

                <div>
                    <p class="text-xs uppercase text-gray-400 mb-2">Ingredients</p>
                    <div class="flex flex-wrap gap-2">
                        @forelse($product->ingredients as $ingredient)
                            <span class="px-3 py-1 rounded-full bg-gray-100 text-sm text-gray-700">{{ $ingredient->name }}</span>
                        @empty
                            <span class="text-sm text-gray-500">No ingredients listed.</span>
                        @endforelse
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <p class="text-xs uppercase text-gray-400 mb-2">Additives</p>
                        <div class="text-sm text-gray-700 whitespace-pre-line">{{ collect($product->additives ?? [])->implode(PHP_EOL) ?: 'None listed' }}</div>
                    </div>
                    <div>
                        <p class="text-xs uppercase text-gray-400 mb-2">Allergens</p>
                        <div class="text-sm text-gray-700 whitespace-pre-line">{{ collect($product->allergens ?? [])->implode(PHP_EOL) ?: 'None listed' }}</div>
                    </div>
                    <div>
                        <p class="text-xs uppercase text-gray-400 mb-2">Regions</p>
                        <div class="text-sm text-gray-700 whitespace-pre-line">{{ collect($product->region_availability ?? [])->implode(PHP_EOL) ?: 'None listed' }}</div>
                    </div>
                </div>

                <div>
                    <p class="text-xs uppercase text-gray-400 mb-2">Nutrition</p>
                    @if($product->nutrition)
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-sm">
                            @foreach(['calories', 'fat', 'saturated_fat', 'carbohydrates', 'fiber', 'sugars', 'protein', 'sodium'] as $field)
                                <div class="rounded-lg bg-gray-50 p-3">
                                    <div class="text-gray-400 uppercase text-xs">{{ str_replace('_', ' ', $field) }}</div>
                                    <div class="font-medium text-gray-800">{{ $product->nutrition->{$field} ?? 'N/A' }}</div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-sm text-gray-500">No nutrition data stored.</p>
                    @endif
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm p-6 space-y-4">
                <div>
                    <p class="text-xs uppercase text-gray-400 mb-2">Barcodes</p>
                    <div class="space-y-2">
                        @forelse($product->barcodes as $barcode)
                            <div class="flex items-center justify-between text-sm">
                                <span>{{ $barcode->barcode }}</span>
                                @if($barcode->is_primary)
                                    <span class="px-2 py-1 rounded-full bg-green-100 text-green-700 text-xs">Primary</span>
                                @endif
                            </div>
                        @empty
                            <p class="text-sm text-gray-500">No barcode aliases.</p>
                        @endforelse
                    </div>
                </div>

                <div>
                    <p class="text-xs uppercase text-gray-400 mb-2">Images</p>
                    <div class="grid grid-cols-2 gap-3">
                        @forelse($product->images as $image)
                            <img src="{{ $image->resolved_url }}" alt="{{ $product->name }}" class="w-full h-28 object-cover rounded-lg border border-gray-200">
                        @empty
                            <p class="text-sm text-gray-500 col-span-2">No images stored.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="bg-white rounded-xl shadow-sm p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Audit Log</h3>
                <div class="space-y-3">
                    @forelse($product->auditLogs->take(10) as $log)
                        <div class="border border-gray-100 rounded-lg p-3">
                            <div class="flex items-center justify-between">
                                <span class="font-medium text-gray-800">{{ str_replace('_', ' ', $log->action) }}</span>
                                <span class="text-xs text-gray-400">{{ $log->created_at?->diffForHumans() }}</span>
                            </div>
                            <p class="text-sm text-gray-500">{{ $log->description }}</p>
                            <p class="text-xs text-gray-400">By {{ $log->actor?->name ?: 'System' }}</p>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500">No audit records yet.</p>
                    @endforelse
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Contribution History</h3>
                <div class="space-y-3">
                    @forelse($product->contributions->take(10) as $contribution)
                        <a href="{{ route('admin.contributions.show', $contribution) }}" class="block border border-gray-100 rounded-lg p-3 hover:bg-gray-50">
                            <div class="flex items-center justify-between">
                                <span class="font-medium text-gray-800">{{ ucfirst($contribution->change_type) }}</span>
                                <span class="text-xs text-gray-400">{{ ucfirst($contribution->status) }}</span>
                            </div>
                            <p class="text-sm text-gray-500">{{ $contribution->reason }}</p>
                            <p class="text-xs text-gray-400">By {{ $contribution->user?->name ?: 'Unknown' }}</p>
                        </a>
                    @empty
                        <p class="text-sm text-gray-500">No contributions linked to this product.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
