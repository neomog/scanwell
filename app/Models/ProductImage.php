<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ProductImage extends Model
{
    use HasFactory, HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'product_id',
        'disk',
        'path',
        'url',
        'source',
        'is_primary',
        'sort_order',
        'uploaded_by',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected $appends = [
        'resolved_url',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function getResolvedUrlAttribute(): ?string
    {
        if ($this->url) {
            return $this->url;
        }

        if ($this->disk && $this->path) {
            return Storage::disk($this->disk)->url($this->path);
        }

        return null;
    }
}
