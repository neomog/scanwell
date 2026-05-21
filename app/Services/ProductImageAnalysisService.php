<?php

namespace App\Services;

use App\Exceptions\ImageScanIdentificationException;
use App\Models\Product;
use App\Models\Scan;
use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProductImageAnalysisService
{
    public function __construct(
        protected ProductAnalysisService $productAnalysisService,
        protected GoogleCloudVisionService $googleCloudVisionService
    ) {
    }

    public function analyze(UploadedFile $image, array $input, ?string $userId = null, ?Scan $scan = null): array
    {
        $storedImage = $this->storeImage($image);
        $ocrOutput = $this->googleCloudVisionService->analyzeProductImage(
            (string) file_get_contents($image->getRealPath()),
            $image->getMimeType() ?: 'image/jpeg'
        );
        $signals = $this->extractSignals($input, $ocrOutput ?? []);
        $this->storeDebugSignalsOnScan($scan, $storedImage, $ocrOutput, $signals);

        if ($signals['barcode'] !== null) {
            try {
                $product = $this->productAnalysisService->analyzeByBarcode($signals['barcode'], $userId, $scan);

                return [
                    'product' => $product,
                    'barcode' => $signals['barcode'],
                    'matched_by' => $signals['barcode_source'] === 'client_hint'
                        ? 'image_barcode_hint'
                        : 'image_barcode',
                    'confidence' => $signals['confidence'] ?? 0.0,
                    'analysis_source' => $signals['analysis_source'],
                    'stored_image' => $storedImage,
                    'signals' => $signals,
                    'ocr' => $ocrOutput,
                ];
            } catch (Exception $exception) {
                if ((int) $exception->getCode() !== 404) {
                    throw $exception;
                }

                Log::info('Image scan barcode match missed product catalog', [
                    'scan_id' => $scan?->id,
                    'barcode' => $signals['barcode'],
                    'barcode_source' => $signals['barcode_source'],
                    'analysis_source' => $signals['analysis_source'],
                ]);

                throw new ImageScanIdentificationException(
                    'barcode_not_found',
                    'We found a barcode in the image, but this product is not in our catalog yet.',
                    404,
                    $this->buildMatchContext($signals)
                );
            }
        }

        if ($this->hasNoUsableSignals($signals)) {
            throw new ImageScanIdentificationException(
                'ocr_unreadable',
                'We could not read enough product information from the image. Please retake the photo with the label or barcode clearly visible.',
                422,
                $this->buildMatchContext($signals)
            );
        }

        $candidate = $this->matchLocalProductByNormalizedIdentity($signals)
            ?? $this->matchLocalProductFromAliases($signals);

        if ($candidate !== null) {
            $product = $this->productAnalysisService->analyzeByBarcode($candidate['product']->barcode, $userId, $scan);

            return [
                'product' => $product,
                'barcode' => $candidate['product']->barcode,
                'matched_by' => $candidate['matched_by'],
                'confidence' => $candidate['confidence'],
                'analysis_source' => $signals['analysis_source'],
                'stored_image' => $storedImage,
                'signals' => $signals,
                'ocr' => $ocrOutput,
            ];
        }

        Log::info('Image scan could not identify product', [
            'scan_id' => $scan?->id,
            'analysis_source' => $signals['analysis_source'],
            'barcode' => $signals['barcode'],
            'barcode_source' => $signals['barcode_source'],
            'product_name' => $signals['product_name'],
            'brand' => $signals['brand'],
            'ocr_provider' => $signals['ocr_provider'],
        ]);

        throw new ImageScanIdentificationException(
            'ocr_text_no_catalog_match',
            $this->unmatchedCatalogTextMessage($signals),
            404,
            $this->buildMatchContext($signals)
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

    protected function extractSignals(array $input, array $ocrOutput = []): array
    {
        $extractedText = $this->nullableString($input['extracted_text'] ?? null)
            ?? $this->nullableString($ocrOutput['extracted_text'] ?? null);
        $productName = $this->nullableString($input['product_name'] ?? null)
            ?? $this->nullableString($ocrOutput['product_name'] ?? null);
        $brand = $this->nullableString($input['brand'] ?? null)
            ?? $this->nullableString($ocrOutput['brand'] ?? null);

        $resolvedBarcode = $this->resolveBarcode(
            $this->nullableString($input['barcode_hint'] ?? null),
            $this->nullableString($ocrOutput['barcode_hint'] ?? null),
            $extractedText
        );

        return [
            'barcode' => $resolvedBarcode['barcode'],
            'barcode_source' => $resolvedBarcode['source'],
            'product_name' => $productName,
            'normalized_product_name' => $this->normalizeText($productName),
            'brand' => $brand,
            'normalized_brand' => $this->normalizeText($brand),
            'extracted_text' => $extractedText,
            'normalized_extracted_text' => $this->normalizeText($extractedText),
            'confidence' => isset($ocrOutput['confidence']) && is_numeric($ocrOutput['confidence'])
                ? round(max(0, min(1, (float) $ocrOutput['confidence'])), 4)
                : null,
            'analysis_source' => isset($ocrOutput['provider']) ? 'google_cloud_vision' : 'client_hints',
            'ocr_provider' => $this->nullableString($ocrOutput['provider'] ?? null),
            'ocr_mode' => $this->nullableString($ocrOutput['mode'] ?? null),
            'ocr_output' => $ocrOutput,
        ];
    }

    protected function resolveBarcode(?string $clientBarcodeHint, ?string $ocrBarcodeHint, ?string $extractedText): array
    {
        if ($clientBarcodeHint !== null) {
            return [
                'barcode' => $clientBarcodeHint,
                'source' => 'client_hint',
            ];
        }

        if ($ocrBarcodeHint !== null) {
            return [
                'barcode' => $ocrBarcodeHint,
                'source' => 'ocr_barcode_hint',
            ];
        }

        if ($extractedText === null) {
            return [
                'barcode' => null,
                'source' => null,
            ];
        }

        preg_match_all('/(?<!\d)(\d{8,13})(?!\d)/', $extractedText, $matches);

        foreach ($matches[1] ?? [] as $candidate) {
            $candidate = trim((string) $candidate);

            if ($candidate !== '') {
                return [
                    'barcode' => $candidate,
                    'source' => 'ocr_text',
                ];
            }
        }

        return [
            'barcode' => null,
            'source' => null,
        ];
    }

    protected function matchLocalProductByNormalizedIdentity(array $signals): ?array
    {
        $productName = $signals['normalized_product_name'] ?? '';
        $brand = $signals['normalized_brand'] ?? '';

        if ($productName === '' || $brand === '') {
            return null;
        }

        $product = Product::query()
            ->whereRaw('LOWER(name) = ?', [$productName])
            ->whereRaw('LOWER(COALESCE(brand, \'\')) = ?', [$brand])
            ->first();

        if ($product === null) {
            return null;
        }

        return [
            'product' => $product,
            'matched_by' => 'image_brand_product_match',
            'confidence' => 0.95,
        ];
    }

    protected function matchLocalProductFromAliases(array $signals): ?array
    {
        $tokens = $this->searchTokens($signals);

        if ($tokens->isEmpty()) {
            return null;
        }

        $products = Product::query()
            ->with('barcodes')
            ->where(function ($builder) use ($tokens) {
                foreach ($tokens as $token) {
                    $builder->orWhere('name', 'like', '%' . $token . '%')
                        ->orWhere('brand', 'like', '%' . $token . '%')
                        ->orWhere('raw_data', 'like', '%' . $token . '%')
                        ->orWhere('manual_overrides', 'like', '%' . $token . '%')
                        ->orWhereHas('barcodes', fn ($barcodeQuery) => $barcodeQuery->where('barcode', 'like', '%' . $token . '%'));
                }
            })
            ->limit(25)
            ->get();

        if ($products->isEmpty()) {
            return null;
        }

        $ranked = $products->map(function (Product $product) use ($signals) {
            $score = $this->aliasMatchScore($product, $signals);

            return [
                'product' => $product,
                'matched_by' => 'image_alias_text_match',
                'confidence' => round(min(0.9, $score), 4),
            ];
        })->sortByDesc('confidence')->values();

        $best = $ranked->first();
        $second = $ranked->get(1);

        if ($best === null || $best['confidence'] < 0.55) {
            return null;
        }

        if ($second !== null && ($best['confidence'] - $second['confidence']) < 0.1) {
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

    protected function normalizeText(?string $value): string
    {
        if ($value === null) {
            return '';
        }

        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value) ?? '';

        return trim($value);
    }

    protected function searchTokens(array $signals): Collection
    {
        return collect([
            $signals['product_name'] ?? null,
            $signals['brand'] ?? null,
            $signals['extracted_text'] ?? null,
        ])->filter()
            ->flatMap(function (string $value) {
                preg_match_all('/[a-z0-9]{3,}/i', strtolower($value), $matches);

                return $matches[0] ?? [];
            })
            ->reject(fn ($token) => in_array($token, ['the', 'and', 'with', 'for', 'from', 'ingredients', 'nutrition', 'facts'], true))
            ->unique()
            ->take(10)
            ->values();
    }

    protected function aliasMatchScore(Product $product, array $signals): float
    {
        $normalizedProductName = $signals['normalized_product_name'] ?? '';
        $normalizedBrand = $signals['normalized_brand'] ?? '';
        $normalizedText = $signals['normalized_extracted_text'] ?? '';
        $productName = $this->normalizeText($product->name);
        $productBrand = $this->normalizeText($product->brand);
        $score = 0.0;

        if ($normalizedProductName !== '' && $productName !== '') {
            if ($productName === $normalizedProductName) {
                $score += 0.45;
            } elseif (str_contains($productName, $normalizedProductName) || str_contains($normalizedProductName, $productName)) {
                $score += 0.25;
            }
        }

        if ($normalizedBrand !== '' && $productBrand !== '') {
            if ($productBrand === $normalizedBrand) {
                $score += 0.2;
            } elseif (str_contains($productBrand, $normalizedBrand) || str_contains($normalizedBrand, $productBrand)) {
                $score += 0.1;
            }
        }

        foreach ($this->catalogAliases($product) as $alias) {
            $normalizedAlias = $this->normalizeText($alias);

            if ($normalizedAlias === '') {
                continue;
            }

            if ($normalizedText !== '' && str_contains($normalizedText, $normalizedAlias)) {
                $score += str_word_count($normalizedAlias) >= 2 ? 0.18 : 0.08;
            }

            if ($normalizedProductName !== '' && ($normalizedAlias === $normalizedProductName || str_contains($normalizedAlias, $normalizedProductName))) {
                $score += 0.12;
            }
        }

        return min(1.0, $score);
    }

    protected function catalogAliases(Product $product): array
    {
        $rawAliases = collect([
            $product->name,
            $product->brand,
            data_get($product->manual_overrides, 'name'),
            data_get($product->manual_overrides, 'brand'),
            data_get($product->raw_data, 'product_name'),
            data_get($product->raw_data, 'generic_name'),
            data_get($product->raw_data, 'abbreviated_product_name'),
            data_get($product->raw_data, 'brands'),
            data_get($product->raw_data, 'brand'),
            data_get($product->raw_data, 'brand_owner'),
            data_get($product->raw_data, 'product_title'),
        ]);

        $listAliases = collect([
            data_get($product->raw_data, 'aliases', []),
            data_get($product->raw_data, 'alternate_names', []),
            data_get($product->manual_overrides, 'aliases', []),
        ])->flatten(1);

        return $rawAliases
            ->merge($listAliases)
            ->merge($product->barcodes->pluck('barcode'))
            ->filter(fn ($value) => is_string($value) && trim($value) !== '')
            ->map(fn ($value) => trim($value))
            ->unique()
            ->values()
            ->all();
    }

    protected function storeDebugSignalsOnScan(?Scan $scan, array $storedImage, ?array $ocrOutput, array $signals): void
    {
        if ($scan === null) {
            return;
        }

        $scan->update([
            'scan_metadata' => array_merge($scan->scan_metadata ?? [], [
                'image_scan' => [
                    'stored_image' => $storedImage,
                    'ocr' => $ocrOutput,
                    'signals' => $signals,
                ],
            ]),
        ]);
    }

    protected function hasNoUsableSignals(array $signals): bool
    {
        return ($signals['barcode'] ?? null) === null
            && ($signals['product_name'] ?? null) === null
            && ($signals['brand'] ?? null) === null
            && ($signals['extracted_text'] ?? null) === null;
    }

    protected function buildMatchContext(array $signals): array
    {
        return [
            'barcode' => $signals['barcode'] ?? null,
            'barcode_source' => $signals['barcode_source'] ?? null,
            'product_name' => $signals['product_name'] ?? null,
            'brand' => $signals['brand'] ?? null,
            'extracted_text' => $signals['extracted_text'] ?? null,
            'ocr_provider' => $signals['ocr_provider'] ?? null,
            'ocr_mode' => $signals['ocr_mode'] ?? null,
            'analysis_source' => $signals['analysis_source'] ?? null,
        ];
    }

    protected function unmatchedCatalogTextMessage(array $signals): string
    {
        $extractedText = $this->nullableString($signals['extracted_text'] ?? null);

        if ($extractedText === null) {
            return 'We extracted product label text from the image, but it does not match any product in our catalog.';
        }

        return sprintf(
            'We extracted product label text from the image, but it does not match any product in our catalog. Extracted text: "%s".',
            $this->limitText($extractedText, 160)
        );
    }

    protected function limitText(string $value, int $maxLength): string
    {
        $value = preg_replace('/\s+/', ' ', trim($value)) ?? '';

        if (strlen($value) <= $maxLength) {
            return $value;
        }

        return rtrim(substr($value, 0, $maxLength - 3)) . '...';
    }
}
