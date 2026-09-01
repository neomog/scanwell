<?php

use App\Services\EdamamFoodDatabaseService;
use App\Services\GoogleCloudVisionService;
use App\Services\Gs1UsProductService;
use App\Services\OpenAiVisionService;
use App\Services\UsdaFoodDataCentralService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $providers = [
            [
                'name' => 'OpenAI Vision Runtime',
                'provider_key' => 'openai_vision',
                'driver' => OpenAiVisionService::class,
                'is_active' => (bool) filled(config('services.openai.api_key')),
                'priority' => 110,
                'supported_families' => json_encode(['food', 'cosmetic', 'pet_food', 'household', 'general']),
                'settings' => json_encode([
                    'base_url' => config('services.openai.base_url'),
                    'image_recognition_model' => config('services.openai.image_recognition_model', 'gpt-5.4-mini'),
                    'ingredient_extraction_model' => config('services.openai.ingredient_extraction_model', config('services.openai.image_recognition_model', 'gpt-5.4-mini')),
                    'nutrition_extraction_model' => config('services.openai.nutrition_extraction_model', config('services.openai.image_recognition_model', 'gpt-5.4-mini')),
                ]),
                'credentials' => filled(config('services.openai.api_key'))
                    ? json_encode(['api_key' => config('services.openai.api_key')])
                    : null,
                'timeout_seconds' => (int) config('services.openai.timeout', 30),
                'retry_attempts' => (int) config('services.openai.retry_attempts', 1),
                'cache_ttl_minutes' => 60,
                'health_status' => 'unknown',
                'notes' => 'Runtime configuration for OpenAI-powered image identity, ingredients, nutrition, and catalog image enrichment.',
            ],
            [
                'name' => 'Google Cloud Vision OCR',
                'provider_key' => 'google_cloud_vision',
                'driver' => GoogleCloudVisionService::class,
                'is_active' => (bool) (filled(config('services.google_cloud_vision.credentials_json')) || filled(config('services.google_cloud_vision.credentials_path'))),
                'priority' => 120,
                'supported_families' => json_encode(['food', 'cosmetic', 'pet_food', 'household', 'general']),
                'settings' => json_encode([
                    'base_url' => config('services.google_cloud_vision.base_url'),
                    'token_url' => config('services.google_cloud_vision.token_url'),
                ]),
                'credentials' => (filled(config('services.google_cloud_vision.credentials_json')) || filled(config('services.google_cloud_vision.credentials_path')))
                    ? json_encode(array_filter([
                        'credentials_json' => config('services.google_cloud_vision.credentials_json'),
                        'credentials_path' => config('services.google_cloud_vision.credentials_path'),
                    ], fn ($value) => filled($value)))
                    : null,
                'timeout_seconds' => (int) config('services.google_cloud_vision.timeout', 30),
                'retry_attempts' => (int) config('services.google_cloud_vision.retry_attempts', 1),
                'cache_ttl_minutes' => 60,
                'health_status' => 'unknown',
                'notes' => 'Runtime configuration for Google Cloud Vision OCR used during image, gallery, ingredient, and nutrition scans.',
            ],
            [
                'name' => 'Edamam Food Database',
                'provider_key' => 'edamam',
                'driver' => EdamamFoodDatabaseService::class,
                'is_active' => false,
                'priority' => 20,
                'supported_families' => json_encode(['food']),
                'settings' => json_encode([
                    'base_url' => config('services.edamam.base_url'),
                    'category' => config('services.edamam.category', 'packaged-foods'),
                    'nutrition_type' => config('services.edamam.nutrition_type', 'cooking'),
                ]),
                'credentials' => null,
                'timeout_seconds' => (int) config('services.edamam.timeout', 10),
                'retry_attempts' => (int) config('services.edamam.retry_attempts', 1),
                'cache_ttl_minutes' => 1440,
                'health_status' => 'unknown',
                'notes' => 'Paid food enrichment API. Requires app_id and app_key in credentials JSON.',
            ],
            [
                'name' => 'GS1 US / Verified by GS1',
                'provider_key' => 'gs1_us',
                'driver' => Gs1UsProductService::class,
                'is_active' => false,
                'priority' => 10,
                'supported_families' => json_encode(['food', 'cosmetic', 'pet_food', 'household', 'general']),
                'settings' => json_encode([
                    'base_url' => config('services.gs1_us.base_url'),
                    'http_method' => config('services.gs1_us.http_method', 'GET'),
                    'barcode_field' => config('services.gs1_us.barcode_field', 'gtin'),
                    'barcode_path' => config('services.gs1_us.barcode_path', 'gtin'),
                ]),
                'credentials' => null,
                'timeout_seconds' => (int) config('services.gs1_us.timeout', 12),
                'retry_attempts' => (int) config('services.gs1_us.retry_attempts', 1),
                'cache_ttl_minutes' => 1440,
                'health_status' => 'unknown',
                'notes' => 'Enterprise GS1 integration. Configure the exact endpoint from the GS1 US Developer Portal plus APIKey and optional account_id.',
            ],
            [
                'name' => 'USDA FoodData Central',
                'provider_key' => 'usda_fdc',
                'driver' => UsdaFoodDataCentralService::class,
                'is_active' => false,
                'priority' => 30,
                'supported_families' => json_encode(['food']),
                'settings' => json_encode([
                    'base_url' => config('services.usda_fdc.base_url'),
                    'data_types' => config('services.usda_fdc.data_types', ['Branded']),
                ]),
                'credentials' => null,
                'timeout_seconds' => (int) config('services.usda_fdc.timeout', 10),
                'retry_attempts' => (int) config('services.usda_fdc.retry_attempts', 1),
                'cache_ttl_minutes' => 1440,
                'health_status' => 'unknown',
                'notes' => 'Trusted USDA branded-food enrichment provider. Search-based, food-only, and requires an API key.',
            ],
        ];

        foreach ($providers as $provider) {
            $existing = DB::table('scan_providers')
                ->where('provider_key', $provider['provider_key'])
                ->first();

            if ($existing) {
                DB::table('scan_providers')
                    ->where('id', $existing->id)
                    ->update([
                        'name' => $provider['name'],
                        'driver' => $provider['driver'],
                        'supported_families' => $provider['supported_families'],
                        'notes' => $provider['notes'],
                        'updated_at' => now(),
                    ]);

                continue;
            }

            DB::table('scan_providers')->insert([
                'id' => (string) Str::uuid(),
                'name' => $provider['name'],
                'provider_key' => $provider['provider_key'],
                'driver' => $provider['driver'],
                'is_active' => $provider['is_active'],
                'priority' => $provider['priority'],
                'supported_families' => $provider['supported_families'],
                'settings' => $provider['settings'],
                'credentials' => $provider['credentials'],
                'timeout_seconds' => $provider['timeout_seconds'],
                'retry_attempts' => $provider['retry_attempts'],
                'cache_ttl_minutes' => $provider['cache_ttl_minutes'],
                'health_status' => $provider['health_status'],
                'notes' => $provider['notes'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('scan_providers')
            ->whereIn('provider_key', ['openai_vision', 'google_cloud_vision', 'edamam', 'gs1_us', 'usda_fdc'])
            ->delete();
    }
};
