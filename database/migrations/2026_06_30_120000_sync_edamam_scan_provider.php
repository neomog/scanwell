<?php

use App\Services\EdamamFoodDatabaseService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $envCredentials = array_filter([
            'app_id' => trim((string) config('services.edamam.app_id', '')),
            'app_key' => trim((string) config('services.edamam.app_key', '')),
        ], fn ($value) => $value !== '');

        $settings = [
            'base_url' => config('services.edamam.base_url', 'https://api.edamam.com/api/food-database/v2/parser'),
            'category' => config('services.edamam.category', 'packaged-foods'),
            'nutrition_type' => config('services.edamam.nutrition_type', 'cooking'),
        ];

        $existing = DB::table('scan_providers')
            ->where('provider_key', 'edamam')
            ->first();

        if ($existing) {
            $storedCredentials = $this->decodeJsonObject($existing->credentials);
            $credentials = $storedCredentials !== [] ? $storedCredentials : $envCredentials;

            DB::table('scan_providers')
                ->where('id', $existing->id)
                ->update([
                    'name' => 'Edamam Food Database',
                    'driver' => EdamamFoodDatabaseService::class,
                    'supported_families' => json_encode(['food']),
                    'settings' => json_encode($settings),
                    'credentials' => $credentials !== [] ? json_encode($credentials) : $existing->credentials,
                    'timeout_seconds' => (int) config('services.edamam.timeout', 10),
                    'retry_attempts' => (int) config('services.edamam.retry_attempts', 1),
                    'cache_ttl_minutes' => 1440,
                    'is_active' => $credentials !== [] ? true : (bool) $existing->is_active,
                    'notes' => 'Paid food enrichment API. Requires app_id and app_key in credentials JSON.',
                    'updated_at' => now(),
                ]);

            return;
        }

        DB::table('scan_providers')->insert([
            'id' => (string) Str::uuid(),
            'name' => 'Edamam Food Database',
            'provider_key' => 'edamam',
            'driver' => EdamamFoodDatabaseService::class,
            'is_active' => $envCredentials !== [],
            'priority' => 20,
            'supported_families' => json_encode(['food']),
            'settings' => json_encode($settings),
            'credentials' => $envCredentials !== [] ? json_encode($envCredentials) : null,
            'timeout_seconds' => (int) config('services.edamam.timeout', 10),
            'retry_attempts' => (int) config('services.edamam.retry_attempts', 1),
            'cache_ttl_minutes' => 1440,
            'health_status' => 'unknown',
            'notes' => 'Paid food enrichment API. Requires app_id and app_key in credentials JSON.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('scan_providers')
            ->where('provider_key', 'edamam')
            ->update([
                'is_active' => false,
                'updated_at' => now(),
            ]);
    }

    protected function decodeJsonObject(mixed $value): array
    {
        if (!is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }
};
