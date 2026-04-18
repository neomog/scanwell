<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Products</h2>
            <a href="{{ route('admin.products.create') }}" class="px-4 py-2 rounded-lg bg-[#1FA774] text-white text-sm font-medium">Add Product</a>
        </div>
    </x-slot>

    <div class="max-w-7xl mx-auto space-y-6">
        <form method="GET" class="bg-white rounded-xl shadow-sm p-4 grid grid-cols-1 md:grid-cols-3 gap-4">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search name, brand, barcode" class="rounded-lg border-gray-300">
            <select name="product_family" class="rounded-lg border-gray-300">
                <option value="">All families</option>
                @foreach(['food' => 'Food', 'cosmetic' => 'Cosmetic', 'pet_food' => 'Pet Food', 'household' => 'Household', 'general' => 'General'] as $value => $label)
                    <option value="{{ $value }}" @selected(request('product_family') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <button class="px-4 py-2 rounded-lg bg-gray-900 text-white text-sm">Filter</button>
        </form>

        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-500">
                <tr>
                    <th class="px-6 py-3 text-left">Product</th>
                    <th class="px-6 py-3 text-left">Barcode</th>
                    <th class="px-6 py-3 text-left">Family</th>
                    <th class="px-6 py-3 text-left">Score</th>
                    <th class="px-6 py-3 text-left">Source</th>
                    <th class="px-6 py-3 text-right">Actions</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                @forelse($products as $product)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4">
                            <div class="font-medium text-gray-900">{{ $product->name }}</div>
                            <div class="text-xs text-gray-500">{{ $product->brand ?: 'No brand' }}</div>
                        </td>
                        <td class="px-6 py-4 text-gray-700">{{ $product->barcode }}</td>
                        <td class="px-6 py-4 capitalize">{{ str_replace('_', ' ', $product->resolved_product_family) }}</td>
                        <td class="px-6 py-4">{{ $product->score ?? 'N/A' }}</td>
                        <td class="px-6 py-4">{{ $product->source }}</td>
                        <td class="px-6 py-4 text-right space-x-3">
                            <a href="{{ route('admin.products.show', $product) }}" class="text-[#1FA774] font-medium">View</a>
                            <a href="{{ route('admin.products.edit', $product) }}" class="text-blue-600 font-medium">Edit</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-6 py-10 text-center text-gray-500">No products found.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        {{ $products->links() }}
    </div>
</x-app-layout>
