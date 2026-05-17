<?php

namespace App\Jobs;

use App\Models\ScanProvider;
use App\Models\User;
use App\Services\ScanProviderImportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class ImportScanProviderProducts implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public function __construct(
        public string $scanProviderId,
        public ?string $actorId = null
    ) {
    }

    public function handle(ScanProviderImportService $importService): void
    {
        $provider = ScanProvider::query()->find($this->scanProviderId);

        if (!$provider) {
            return;
        }

        $actor = $this->actorId
            ? User::query()->find($this->actorId)
            : null;

        try {
            $summary = $importService->import($provider, $actor);

            Log::info('Scan provider import completed', $summary);
        } catch (\Throwable $exception) {
            $provider->forceFill([
                'health_status' => 'degraded',
                'last_failure_at' => now(),
                'last_error' => $exception->getMessage(),
            ])->save();

            Log::error('Scan provider import failed', [
                'provider_key' => $provider->provider_key,
                'provider_id' => $provider->id,
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }
}
