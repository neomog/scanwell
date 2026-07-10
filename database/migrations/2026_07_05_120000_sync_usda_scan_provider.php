<?php

use App\Services\UsdaFoodDataCentralService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $envCredentials = array_filter([
            'api_key' => trim((string) config('services.usda_fdc.api_key', '')),
        ], fn ($value) => $value !== '');

        $settings = [
            'base_url' => config('services.usda_fdc.base_url', 'https://api.nal.usda.gov/fdc/v1/foods/search'),
            'data_types' => config('services.usda_fdc.data_types', ['Branded']),
        ];

        $existing = DB::table('scan_providers')
            ->where('provider_key', 'usda_fdc')
            ->first();

        if ($existing) {
            $storedCredentials = $this->decodeJsonObject($existing->credentials);
            $credentials = $storedCredentials !== [] ? $storedCredentials : $envCredentials;

            DB::table('scan_providers')
                ->where('id', $existing->id)
                ->update([
                    'name' => 'USDA FoodData Central',
                    'driver' => UsdaFoodDataCentralService::class,
                    'supported_families' => json_encode(['food']),
                    'settings' => json_encode($settings),
                    'credentials' => $credentials !== [] ? json_encode($credentials) : $existing->credentials,
                    'timeout_seconds' => (int) config('services.usda_fdc.timeout', 10),
                    'retry_attempts' => (int) config('services.usda_fdc.retry_attempts', 1),
                    'cache_ttl_minutes' => 1440,
                    'notes' => 'Trusted USDA branded-food enrichment provider. Search-based, food-only, and requires an API key.',
                    'is_active' => $credentials !== [] ? true : (bool) $existing->is_active,
                    'updated_at' => now(),
                ]);

            return;
        }

        DB::table('scan_providers')->insert([
            'id' => (string) Str::uuid(),
            'name' => 'USDA FoodData Central',
            'provider_key' => 'usda_fdc',
            'driver' => UsdaFoodDataCentralService::class,
            'is_active' => $envCredentials !== [],
            'priority' => 30,
            'supported_families' => json_encode(['food']),
            'settings' => json_encode($settings),
            'credentials' => $envCredentials !== [] ? json_encode($envCredentials) : null,
            'timeout_seconds' => (int) config('services.usda_fdc.timeout', 10),
            'retry_attempts' => (int) config('services.usda_fdc.retry_attempts', 1),
            'cache_ttl_minutes' => 1440,
            'health_status' => 'unknown',
            'notes' => 'Trusted USDA branded-food enrichment provider. Search-based, food-only, and requires an API key.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('scan_providers')
            ->where('provider_key', 'usda_fdc')
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
