<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BillingInvoice extends Model
{
    use HasFactory, HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'subscription_id',
        'provider',
        'provider_invoice_id',
        'provider_payment_intent_id',
        'provider_charge_id',
        'currency',
        'subtotal',
        'tax',
        'total',
        'amount_paid',
        'amount_due',
        'status',
        'billing_reason',
        'hosted_invoice_url',
        'invoice_pdf_url',
        'issued_at',
        'due_at',
        'paid_at',
        'failed_at',
        'metadata',
    ];

    protected $casts = [
        'subtotal' => 'integer',
        'tax' => 'integer',
        'total' => 'integer',
        'amount_paid' => 'integer',
        'amount_due' => 'integer',
        'issued_at' => 'datetime',
        'due_at' => 'datetime',
        'paid_at' => 'datetime',
        'failed_at' => 'datetime',
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

    public function transactions(): HasMany
    {
        return $this->hasMany(BillingTransaction::class, 'billing_invoice_id');
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(BillingRefund::class, 'billing_invoice_id');
    }
}
