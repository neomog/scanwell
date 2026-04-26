<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    use HasFactory, HasUuids;

    public const STATUS_TRIALING = 'trialing';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_PAST_DUE = 'past_due';
    public const STATUS_CANCELING = 'canceling';
    public const STATUS_CANCELED = 'canceled';
    public const STATUS_INCOMPLETE = 'incomplete';
    public const STATUS_UNPAID = 'unpaid';
    public const STATUS_REPLACED = 'replaced';
    public const STATUS_REFUNDED = 'refunded';

    public const CURRENT_STATUSES = [
        self::STATUS_TRIALING,
        self::STATUS_ACTIVE,
        self::STATUS_PAST_DUE,
        self::STATUS_CANCELING,
    ];

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'plan_id',
        'price_id',
        'provider',
        'status',
        'quantity',
        'currency',
        'amount',
        'stripe_customer_id',
        'stripe_subscription_id',
        'stripe_checkout_session_id',
        'stripe_payment_intent_id',
        'stripe_invoice_id',
        'starts_at',
        'current_period_starts_at',
        'current_period_ends_at',
        'trial_ends_at',
        'canceled_at',
        'ends_at',
        'refunded_at',
        'metadata',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'amount' => 'integer',
        'starts_at' => 'datetime',
        'current_period_starts_at' => 'datetime',
        'current_period_ends_at' => 'datetime',
        'trial_ends_at' => 'datetime',
        'canceled_at' => 'datetime',
        'ends_at' => 'datetime',
        'refunded_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id');
    }

    public function price(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPrice::class, 'price_id');
    }

    public function scopeCurrent($query)
    {
        return $query->whereIn('status', self::CURRENT_STATUSES);
    }

    public function isPaid(): bool
    {
        return ($this->amount ?? 0) > 0 || $this->provider === 'stripe';
    }

    public function isCurrent(): bool
    {
        return in_array($this->status, self::CURRENT_STATUSES, true);
    }
}
