<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductAuditLog extends Model
{
    use HasFactory, HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'actor_id',
        'product_id',
        'contribution_id',
        'action',
        'description',
        'changes',
        'metadata',
    ];

    protected $casts = [
        'changes' => 'array',
        'metadata' => 'array',
    ];

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function contribution(): BelongsTo
    {
        return $this->belongsTo(ProductContribution::class, 'contribution_id');
    }
}
