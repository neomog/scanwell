<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPrice extends Model
{
    use HasFactory;

    protected $fillable = [
        'plan_id',
        'name',
        'amount',
        'currency',
        'billing_interval',
        'billing_interval_count',
        'trial_days',
        'stripe_price_id',
        'is_active',
        'is_default',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'integer',
        'billing_interval_count' => 'integer',
        'trial_days' => 'integer',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'metadata' => 'array',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id');
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, 'price_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function formattedAmount(): string
    {
        return strtoupper($this->currency).' '.number_format($this->amount / 100, 2);
    }

    public function isFree(): bool
    {
        return $this->amount === 0;
    }
}
