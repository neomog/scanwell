<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BillingTransaction extends Model
{
    use HasFactory, HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'subscription_id',
        'billing_invoice_id',
        'provider',
        'provider_transaction_id',
        'type',
        'status',
        'amount',
        'currency',
        'description',
        'payment_method_brand',
        'payment_method_last4',
        'failure_code',
        'failure_message',
        'occurred_at',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'integer',
        'occurred_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(BillingInvoice::class, 'billing_invoice_id');
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(BillingRefund::class, 'billing_transaction_id');
    }
}
