<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Edit Product</h2>
            <form method="POST" action="{{ route('admin.products.destroy', $product) }}">
                @csrf
                @method('DELETE')
                <button class="px-4 py-2 rounded-lg bg-red-600 text-white text-sm">Delete Product</button>
            </form>
        </div>
    </x-slot>

    <div class="max-w-7xl mx-auto">
        <form method="POST" action="{{ route('admin.products.update', $product) }}" enctype="multipart/form-data">
            @csrf
            @include('admin.products._form', ['product' => $product])
        </form>
    </div>
</x-app-layout>
