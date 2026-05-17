<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Scan;
use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ProductImageAnalysisService
{
    public function __construct(
        protected ProductAnalysisService $productAnalysisService,
        protected OpenAiVisionService $openAiVisionService
    ) {
    }

    public function analyze(UploadedFile $image, array $input, ?string $userId = null, ?Scan $scan = null): array
    {
        $storedImage = $this->storeImage($image);
        $visionSignals = $this->openAiVisionService->analyzeProductImage(
            (string) file_get_contents($image->getRealPath()),
            $image->getMimeType() ?: 'image/jpeg'
        );
        $signals = $this->extractSignals($input, $visionSignals ?? []);

        if ($signals['barcode'] !== null) {
            try {
                $product = $this->productAnalysisService->analyzeByBarcode($signals['barcode'], $userId, $scan);

                return [
                    'product' => $product,
                    'barcode' => $signals['barcode'],
                    'matched_by' => $visionSignals !== null && ($visionSignals['barcode'] ?? null) === $signals['barcode']
                        ? 'image_barcode'
                        : 'image_barcode_hint',
                    'confidence' => $signals['confidence'] ?? 95,
                    'analysis_source' => $visionSignals !== null ? 'openai_vision' : 'barcode_hint',
                    'stored_image' => $storedImage,
                    'signals' => $signals,
                ];
            } catch (Exception $exception) {
                if ((int) $exception->getCode() !== 404) {
                    throw $exception;
                }
            }
        }

        $candidate = $this->matchLocalProductFromSignals($signals);

        if ($candidate !== null) {
            $product = $this->productAnalysisService->analyzeByBarcode($candidate['product']->barcode, $userId, $scan);

            return [
                'product' => $product,
                'barcode' => $candidate['product']->barcode,
                'matched_by' => 'image_text_match',
                'confidence' => $candidate['confidence'],
                'analysis_source' => $visionSignals !== null ? 'openai_vision_local_catalog_match' : 'local_catalog_text_match',
                'stored_image' => $storedImage,
                'signals' => $signals,
            ];
        }

        throw new Exception(
            'We could not confidently identify this product from the uploaded image. Include a barcode hint or OCR text for now.',
            422
        );
    }

    protected function storeImage(UploadedFile $image): array
    {
        $path = $image->store('scan-inputs', 'public');

        return [
            'disk' => 'public',
            'path' => $path,
            'url' => Storage::disk('public')->url($path),
            'original_name' => $image->getClientOriginalName(),
            'mime_type' => $image->getMimeType(),
            'size' => $image->getSize(),
        ];
    }

    protected function extractSignals(array $input, array $visionSignals = []): array
    {
        $extractedText = $this->nullableString($input['extracted_text'] ?? null)
            ?? $this->nullableString($visionSignals['extracted_text'] ?? null);
        $productName = $this->nullableString($input['product_name'] ?? null)
            ?? $this->nullableString($visionSignals['product_name'] ?? null);
        $brand = $this->nullableString($input['brand'] ?? null)
            ?? $this->nullableString($visionSignals['brand'] ?? null);
        $barcode = $this->resolveBarcode(
            $this->nullableString($input['barcode_hint'] ?? null)
                ?? $this->nullableString($visionSignals['barcode'] ?? null),
            $extractedText
        );

        return [
            'barcode' => $barcode,
            'product_name' => $productName,
            'brand' => $brand,
            'extracted_text' => $extractedText,
            'category_hint' => $this->nullableString($visionSignals['category_hint'] ?? null),
            'confidence' => isset($visionSignals['confidence']) && is_numeric($visionSignals['confidence'])
                ? max(0, min(100, (int) $visionSignals['confidence']))
                : null,
            'front_label_visible' => (bool) ($visionSignals['front_label_visible'] ?? false),
            'barcode_visible' => (bool) ($visionSignals['barcode_visible'] ?? false),
            'nutrition_panel_visible' => (bool) ($visionSignals['nutrition_panel_visible'] ?? false),
            'ingredients_visible' => (bool) ($visionSignals['ingredients_visible'] ?? false),
        ];
    }

    protected function resolveBarcode(?string $barcodeHint, ?string $extractedText): ?string
    {
        if ($barcodeHint !== null) {
            return $barcodeHint;
        }

        if ($extractedText === null) {
            return null;
        }

        preg_match_all('/(?<!\d)(\d{8,13})(?!\d)/', $extractedText, $matches);

        foreach ($matches[1] ?? [] as $candidate) {
            $candidate = trim((string) $candidate);

            if ($candidate !== '') {
                return $candidate;
            }
        }

        return null;
    }

    protected function matchLocalProductFromSignals(array $signals): ?array
    {
        $productName = strtolower((string) ($signals['product_name'] ?? ''));
        $brand = strtolower((string) ($signals['brand'] ?? ''));
        $extractedText = strtolower((string) ($signals['extracted_text'] ?? ''));

        if ($productName === '' && $brand === '' && $extractedText === '') {
            return null;
        }

        $query = Product::query()
            ->where(function ($builder) use ($productName, $brand, $extractedText) {
                if ($productName !== '') {
                    $builder->orWhere('name', 'like', '%' . $productName . '%');
                }

                if ($brand !== '') {
                    $builder->orWhere('brand', 'like', '%' . $brand . '%');
                }

                if ($extractedText !== '') {
                    $builder->orWhere('name', 'like', '%' . $extractedText . '%');
                }
            })
            ->limit(10)
            ->get();

        if ($query->isEmpty()) {
            return null;
        }

        $ranked = $query->map(function (Product $product) use ($productName, $brand, $extractedText) {
            $score = 0;
            $name = strtolower((string) $product->name);
            $productBrand = strtolower((string) $product->brand);

            if ($productName !== '') {
                if ($name === $productName) {
                    $score += 70;
                } elseif (str_contains($name, $productName) || str_contains($productName, $name)) {
                    $score += 45;
                }
            }

            if ($brand !== '') {
                if ($productBrand === $brand) {
                    $score += 25;
                } elseif ($productBrand !== '' && (str_contains($productBrand, $brand) || str_contains($brand, $productBrand))) {
                    $score += 15;
                }
            }

            if ($extractedText !== '' && $name !== '' && str_contains($extractedText, $name)) {
                $score += 15;
            }

            return [
                'product' => $product,
                'confidence' => min(90, $score),
            ];
        })->sortByDesc('confidence')->values();

        $best = $ranked->first();
        $second = $ranked->get(1);

        if ($best === null || $best['confidence'] < 60) {
            return null;
        }

        if ($second !== null && ($best['confidence'] - $second['confidence']) < 10) {
            return null;
        }

        return $best;
    }

    protected function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
