<?php

namespace App\Jobs;

use App\Models\Scan;
use App\Services\ProductAnalysisService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessProductScan implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected Scan $scan;

    public function __construct(Scan $scan)
    {
        $this->scan = $scan;
    }

    public function handle(ProductAnalysisService $analysisService): void
    {
        try {
            $product = $analysisService->analyzeByBarcode(
                $this->scan->barcode,
                $this->scan->user_id,
                $this->scan
            );

            $lookupSummary = $analysisService->lastLookupSummary();

            $this->scan->markAsCompleted($product, [
                'matched_provider' => data_get($product->raw_data, '_scanwell.source'),
                'product_family' => $product->product_family,
                'confidence' => data_get($product->raw_data, '_scanwell.confidence'),
                'provider_lookup' => $lookupSummary,
            ]);

            Log::info('Scan processed successfully', [
                'scan_id' => $this->scan->id,
                'product_id' => $product->id,
            ]);

        } catch (\Exception $e) {
            $this->scan->markAsFailed($e->getMessage(), [
                'provider_lookup' => $analysisService->lastLookupSummary(),
            ]);

            Log::error('Failed to process scan', [
                'scan_id' => $this->scan->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
