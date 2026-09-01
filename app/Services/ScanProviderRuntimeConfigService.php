<?php

namespace App\Services;

use App\Models\ScanProvider;
use Illuminate\Support\Facades\Schema;

class ScanProviderRuntimeConfigService
{
    protected static array $records = [];

    public function openAi(): array
    {
        $defaults = config('services.openai', []);
        $record = $this->provider('openai_vision');

        if ($record === null) {
            return array_merge($defaults, [
                'provider_key' => 'openai_vision',
                'is_active' => filled($defaults['api_key'] ?? null),
            ]);
        }

        return array_merge(
            $defaults,
            $record->settings ?? [],
            $record->safeCredentials(),
            [
                'provider_key' => $record->provider_key,
                'is_active' => (bool) $record->is_active,
                'timeout' => (int) ($record->timeout_seconds ?: ($defaults['timeout'] ?? 30)),
                'retry_attempts' => (int) ($record->retry_attempts ?: ($defaults['retry_attempts'] ?? 1)),
            ]
        );
    }

    public function googleCloudVision(): array
    {
        $defaults = config('services.google_cloud_vision', []);
        $record = $this->provider('google_cloud_vision');
        $hasEnvCredentials = filled($defaults['credentials_json'] ?? null) || filled($defaults['credentials_path'] ?? null);

        if ($record === null) {
            return array_merge($defaults, [
                'provider_key' => 'google_cloud_vision',
                'is_active' => $hasEnvCredentials,
            ]);
        }

        return array_merge(
            $defaults,
            $record->settings ?? [],
            $record->safeCredentials(),
            [
                'provider_key' => $record->provider_key,
                'is_active' => (bool) $record->is_active,
                'timeout' => (int) ($record->timeout_seconds ?: ($defaults['timeout'] ?? 30)),
                'retry_attempts' => (int) ($record->retry_attempts ?: ($defaults['retry_attempts'] ?? 1)),
            ]
        );
    }

    protected function provider(string $providerKey): ?ScanProvider
    {
        if (array_key_exists($providerKey, self::$records)) {
            return self::$records[$providerKey];
        }

        if (!class_exists(ScanProvider::class) || !Schema::hasTable('scan_providers')) {
            return self::$records[$providerKey] = null;
        }

        return self::$records[$providerKey] = ScanProvider::query()
            ->where('provider_key', $providerKey)
            ->first();
    }
}
