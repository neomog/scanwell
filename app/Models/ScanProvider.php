<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

class ScanProvider extends Model
{
    use HasFactory, HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'name',
        'provider_key',
        'driver',
        'is_active',
        'priority',
        'supported_families',
        'settings',
        'credentials',
        'timeout_seconds',
        'retry_attempts',
        'cache_ttl_minutes',
        'health_status',
        'last_success_at',
        'last_failure_at',
        'last_error',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'supported_families' => 'array',
            'settings' => 'array',
            'timeout_seconds' => 'integer',
            'retry_attempts' => 'integer',
            'cache_ttl_minutes' => 'integer',
            'last_success_at' => 'datetime',
            'last_failure_at' => 'datetime',
            'priority' => 'integer',
        ];
    }

    protected function credentials(): Attribute
    {
        return Attribute::make(
            get: function (mixed $value): array {
                if (is_array($value)) {
                    return $value;
                }

                if ($value === null) {
                    return [];
                }

                $raw = trim((string) $value);

                if ($raw === '') {
                    return [];
                }

                try {
                    $decrypted = Crypt::decryptString($raw);
                    $decoded = json_decode($decrypted, true);

                    return is_array($decoded) ? $decoded : [];
                } catch (\Throwable $exception) {
                    $decoded = json_decode($raw, true);

                    if (is_array($decoded)) {
                        Log::warning('Scan provider credentials are stored as legacy plaintext JSON', [
                            'provider_key' => $this->provider_key,
                            'provider_id' => $this->id,
                        ]);

                        return $decoded;
                    }

                    Log::warning('Scan provider credentials could not be decoded', [
                        'provider_key' => $this->provider_key,
                        'provider_id' => $this->id,
                        'error' => $exception->getMessage(),
                    ]);

                    return [];
                }
            },
            set: function (mixed $value): ?string {
                if ($value === null) {
                    return null;
                }

                if (is_string($value)) {
                    $value = trim($value);

                    if ($value === '') {
                        return null;
                    }

                    $decoded = json_decode($value, true);
                    $value = is_array($decoded) ? $decoded : ['value' => $value];
                }

                if (!is_array($value) || $value === []) {
                    return null;
                }

                return Crypt::encryptString(json_encode($value, JSON_UNESCAPED_SLASHES));
            }
        );
    }

    public function lookups(): HasMany
    {
        return $this->hasMany(ScanProviderLookup::class);
    }

    public function supportsFamily(?string $family): bool
    {
        if ($family === null || $family === '') {
            return true;
        }

        $supportedFamilies = $this->supported_families ?? [];

        return $supportedFamilies === [] || in_array($family, $supportedFamilies, true);
    }

    public function safeCredentials(): array
    {
        try {
            $credentials = $this->getAttribute('credentials');

            return is_array($credentials) ? $credentials : [];
        } catch (\Throwable $exception) {
            Log::warning('Scan provider credentials could not be decrypted', [
                'provider_key' => $this->provider_key,
                'provider_id' => $this->id,
                'error' => $exception->getMessage(),
            ]);

            return [];
        }
    }
}
