<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\ScanRequest;
use App\Http\Resources\ProductResource;
use App\Http\Resources\ScanResource;
use App\Models\Product;
use App\Models\Scan;
use App\Models\UserPreference;
use App\Services\ProductAnalysisService;
use Error;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Exception;

class ScanController extends Controller
{
    protected ProductAnalysisService $analysisService;

    public function __construct(ProductAnalysisService $analysisService)
    {
        $this->analysisService = $analysisService;
    }

    /**
     * Scan a product by barcode
     */
    public function scan(ScanRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            $barcode = $validated['barcode'];

            // Create initial scan record
            $scan = Scan::create([
                'user_id' => Auth::id(),
                'barcode' => $barcode,
                'scan_timestamp' => now(),
                'device_type' => $request->userAgent(),
                'status' => 'pending',
                'scan_metadata' => [
                    'ip' => $request->ip(),
                    'source' => 'mobile_app',
                    'latitude' => $validated['latitude'] ?? null,
                    'longitude' => $validated['longitude'] ?? null,
                ],
            ]);

            // Analyze product
            $product = $this->analysisService->analyzeByBarcode($barcode, Auth::id());

            // Update scan with product
            $scan->markAsCompleted($product, [
                'matched_provider' => data_get($product->raw_data, '_scanwell.source'),
                'product_family' => $product->product_family,
                'confidence' => data_get($product->raw_data, '_scanwell.confidence'),
            ]);

            // Get personalized warnings
            $personalizedWarnings = [];
            if (Auth::check()) {
                $preferences = UserPreference::where('user_id', Auth::id())->first();
                if ($preferences) {
                    $personalizedWarnings = $preferences->getPersonalizedWarnings($product);
                }
            }

            // Find alternatives if product score is low
            $alternatives = null;
            $score = $product->foodScore->overall_score ?? $product->cosmeticScore->overall_score;

            if ($score !== null && $score < 50) {
                $alternatives = $this->findAlternatives($product);
            }

            return response()->json([
                'success' => true,
                'message' => 'Product scanned successfully',
                'data' => [
                    'scan' => new ScanResource($scan),
                    'product' => new ProductResource($product),
                    'personalized_warnings' => $personalizedWarnings,
                    'alternatives' => $alternatives ? ProductResource::collection($alternatives) : [],
                    'score_interpretation' => $this->interpretScore($score, $product),
                ],
            ]);

        } catch (Exception|Error $e) {
            // Update scan as failed if it was created
            if (isset($scan)) {
                $scan->markAsFailed($e->getMessage());
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to scan product',
                'error' => $e->getMessage(),
                'error2' => $e->getTraceAsString(),
                'error3' => $e->getLine(),
                'error4' => $e->getCode(),
            ], 500);
        }
    }

    /**
     * Delete a scan from history
     */
    public function destroy(string $id): JsonResponse
    {
        $scan = Scan::where('user_id', Auth::id())->findOrFail($id);
        $scan->delete();

        return response()->json([
            'success' => true,
            'message' => 'Scan removed from history',
        ]);
    }

    /**
     * Get scan history for authenticated user
     */
    public function history(): JsonResponse
    {
        $scans = Scan::with(['product', 'product.foodScore', 'product.cosmeticScore'])
            ->where('user_id', Auth::id())
            ->orderBy('scan_timestamp', 'desc')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => ScanResource::collection($scans),
            'meta' => [
                'total' => $scans->total(),
                'per_page' => $scans->perPage(),
                'current_page' => $scans->currentPage(),
                'last_page' => $scans->lastPage(),
            ],
        ]);
    }

    /**
     * Get single scan details
     */
    public function show(string $id): JsonResponse
    {
        $scan = Scan::with([
            'product',
            'product.ingredients',
            'product.nutrition',
            'product.foodScore',
            'product.cosmeticScore'
        ])->where('user_id', Auth::id())->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => new ScanResource($scan),
        ]);
    }

    /**
     * Find alternative products
     */
    protected function findAlternatives(Product $product): ?object
    {
        return Product::with(['foodScore', 'cosmeticScore'])
            ->where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->where(function ($query) {
                $query->whereHas('foodScore', function ($q) {
                    $q->where('overall_score', '>=', 70);
                })->orWhereHas('cosmeticScore', function ($q) {
                    $q->where('overall_score', '>=', 70);
                });
            })
            ->limit(3)
            ->get();
    }

    /**
     * Interpret score in human-readable format
     */
    protected function interpretScore(?float $score, ?Product $product = null): array
    {
        if ($score === null) {
            return [
                'grade' => 'Unknown',
                'description' => 'We found the product, but there is not enough verified data to score this category confidently yet.',
                'color' => 'gray',
            ];
        }

        $isCosmetic = $product?->isCosmetic() ?? false;

        if ($score >= 80) {
            return [
                'grade' => 'Excellent',
                'description' => $isCosmetic
                    ? 'This product has a strong safety profile with limited flagged concerns.'
                    : 'This product scores strongly with limited flagged concerns.',
                'color' => 'green',
            ];
        } elseif ($score >= 60) {
            return [
                'grade' => 'Good',
                'description' => $isCosmetic
                    ? 'This product has a generally good safety profile with some minor concerns.'
                    : 'This product has a generally good profile with some minor concerns.',
                'color' => 'lightgreen',
            ];
        } elseif ($score >= 40) {
            return [
                'grade' => 'Moderate',
                'description' => 'This product is average. Check the detailed warnings before relying on it regularly.',
                'color' => 'yellow',
            ];
        } elseif ($score >= 20) {
            return [
                'grade' => 'Poor',
                'description' => 'This product has several concerns. Consider better-scoring alternatives.',
                'color' => 'orange',
            ];
        } else {
            return [
                'grade' => 'Avoid',
                'description' => 'This product is not recommended. Please consider healthier alternatives.',
                'color' => 'red',
            ];
        }
    }
}
