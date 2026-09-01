<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScanProviderLookup extends Model
{
    use HasFactory, HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'scan_provider_id',
        'scan_id',
        'barcode',
        'status',
        'matched',
        'product_family',
        'confidence',
        'latency_ms',
        'error_message',
        'response_summary',
    ];

    protected function casts(): array
    {
        return [
            'matched' => 'boolean',
            'confidence' => 'integer',
            'latency_ms' => 'integer',
            'response_summary' => 'array',
        ];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(ScanProvider::class, 'scan_provider_id');
    }

    public function scan(): BelongsTo
    {
        return $this->belongsTo(Scan::class);
    }
}
