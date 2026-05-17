<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
            'credentials' => 'encrypted:array',
            'timeout_seconds' => 'integer',
            'retry_attempts' => 'integer',
            'cache_ttl_minutes' => 'integer',
            'last_success_at' => 'datetime',
            'last_failure_at' => 'datetime',
            'priority' => 'integer',
        ];
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
}
