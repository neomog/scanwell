<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\ProductPayloadService;
use App\Services\ProductWorkflowService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProductManagementController extends Controller
{
    public function __construct(
        protected ProductWorkflowService $productWorkflowService,
        protected ProductPayloadService $productPayloadService
    ) {
    }

    public function index(Request $request)
    {
        $query = Product::with(['foodScore', 'cosmeticScore', 'images', 'barcodes'])
            ->orderByDesc('created_at');

        if ($search = trim((string) $request->get('search'))) {
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('brand', 'like', "%{$search}%")
                    ->orWhere('barcode', 'like', "%{$search}%")
                    ->orWhereHas('barcodes', fn ($barcodeQuery) => $barcodeQuery->where('barcode', 'like', "%{$search}%"));
            });
        }

        if ($family = $request->get('product_family')) {
            $query->where('product_family', $family);
        }

        $products = $query->paginate(15)->withQueryString();

        return view('admin.products.index', compact('products'));
    }

    public function create()
    {
        return view('admin.products.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'barcode' => 'required|string|max:50|unique:products,barcode',
            'name' => 'required|string|max:255',
            'brand' => 'nullable|string|max:255',
            'category_id' => 'nullable|integer',
            'category_name' => 'nullable|string|max:255',
            'product_family' => 'nullable|string|in:food,cosmetic,pet_food,household,general',
            'image_url' => 'nullable|url',
            'ingredients_text' => 'nullable|string',
            'ingredients' => 'nullable|string',
            'nutrition' => 'nullable|string',
            'additives' => 'nullable|string',
            'allergens' => 'nullable|string',
            'region_availability' => 'nullable|string',
            'barcodes' => 'nullable|string',
            'image_urls' => 'nullable|string',
            'image_files.*' => 'nullable|image|max:5120',
        ]);

        $payload = $this->buildPayloadFromRequest($request, $validated);
        $product = $this->productWorkflowService->createProduct($payload, [
            'actor' => $request->user(),
            'source' => 'admin_manual',
            'mark_approved' => true,
        ]);

        return redirect()
            ->route('admin.products.show', $product)
            ->with('success', 'Product created successfully.');
    }

    public function show(Product $product)
    {
        $product->load([
            'ingredients',
            'nutrition',
            'foodScore',
            'cosmeticScore',
            'images',
            'barcodes',
            'auditLogs.actor',
            'contributions.user',
        ]);

        return view('admin.products.show', compact('product'));
    }

    public function edit(Product $product)
    {
        $product->load(['ingredients', 'nutrition', 'images', 'barcodes']);

        return view('admin.products.edit', compact('product'));
    }

    public function update(Request $request, Product $product)
    {
        $validated = $request->validate([
            'barcode' => 'required|string|max:50|unique:products,barcode,' . $product->id,
            'name' => 'required|string|max:255',
            'brand' => 'nullable|string|max:255',
            'category_id' => 'nullable|integer',
            'category_name' => 'nullable|string|max:255',
            'product_family' => 'nullable|string|in:food,cosmetic,pet_food,household,general',
            'image_url' => 'nullable|url',
            'ingredients_text' => 'nullable|string',
            'ingredients' => 'nullable|string',
            'nutrition' => 'nullable|string',
            'additives' => 'nullable|string',
            'allergens' => 'nullable|string',
            'region_availability' => 'nullable|string',
            'barcodes' => 'nullable|string',
            'image_urls' => 'nullable|string',
            'image_files.*' => 'nullable|image|max:5120',
        ]);

        $payload = $this->buildPayloadFromRequest($request, $validated);
        $updatedProduct = $this->productWorkflowService->updateProduct($product, $payload, [
            'actor' => $request->user(),
            'track_manual_overrides' => true,
            'mark_approved' => true,
        ]);

        return redirect()
            ->route('admin.products.show', $updatedProduct)
            ->with('success', 'Product updated successfully.');
    }

    public function destroy(Request $request, Product $product)
    {
        $this->productWorkflowService->deleteProduct($product, $request->user());

        return redirect()
            ->route('admin.products.index')
            ->with('success', 'Product deleted successfully.');
    }

    protected function buildPayloadFromRequest(Request $request, array $validated): array
    {
        $input = $validated;
        $input['ingredients'] = $this->splitLines($validated['ingredients'] ?? null, true);
        $input['additives'] = $this->splitLines($validated['additives'] ?? null);
        $input['allergens'] = $this->splitLines($validated['allergens'] ?? null);
        $input['region_availability'] = $this->splitLines($validated['region_availability'] ?? null);
        $input['barcodes'] = $this->splitLines($validated['barcodes'] ?? null, true, 'barcode');
        $input['image_urls'] = $this->splitLines($validated['image_urls'] ?? null);
        $input['nutrition'] = $this->parseNutrition($validated['nutrition'] ?? null);

        if ($request->hasFile('image_files')) {
            $uploadedImages = [];

            foreach ($request->file('image_files') as $index => $file) {
                $path = $file->store('products', 'public');
                $uploadedImages[] = [
                    'disk' => 'public',
                    'path' => $path,
                    'source' => 'upload',
                    'is_primary' => $index === 0,
                    'sort_order' => $index,
                ];
            }

            $input['images'] = array_merge($input['images'] ?? [], $uploadedImages);

            if (!isset($input['image_url']) && count($uploadedImages) > 0) {
                $input['image_url'] = Storage::disk('public')->url($uploadedImages[0]['path']);
            }
        }

        return $this->productPayloadService->fromInput($input);
    }

    protected function splitLines(?string $value, bool $asArrayObjects = false, string $objectKey = 'name'): array
    {
        if (!$value) {
            return [];
        }

        $items = collect(preg_split('/\r\n|\r|\n|,/', $value))
            ->map(fn ($item) => trim((string) $item))
            ->filter()
            ->values();

        if (!$asArrayObjects) {
            return $items->all();
        }

        return $items->map(fn ($item) => [$objectKey => $item])->all();
    }

    protected function parseNutrition(?string $value): array
    {
        if (!$value) {
            return [];
        }

        $nutrition = [];

        foreach (preg_split('/\r\n|\r|\n/', $value) as $line) {
            [$key, $entry] = array_pad(explode(':', $line, 2), 2, null);

            if (!$key || $entry === null) {
                continue;
            }

            $nutrition[trim($key)] = is_numeric(trim($entry)) ? (float) trim($entry) : trim($entry);
        }

        return $nutrition;
    }
}
