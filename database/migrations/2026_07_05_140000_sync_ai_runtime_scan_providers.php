<?php

use App\Services\GoogleCloudVisionService;
use App\Services\OpenAiVisionService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $providers = [
            'openai_vision' => [
                'name' => 'OpenAI Vision Runtime',
                'driver' => OpenAiVisionService::class,
                'supported_families' => ['food', 'cosmetic', 'pet_food', 'household', 'general'],
                'settings' => [
                    'base_url' => config('services.openai.base_url', 'https://api.openai.com/v1'),
                    'image_recognition_model' => config('services.openai.image_recognition_model', 'gpt-5.4-mini'),
                    'ingredient_extraction_model' => config('services.openai.ingredient_extraction_model', config('services.openai.image_recognition_model', 'gpt-5.4-mini')),
                    'nutrition_extraction_model' => config('services.openai.nutrition_extraction_model', config('services.openai.image_recognition_model', 'gpt-5.4-mini')),
                ],
                'credentials' => array_filter([
                    'api_key' => trim((string) config('services.openai.api_key', '')),
                ], fn ($value) => $value !== ''),
                'timeout_seconds' => (int) config('services.openai.timeout', 30),
                'retry_attempts' => (int) config('services.openai.retry_attempts', 1),
                'notes' => 'Runtime configuration for OpenAI-powered image identity, ingredients, nutrition, and catalog image enrichment.',
                'priority' => 110,
            ],
            'google_cloud_vision' => [
                'name' => 'Google Cloud Vision OCR',
                'driver' => GoogleCloudVisionService::class,
                'supported_families' => ['food', 'cosmetic', 'pet_food', 'household', 'general'],
                'settings' => [
                    'base_url' => config('services.google_cloud_vision.base_url', 'https://vision.googleapis.com/v1'),
                    'token_url' => config('services.google_cloud_vision.token_url', 'https://oauth2.googleapis.com/token'),
                ],
                'credentials' => array_filter([
                    'credentials_json' => trim((string) config('services.google_cloud_vision.credentials_json', '')),
                    'credentials_path' => trim((string) config('services.google_cloud_vision.credentials_path', '')),
                ], fn ($value) => $value !== ''),
                'timeout_seconds' => (int) config('services.google_cloud_vision.timeout', 30),
                'retry_attempts' => (int) config('services.google_cloud_vision.retry_attempts', 1),
                'notes' => 'Runtime configuration for Google Cloud Vision OCR used during image, gallery, ingredient, and nutrition scans.',
                'priority' => 120,
            ],
        ];

        foreach ($providers as $providerKey => $provider) {
            $existing = DB::table('scan_providers')
                ->where('provider_key', $providerKey)
                ->first();

            if ($existing) {
                $storedCredentials = $this->decodeJsonObject($existing->credentials);
                $credentials = $storedCredentials !== [] ? $storedCredentials : $provider['credentials'];

                DB::table('scan_providers')
                    ->where('id', $existing->id)
                    ->update([
                        'name' => $provider['name'],
                        'driver' => $provider['driver'],
                        'supported_families' => json_encode($provider['supported_families']),
                        'settings' => json_encode($provider['settings']),
                        'credentials' => $credentials !== [] ? json_encode($credentials) : $existing->credentials,
                        'timeout_seconds' => $provider['timeout_seconds'],
                        'retry_attempts' => $provider['retry_attempts'],
                        'cache_ttl_minutes' => 60,
                        'notes' => $provider['notes'],
                        'is_active' => $credentials !== [] ? true : (bool) $existing->is_active,
                        'updated_at' => now(),
                    ]);

                continue;
            }

            DB::table('scan_providers')->insert([
                'id' => (string) Str::uuid(),
                'name' => $provider['name'],
                'provider_key' => $providerKey,
                'driver' => $provider['driver'],
                'is_active' => $provider['credentials'] !== [],
                'priority' => $provider['priority'],
                'supported_families' => json_encode($provider['supported_families']),
                'settings' => json_encode($provider['settings']),
                'credentials' => $provider['credentials'] !== [] ? json_encode($provider['credentials']) : null,
                'timeout_seconds' => $provider['timeout_seconds'],
                'retry_attempts' => $provider['retry_attempts'],
                'cache_ttl_minutes' => 60,
                'health_status' => 'unknown',
                'notes' => $provider['notes'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('scan_providers')
            ->whereIn('provider_key', ['openai_vision', 'google_cloud_vision'])
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
