<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProductContribution;
use App\Services\ProductContributionService;
use App\Services\ProductPayloadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ContributionModerationController extends Controller
{
    public function __construct(
        protected ProductContributionService $productContributionService,
        protected ProductPayloadService $productPayloadService
    ) {
    }

    public function index(Request $request)
    {
        $query = ProductContribution::with(['user', 'product', 'reviewer'])->latest();

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        if ($changeType = $request->get('change_type')) {
            $query->where('change_type', $changeType);
        }

        if ($search = trim((string) $request->get('search'))) {
            $query->where(function ($builder) use ($search) {
                $builder->where('product_name', 'like', "%{$search}%")
                    ->orWhere('barcode', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($userQuery) => $userQuery->where('name', 'like', "%{$search}%"));
            });
        }

        $contributions = $query->paginate(15)->withQueryString();

        return view('admin.contributions.index', [
            'contributions' => $contributions,
            'pendingCount' => ProductContribution::where('status', 'pending')->count(),
        ]);
    }

    public function show(ProductContribution $contribution)
    {
        $contribution->load(['user', 'product.images', 'product.barcodes', 'product.ingredients', 'reviewer', 'auditLogs.actor']);

        return view('admin.contributions.show', compact('contribution'));
    }

    public function update(Request $request, ProductContribution $contribution)
    {
        $validated = $request->validate([
            'review_notes' => 'nullable|string|max:1000',
            'barcode' => 'nullable|string|max:50',
            'product_name' => 'nullable|string|max:255',
            'name' => 'nullable|string|max:255',
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

        $this->productContributionService->updateForModeration(
            $contribution,
            $request->user(),
            $this->buildInput($request, $validated, $contribution->barcode)
        );

        return redirect()
            ->route('admin.contributions.show', $contribution)
            ->with('success', 'Contribution updated for moderation.');
    }

    public function approve(Request $request, ProductContribution $contribution)
    {
        $validated = $request->validate([
            'notes' => 'nullable|string|max:1000',
            'barcode' => 'nullable|string|max:50',
            'product_name' => 'nullable|string|max:255',
            'name' => 'nullable|string|max:255',
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

        $this->productContributionService->approve(
            $contribution,
            $request->user(),
            $this->buildInput($request, $validated, $contribution->barcode),
            $validated['notes'] ?? null
        );

        return redirect()
            ->route('admin.contributions.show', $contribution)
            ->with('success', 'Contribution approved successfully.');
    }

    public function reject(Request $request, ProductContribution $contribution)
    {
        $validated = $request->validate([
            'reason' => 'required|string|min:10|max:1000',
        ]);

        $this->productContributionService->reject($contribution, $request->user(), $validated['reason']);

        return redirect()
            ->route('admin.contributions.show', $contribution)
            ->with('success', 'Contribution rejected.');
    }

    public function flag(Request $request, ProductContribution $contribution)
    {
        $validated = $request->validate([
            'reason' => 'required|string|min:10|max:1000',
        ]);

        $this->productContributionService->flag($contribution, $request->user(), $validated['reason']);

        return redirect()
            ->route('admin.contributions.show', $contribution)
            ->with('success', 'Contribution flagged.');
    }

    protected function buildInput(Request $request, array $validated, string $defaultBarcode): array
    {
        $input = $validated;
        $input['ingredients'] = $this->splitLines($validated['ingredients'] ?? null, true);
        $input['additives'] = $this->splitLines($validated['additives'] ?? null);
        $input['allergens'] = $this->splitLines($validated['allergens'] ?? null);
        $input['region_availability'] = $this->splitLines($validated['region_availability'] ?? null);
        $input['barcodes'] = $this->splitLines($validated['barcodes'] ?? null, true, 'barcode');
        $input['image_urls'] = $this->splitLines($validated['image_urls'] ?? null);
        $input['nutrition'] = $this->parseNutrition($validated['nutrition'] ?? null);
        $input['barcode'] = $validated['barcode'] ?? $defaultBarcode;

        if ($request->hasFile('image_files')) {
            $uploadedImages = [];

            foreach ($request->file('image_files') as $index => $file) {
                $path = $file->store('product-contributions', 'public');
                $uploadedImages[] = [
                    'disk' => 'public',
                    'path' => $path,
                    'source' => 'moderation_upload',
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
