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

        return view('admin.contributions.show', [
            'contribution' => $contribution,
            'currentData' => $this->productPayloadService->presentContributionPayload($contribution->old_data),
            'submittedData' => $this->productPayloadService->presentContributionPayload($contribution->new_data),
            'reviewData' => $this->productPayloadService->presentContributionPayload(
                $contribution->moderated_data ?? $contribution->new_data
            ),
        ]);
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
            $this->buildInput(
                $request,
                $validated,
                $contribution->barcode,
                $contribution->moderated_data ?? $contribution->new_data ?? []
            )
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
            $this->buildInput(
                $request,
                $validated,
                $contribution->barcode,
                $contribution->moderated_data ?? $contribution->new_data ?? []
            ),
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

    protected function buildInput(Request $request, array $validated, string $defaultBarcode, array $existingPayload = []): array
    {
        $input = $validated;
        $currentImages = data_get(
            $this->productPayloadService->presentContributionPayload($existingPayload),
            'images',
            []
        );

        if (array_key_exists('ingredients', $validated)) {
            $input['ingredients'] = $this->splitLines($validated['ingredients'], true);
        }

        if (array_key_exists('additives', $validated)) {
            $input['additives'] = $this->splitLines($validated['additives']);
        }

        if (array_key_exists('allergens', $validated)) {
            $input['allergens'] = $this->splitLines($validated['allergens']);
        }

        if (array_key_exists('region_availability', $validated)) {
            $input['region_availability'] = $this->splitLines($validated['region_availability']);
        }

        if (array_key_exists('barcodes', $validated)) {
            $input['barcodes'] = $this->splitLines($validated['barcodes'], true, 'barcode');
        }

        if (array_key_exists('nutrition', $validated)) {
            $input['nutrition'] = $this->parseNutrition($validated['nutrition']);
        }

        if (array_key_exists('barcode', $validated)) {
            $input['barcode'] = $validated['barcode'] ?? $defaultBarcode;
        }

        if (array_key_exists('image_urls', $validated) || $request->hasFile('image_files')) {
            $input['images'] = $this->buildImagesFromInput(
                $this->splitLines($validated['image_urls'] ?? null),
                $request,
                $currentImages
            );

            if (!array_key_exists('image_url', $validated)) {
                $input['image_url'] = $input['images'][0]['url'] ?? null;
            }
        }

        return $this->productPayloadService->fromInput($input);
    }

    protected function buildImagesFromInput(array $imageUrls, Request $request, array $existingImages = []): array
    {
        $existingImagesByUrl = collect($existingImages)
            ->filter(fn ($image) => is_array($image) && !empty($image['url'] ?? null))
            ->keyBy(fn ($image) => $image['url']);

        $images = collect($imageUrls)
            ->map(function (string $url, int $index) use ($existingImagesByUrl) {
                $existingImage = $existingImagesByUrl->get($url);

                if (is_array($existingImage)) {
                    $existingImage['is_primary'] = $index === 0;
                    $existingImage['sort_order'] = $index;

                    return $existingImage;
                }

                return [
                    'url' => $url,
                    'disk' => null,
                    'path' => null,
                    'source' => 'manual',
                    'is_primary' => $index === 0,
                    'sort_order' => $index,
                ];
            })
            ->values();

        if ($request->hasFile('image_files')) {
            foreach ($request->file('image_files') as $file) {
                $path = $file->store('product-contributions', 'public');
                $images->push([
                    'disk' => 'public',
                    'path' => $path,
                    'url' => Storage::disk('public')->url($path),
                    'source' => 'moderation_upload',
                    'is_primary' => false,
                    'sort_order' => $images->count(),
                ]);
            }
        }

        return $images
            ->values()
            ->map(function (array $image, int $index) {
                $image['is_primary'] = $index === 0;
                $image['sort_order'] = $index;

                return $image;
            })
            ->all();
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
