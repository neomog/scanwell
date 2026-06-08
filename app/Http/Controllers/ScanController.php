<?php

namespace App\Http\Controllers;

use App\Exceptions\ImageScanIdentificationException;
use App\Exceptions\PlanFeatureException;
use App\Http\Requests\ImageScanRequest;
use App\Http\Requests\ScanRequest;
use App\Http\Resources\ProductContributionResource;
use App\Http\Resources\ProductResource;
use App\Http\Resources\ScanResource;
use App\Models\Product;
use App\Models\Scan;
use App\Models\UserPreference;
use App\Services\ProductAnalysisService;
use App\Services\ProductImageAnalysisService;
use App\Services\ProductContributionService;
use App\Services\SubscriptionManager;
use Error;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ScanController extends Controller
{
    public function __construct(
        protected ProductAnalysisService $analysisService,
        protected ProductImageAnalysisService $productImageAnalysisService,
        protected ProductContributionService $productContributionService,
        protected SubscriptionManager $subscriptionManager
    ) {
    }

    public function scan(ScanRequest $request): JsonResponse
    {
        try {
            $this->subscriptionManager->ensureFeature(Auth::user(), 'scans.enabled');
            $this->subscriptionManager->enforceMonthlyScanLimit(Auth::user());

            $validated = $request->validated();
            $barcode = $validated['barcode'];

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

            $product = $this->analysisService
                ->analyzeByBarcode($barcode, Auth::id(), $scan)
                ->load(['ingredients', 'nutrition', 'foodScore', 'cosmeticScore', 'images', 'barcodes']);

            $lookupSummary = $this->analysisService->lastLookupSummary();

            $scan->markAsCompleted($product, [
                'matched_provider' => data_get($product->raw_data, '_scanwell.source'),
                'product_family' => $product->resolved_product_family,
                'confidence' => data_get($product->raw_data, '_scanwell.confidence'),
                'provider_lookup' => $lookupSummary,
            ]);

            $personalizedWarnings = [];

            if (Auth::check()) {
                $preferences = UserPreference::where('user_id', Auth::id())->first();

                if ($preferences) {
                    $personalizedWarnings = $preferences->getPersonalizedWarnings($product);
                }
            }

            $alternatives = null;
            $score = $product->foodScore->overall_score ?? $product->cosmeticScore->overall_score;

            if ($score !== null && $score < 50) {
                $alternatives = $this->findAlternatives($product);
            }

            return $this->successResponse($scan, $product, $personalizedWarnings, $alternatives);
        } catch (PlanFeatureException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'feature' => $e->feature,
            ], 403);
        } catch (ImageScanIdentificationException $e) {
            if (isset($scan)) {
                $scan->markAsFailed($e->getMessage(), [
                    'provider_lookup' => $this->analysisService->lastLookupSummary(),
                    'error_code' => $e->errorCode,
                    'match_context' => $e->context,
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error_code' => $e->errorCode,
                'data' => [
                    'scan' => isset($scan) ? new ScanResource($scan->fresh()) : null,
                    'product' => null,
                    'match_context' => $e->context,
                ],
            ], $e->getCode() > 0 ? $e->getCode() : 422);
        } catch (Exception|Error $e) {
            if (isset($scan)) {
                $scan->markAsFailed($e->getMessage(), [
                    'provider_lookup' => $this->analysisService->lastLookupSummary(),
                ]);
            }

            if ((int) $e->getCode() === 404) {
                $pendingContribution = $this->productContributionService->pendingSummaryForBarcode($barcode ?? '');

                return response()->json([
                    'success' => false,
                    'message' => $pendingContribution
                        ? 'Product is not in the catalogue yet. A community submission is pending review.'
                        : 'Product not found',
                    'data' => [
                        'scan' => isset($scan) ? new ScanResource($scan) : null,
                        'product' => null,
                        'pending_contribution' => $pendingContribution
                            ? new ProductContributionResource($pendingContribution)
                            : null,
                    ],
                ], 404);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to scan product',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function scanImage(ImageScanRequest $request): JsonResponse
    {
        try {
            $this->subscriptionManager->ensureFeature(Auth::user(), 'scans.enabled');
            $this->subscriptionManager->enforceMonthlyScanLimit(Auth::user());

            $validated = $request->validated();
            $seedBarcode = $validated['barcode_hint'] ?? 'image-scan';

            $scan = Scan::create([
                'user_id' => Auth::id(),
                'barcode' => $seedBarcode,
                'scan_timestamp' => now(),
                'device_type' => $request->userAgent(),
                'status' => 'pending',
                'scan_metadata' => [
                    'ip' => $request->ip(),
                    'source' => 'mobile_app',
                    'scan_mode' => 'image',
                    'latitude' => $validated['latitude'] ?? null,
                    'longitude' => $validated['longitude'] ?? null,
                ],
            ]);

            $imageAnalysis = $this->productImageAnalysisService->analyze(
                $request->file('image'),
                $validated,
                Auth::id(),
                $scan
            );

            $product = $imageAnalysis['product']
                ->load(['ingredients', 'nutrition', 'foodScore', 'cosmeticScore', 'images', 'barcodes']);

            $scan->forceFill([
                'barcode' => $imageAnalysis['barcode'] ?? $scan->barcode,
            ])->save();

            $lookupSummary = $this->analysisService->lastLookupSummary();

            $scan->markAsCompleted($product, [
                'matched_provider' => data_get($product->raw_data, '_scanwell.source'),
                'product_family' => $product->resolved_product_family,
                'confidence' => $imageAnalysis['confidence'] ?? data_get($product->raw_data, '_scanwell.confidence'),
                'matched_by' => $imageAnalysis['matched_by'] ?? 'image',
                'analysis_source' => $imageAnalysis['analysis_source'] ?? null,
                'provider_lookup' => $lookupSummary,
                'image_scan' => [
                    'stored_image' => $imageAnalysis['stored_image'] ?? null,
                    'ocr' => $imageAnalysis['ocr'] ?? null,
                    'signals' => $imageAnalysis['signals'] ?? [],
                ],
            ]);

            $personalizedWarnings = $this->personalizedWarnings($product);
            $alternatives = $this->alternativesFor($product);

            return $this->successResponse($scan, $product, $personalizedWarnings, $alternatives);
        } catch (PlanFeatureException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'feature' => $e->feature,
            ], 403);
        } catch (Exception|Error $e) {
            if (isset($scan)) {
                $scan->markAsFailed($e->getMessage(), [
                    'provider_lookup' => $this->analysisService->lastLookupSummary(),
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to scan product image',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(string $id): JsonResponse
    {
        $scan = Scan::where('user_id', Auth::id())->findOrFail($id);
        $scan->delete();

        return response()->json([
            'success' => true,
            'message' => 'Scan removed from history',
        ]);
    }

    public function history(Request $request): JsonResponse
    {
        $baseQuery = Scan::query()->where('user_id', Auth::id());

        $summary = [
            'total' => (clone $baseQuery)->count(),
            'completed' => (clone $baseQuery)->where('status', 'completed')->count(),
            'failed' => (clone $baseQuery)->where('status', 'failed')->count(),
            'pending' => (clone $baseQuery)->where('status', 'pending')->count(),
        ];

        $query = Scan::with(['product', 'product.foodScore', 'product.cosmeticScore', 'product.images', 'product.barcodes'])
            ->where('user_id', Auth::id())
            ->orderBy('scan_timestamp', 'desc');

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        $perPage = min(max((int) $request->get('limit', 20), 1), 50);
        $scans = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => ScanResource::collection($scans),
            'meta' => [
                'total' => $scans->total(),
                'per_page' => $scans->perPage(),
                'current_page' => $scans->currentPage(),
                'last_page' => $scans->lastPage(),
                'summary' => $summary,
            ],
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $scan = Scan::with([
            'product',
            'product.ingredients',
            'product.nutrition',
            'product.foodScore',
            'product.cosmeticScore',
            'product.images',
            'product.barcodes',
        ])->where('user_id', Auth::id())->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => new ScanResource($scan),
        ]);
    }

    protected function findAlternatives(Product $product): ?object
    {
        return Product::with(['foodScore', 'cosmeticScore', 'images', 'barcodes'])
            ->where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->where(function ($query) {
                $query->whereHas('foodScore', function ($scoreQuery) {
                    $scoreQuery->where('overall_score', '>=', 70);
                })->orWhereHas('cosmeticScore', function ($scoreQuery) {
                    $scoreQuery->where('overall_score', '>=', 70);
                });
            })
            ->limit(3)
            ->get();
    }

    protected function personalizedWarnings(Product $product): array
    {
        if (!Auth::check()) {
            return [];
        }

        $preferences = UserPreference::where('user_id', Auth::id())->first();

        if (!$preferences) {
            return [];
        }

        return $preferences->getPersonalizedWarnings($product);
    }

    protected function alternativesFor(Product $product): ?object
    {
        $score = $product->foodScore->overall_score ?? $product->cosmeticScore->overall_score;

        if ($score === null || $score >= 50) {
            return null;
        }

        return $this->findAlternatives($product);
    }

    protected function successResponse(Scan $scan, Product $product, array $personalizedWarnings, ?object $alternatives): JsonResponse
    {
        $score = $product->foodScore->overall_score ?? $product->cosmeticScore->overall_score;

        return response()->json([
            'success' => true,
            'message' => 'Product scanned successfully',
            'data' => [
                'scan' => new ScanResource($scan->fresh('product')),
                'product' => new ProductResource($product),
                'personalized_warnings' => $personalizedWarnings,
                'alternatives' => $alternatives ? ProductResource::collection($alternatives) : [],
                'score_interpretation' => $this->interpretScore($score, $product),
                'remaining_monthly_scans' => $this->subscriptionManager->remainingMonthlyScans(Auth::user()),
            ],
        ]);
    }

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
        }

        if ($score >= 60) {
            return [
                'grade' => 'Good',
                'description' => $isCosmetic
                    ? 'This product has a generally good safety profile with some minor concerns.'
                    : 'This product has a generally good profile with some minor concerns.',
                'color' => 'lightgreen',
            ];
        }

        if ($score >= 40) {
            return [
                'grade' => 'Moderate',
                'description' => 'This product is average. Check the detailed warnings before relying on it regularly.',
                'color' => 'yellow',
            ];
        }

        if ($score >= 20) {
            return [
                'grade' => 'Poor',
                'description' => 'This product has several concerns. Consider better-scoring alternatives.',
                'color' => 'orange',
            ];
        }

        return [
            'grade' => 'Avoid',
            'description' => 'This product is not recommended. Please consider healthier alternatives.',
            'color' => 'red',
        ];
    }
}
