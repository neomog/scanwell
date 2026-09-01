<?php
// app/Models/Scan.php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Scan extends Model
{
    use HasFactory, HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'product_id',
        'barcode',
        'scan_timestamp',
        'device_type',
        'status',
        'scan_metadata',
    ];

    protected $casts = [
        'scan_timestamp' => 'datetime',
        'scan_metadata' => 'array',
    ];

    protected $attributes = [
        'status' => 'pending',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function providerLookups(): HasMany
    {
        return $this->hasMany(ScanProviderLookup::class);
    }

    public function markAsCompleted(Product $product, array $metadata = []): void
    {
        $this->update([
            'product_id' => $product->id,
            'status' => 'completed',
            'scan_metadata' => array_merge($this->scan_metadata ?? [], $metadata),
        ]);
    }

    public function markAsFailed(string $error, array $metadata = []): void
    {
        $this->update([
            'status' => 'failed',
            'scan_metadata' => array_merge($this->scan_metadata ?? [], $metadata, ['error' => $error]),
        ]);
    }
}
//
//namespace App\Models;
//
//use Illuminate\Database\Eloquent\Concerns\HasUuids;
//use Illuminate\Database\Eloquent\Factories\HasFactory;
//use Illuminate\Database\Eloquent\Model;
//use Illuminate\Database\Eloquent\Relations\BelongsTo;
//
//class Scan extends Model
//{
//    use HasFactory, HasUuids;
//
//    protected $keyType = 'string';
//    public $incrementing = false;
//
//    protected $fillable = [
//        'user_id',
//        'product_id',
//        'barcode',
//        'scan_timestamp',
//        'device_type',
//        'status',
//        'scan_metadata',
//    ];
//
//    protected $casts = [
//        'scan_timestamp' => 'datetime',
//        'scan_metadata' => 'array',
//    ];
//
//    protected $attributes = [
//        'status' => 'pending',
//    ];
//
//    public function user(): BelongsTo
//    {
//        return $this->belongsTo(User::class);
//    }
//
//    public function product(): BelongsTo
//    {
//        return $this->belongsTo(Product::class);
//    }
//
//    public function markAsCompleted(Product $product): void
//    {
//        $this->update([
//            'product_id' => $product->id,
//            'status' => 'completed',
//        ]);
//    }
//
//    public function markAsFailed(string $error): void
//    {
//        $this->update([
//            'status' => 'failed',
//            'scan_metadata' => array_merge($this->scan_metadata ?? [], ['error' => $error]),
//        ]);
//    }
//}
