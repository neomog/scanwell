@php
    $productData = isset($product) ? $product : null;
    $ingredientLines = old('ingredients', isset($product) ? $product->ingredients->pluck('name')->implode(PHP_EOL) : '');
    $nutritionLines = old('nutrition', isset($product) && $product->nutrition
        ? collect([
            'calories' => $product->nutrition->calories,
            'fat' => $product->nutrition->fat,
            'saturated_fat' => $product->nutrition->saturated_fat,
            'carbohydrates' => $product->nutrition->carbohydrates,
            'fiber' => $product->nutrition->fiber,
            'sugars' => $product->nutrition->sugars,
            'protein' => $product->nutrition->protein,
            'sodium' => $product->nutrition->sodium,
            'serving_size' => $product->nutrition->serving_size,
        ])->filter(fn ($value) => $value !== null && $value !== '')->map(fn ($value, $key) => $key . ': ' . $value)->implode(PHP_EOL)
        : '');
    $additiveLines = old('additives', isset($product) ? collect($product->additives ?? [])->implode(PHP_EOL) : '');
    $allergenLines = old('allergens', isset($product) ? collect($product->allergens ?? [])->implode(PHP_EOL) : '');
    $regionLines = old('region_availability', isset($product) ? collect($product->region_availability ?? [])->implode(PHP_EOL) : '');
    $barcodeLines = old('barcodes', isset($product) ? $product->barcodes->pluck('barcode')->implode(PHP_EOL) : '');
    $imageUrlLines = old('image_urls', isset($product) ? $product->images->pluck('resolved_url')->filter()->implode(PHP_EOL) : '');
@endphp

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <div class="bg-white rounded-xl shadow-sm p-6 space-y-4">
        <h3 class="text-lg font-semibold text-gray-900">Core Details</h3>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Barcode</label>
            <input type="text" name="barcode" value="{{ old('barcode', $productData?->barcode) }}" class="w-full rounded-lg border-gray-300">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
            <input type="text" name="name" value="{{ old('name', $productData?->name) }}" class="w-full rounded-lg border-gray-300">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Brand</label>
            <input type="text" name="brand" value="{{ old('brand', $productData?->brand) }}" class="w-full rounded-lg border-gray-300">
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Family</label>
                <select name="product_family" class="w-full rounded-lg border-gray-300">
                    @foreach(['food' => 'Food', 'cosmetic' => 'Cosmetic', 'pet_food' => 'Pet Food', 'household' => 'Household', 'general' => 'General'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('product_family', $productData?->resolved_product_family) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Category ID</label>
                <input type="number" name="category_id" value="{{ old('category_id', $productData?->category_id) }}" class="w-full rounded-lg border-gray-300">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Category Name</label>
                <input type="text" name="category_name" value="{{ old('category_name', $productData?->category_name) }}" class="w-full rounded-lg border-gray-300">
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Primary Image URL</label>
            <input type="url" name="image_url" value="{{ old('image_url', $productData?->primary_image_url) }}" class="w-full rounded-lg border-gray-300">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Ingredients Text</label>
            <textarea name="ingredients_text" rows="4" class="w-full rounded-lg border-gray-300">{{ old('ingredients_text', $productData?->ingredients_text) }}</textarea>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm p-6 space-y-4">
        <h3 class="text-lg font-semibold text-gray-900">Structured Data</h3>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Ingredients</label>
            <textarea name="ingredients" rows="6" class="w-full rounded-lg border-gray-300" placeholder="One ingredient per line">{{ $ingredientLines }}</textarea>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Nutrition</label>
            <textarea name="nutrition" rows="6" class="w-full rounded-lg border-gray-300" placeholder="calories: 120">{{ $nutritionLines }}</textarea>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Additives</label>
                <textarea name="additives" rows="4" class="w-full rounded-lg border-gray-300">{{ $additiveLines }}</textarea>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Allergens</label>
                <textarea name="allergens" rows="4" class="w-full rounded-lg border-gray-300">{{ $allergenLines }}</textarea>
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Region Availability</label>
            <textarea name="region_availability" rows="3" class="w-full rounded-lg border-gray-300">{{ $regionLines }}</textarea>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Barcodes</label>
            <textarea name="barcodes" rows="3" class="w-full rounded-lg border-gray-300">{{ $barcodeLines }}</textarea>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Additional Image URLs</label>
            <textarea name="image_urls" rows="3" class="w-full rounded-lg border-gray-300">{{ $imageUrlLines }}</textarea>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Upload Images</label>
            <input type="file" name="image_files[]" multiple class="w-full rounded-lg border-gray-300">
        </div>
    </div>
</div>

<div class="flex items-center justify-end gap-3 mt-6">
    <a href="{{ route('admin.products.index') }}" class="px-4 py-2 rounded-lg border border-gray-300 text-gray-700">Cancel</a>
    <button type="submit" class="px-4 py-2 rounded-lg bg-[#1FA774] text-white font-medium">Save Product</button>
</div>
