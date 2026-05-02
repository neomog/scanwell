<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionEvent extends Model
{
    use HasFactory, HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'subscription_id',
        'event_type',
        'source',
        'actor_id',
        'from_plan_id',
        'to_plan_id',
        'from_price_id',
        'to_price_id',
        'status_before',
        'status_after',
        'reason',
        'effective_at',
        'metadata',
    ];

    protected $casts = [
        'effective_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function fromPlan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'from_plan_id');
    }

    public function toPlan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'to_plan_id');
    }

    public function fromPrice(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPrice::class, 'from_price_id');
    }

    public function toPrice(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPrice::class, 'to_price_id');
    }
}
