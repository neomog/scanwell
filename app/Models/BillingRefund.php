<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillingRefund extends Model
{
    use HasFactory, HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'subscription_id',
        'billing_invoice_id',
        'billing_transaction_id',
        'provider',
        'provider_refund_id',
        'amount',
        'currency',
        'reason',
        'status',
        'requested_by_type',
        'requested_by_id',
        'refunded_at',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'integer',
        'refunded_at' => 'datetime',
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

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(BillingTransaction::class, 'billing_transaction_id');
    }
}
