<?php

namespace App\Services;

use App\Models\BillingInvoice;
use App\Models\BillingRefund;
use App\Models\BillingTransaction;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Carbon;

class BillingSyncService
{
    public function syncInvoiceFromStripe(?User $user, ?Subscription $subscription, object $invoice, ?string $statusOverride = null): ?BillingInvoice
    {
        if (! $user || empty($invoice->id)) {
            return null;
        }

        $invoiceModel = BillingInvoice::query()->firstOrNew([
            'provider_invoice_id' => (string) $invoice->id,
        ]);

        $invoiceModel->fill([
            'user_id' => $user->id,
            'subscription_id' => $subscription?->id,
            'provider' => 'stripe',
            'provider_payment_intent_id' => $invoice->payment_intent ?? $invoiceModel->provider_payment_intent_id,
            'provider_charge_id' => $invoice->charge ?? $invoiceModel->provider_charge_id,
            'currency' => strtolower((string) ($invoice->currency ?? $invoiceModel->currency ?? 'usd')),
            'subtotal' => (int) ($invoice->subtotal ?? $invoiceModel->subtotal ?? 0),
            'tax' => (int) ($invoice->tax ?? $invoiceModel->tax ?? 0),
            'total' => (int) ($invoice->total ?? $invoiceModel->total ?? 0),
            'amount_paid' => (int) ($invoice->amount_paid ?? $invoiceModel->amount_paid ?? 0),
            'amount_due' => (int) ($invoice->amount_due ?? $invoiceModel->amount_due ?? 0),
            'status' => $statusOverride ?? (string) ($invoice->status ?? $invoiceModel->status ?? 'draft'),
            'billing_reason' => $invoice->billing_reason ?? $invoiceModel->billing_reason,
            'hosted_invoice_url' => $invoice->hosted_invoice_url ?? $invoiceModel->hosted_invoice_url,
            'invoice_pdf_url' => $invoice->invoice_pdf ?? $invoiceModel->invoice_pdf_url,
            'issued_at' => $this->timestampToCarbon($invoice->created ?? null) ?? $invoiceModel->issued_at,
            'due_at' => $this->timestampToCarbon($invoice->due_date ?? null) ?? $invoiceModel->due_at,
            'paid_at' => $this->timestampToCarbon(data_get($invoice, 'status_transitions.paid_at')) ?? $invoiceModel->paid_at,
            'failed_at' => $statusOverride === 'payment_failed'
                ? now()
                : ($invoiceModel->failed_at && ($statusOverride ?? $invoice->status ?? null) !== 'paid'
                    ? $invoiceModel->failed_at
                    : null),
            'metadata' => array_merge($invoiceModel->metadata ?? [], [
                'collection_method' => $invoice->collection_method ?? null,
                'subscription' => $invoice->subscription ?? null,
                'attempt_count' => $invoice->attempt_count ?? null,
            ]),
        ]);

        if (($statusOverride ?? $invoice->status ?? null) === 'paid') {
            $invoiceModel->failed_at = null;
        }

        $invoiceModel->save();

        return $invoiceModel;
    }

    public function syncInvoiceTransaction(
        ?User $user,
        ?Subscription $subscription,
        ?BillingInvoice $invoice,
        object $stripeInvoice,
        string $status,
        ?string $description = null
    ): ?BillingTransaction {
        if (! $user || ! $invoice) {
            return null;
        }

        $providerTransactionId = $stripeInvoice->payment_intent
            ?? $stripeInvoice->charge
            ?? sprintf('%s:%s', (string) $stripeInvoice->id, $status);

        $transaction = BillingTransaction::query()->firstOrNew([
            'provider_transaction_id' => (string) $providerTransactionId,
        ]);

        $transaction->fill([
            'user_id' => $user->id,
            'subscription_id' => $subscription?->id,
            'billing_invoice_id' => $invoice->id,
            'provider' => 'stripe',
            'type' => 'invoice_payment',
            'status' => $status,
            'amount' => (int) ($status === 'failed'
                ? ($stripeInvoice->amount_due ?? $stripeInvoice->total ?? 0)
                : ($stripeInvoice->amount_paid ?? $stripeInvoice->total ?? 0)),
            'currency' => strtolower((string) ($stripeInvoice->currency ?? $invoice->currency ?? 'usd')),
            'description' => $description ?? ($stripeInvoice->description ?? 'Subscription invoice payment'),
            'failure_code' => data_get($stripeInvoice, 'last_finalization_error.code')
                ?? data_get($stripeInvoice, 'last_payment_error.code'),
            'failure_message' => data_get($stripeInvoice, 'last_finalization_error.message')
                ?? data_get($stripeInvoice, 'last_payment_error.message'),
            'occurred_at' => $status === 'succeeded'
                ? ($invoice->paid_at ?? now())
                : now(),
            'metadata' => array_merge($transaction->metadata ?? [], [
                'invoice_id' => $stripeInvoice->id ?? null,
                'attempt_count' => $stripeInvoice->attempt_count ?? null,
            ]),
        ]);

        $transaction->save();

        return $transaction;
    }

    public function recordRefundFromStripe(
        Subscription $subscription,
        object $refund,
        ?string $reason = null,
        ?string $requestedByType = null,
        ?string $requestedById = null
    ): BillingRefund {
        $invoice = BillingInvoice::query()
            ->where('provider_payment_intent_id', $subscription->stripe_payment_intent_id)
            ->orWhere('provider_invoice_id', $subscription->stripe_invoice_id)
            ->latest('paid_at')
            ->first();

        $transaction = BillingTransaction::query()
            ->where('provider_transaction_id', $refund->payment_intent ?? $subscription->stripe_payment_intent_id)
            ->orWhere('billing_invoice_id', $invoice?->id)
            ->latest('occurred_at')
            ->first();

        $refundModel = BillingRefund::query()->firstOrNew([
            'provider_refund_id' => (string) $refund->id,
        ]);

        $refundModel->fill([
            'user_id' => $subscription->user_id,
            'subscription_id' => $subscription->id,
            'billing_invoice_id' => $invoice?->id,
            'billing_transaction_id' => $transaction?->id,
            'provider' => 'stripe',
            'amount' => (int) ($refund->amount ?? 0),
            'currency' => strtolower((string) ($refund->currency ?? $subscription->currency ?? 'usd')),
            'reason' => $reason ?? $refund->reason ?? $refundModel->reason,
            'status' => $refund->status ?? $refundModel->status ?? 'pending',
            'requested_by_type' => $requestedByType ?? $refundModel->requested_by_type,
            'requested_by_id' => $requestedById ?? $refundModel->requested_by_id,
            'refunded_at' => $this->timestampToCarbon($refund->created ?? null) ?? now(),
            'metadata' => array_merge($refundModel->metadata ?? [], [
                'payment_intent' => $refund->payment_intent ?? null,
            ]),
        ]);

        $refundModel->save();

        return $refundModel;
    }

    protected function timestampToCarbon(mixed $timestamp): ?Carbon
    {
        if (! $timestamp) {
            return null;
        }

        return Carbon::createFromTimestamp((int) $timestamp);
    }
}
