<?php

use App\Services\OpenAiBarcodeWebSearchService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $provider = [
            'name' => 'OpenAI Barcode Web Search',
            'provider_key' => 'openai_barcode_web',
            'driver' => OpenAiBarcodeWebSearchService::class,
            'is_active' => (bool) config('services.openai.barcode_web_search_enabled', true)
                && filled(config('services.openai.api_key')),
            'priority' => 999,
            'supported_families' => json_encode(['food', 'cosmetic', 'pet_food', 'household', 'general']),
            'settings' => json_encode([
                'base_url' => config('services.openai.base_url'),
                'barcode_web_search_model' => config('services.openai.barcode_web_search_model'),
            ]),
            'credentials' => filled(config('services.openai.api_key'))
                ? json_encode(['api_key' => config('services.openai.api_key')])
                : null,
            'timeout_seconds' => (int) config('services.openai.timeout', 30),
            'retry_attempts' => (int) config('services.openai.retry_attempts', 1),
            'cache_ttl_minutes' => 1440,
            'health_status' => 'unknown',
            'notes' => 'Fallback for exact barcode misses. Uses OpenAI web search and never replaces local/Open Facts matches.',
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $existing = DB::table('scan_providers')
            ->where('provider_key', $provider['provider_key'])
            ->first();

        if ($existing) {
            DB::table('scan_providers')
                ->where('id', $existing->id)
                ->update([
                    'driver' => $provider['driver'],
                    'is_active' => $provider['is_active'],
                    'supported_families' => $provider['supported_families'],
                    'settings' => $provider['settings'],
                    'credentials' => $provider['credentials'],
                    'timeout_seconds' => $provider['timeout_seconds'],
                    'retry_attempts' => $provider['retry_attempts'],
                    'cache_ttl_minutes' => $provider['cache_ttl_minutes'],
                    'notes' => $provider['notes'],
                    'updated_at' => now(),
                ]);

            return;
        }

        DB::table('scan_providers')->insert(array_merge($provider, [
            'id' => (string) Str::uuid(),
        ]));
    }

    public function down(): void
    {
        DB::table('scan_providers')
            ->where('provider_key', 'openai_barcode_web')
            ->delete();
    }
};
