<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BillingInvoice;
use App\Models\BillingTransaction;
use App\Models\Subscription;
use App\Models\SubscriptionEvent;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionPrice;
use App\Services\StripeSubscriptionService;
use App\Services\SubscriptionEventService;
use App\Services\SubscriptionManager;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Throwable;

class BillingController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected SubscriptionManager $subscriptionManager,
        protected StripeSubscriptionService $stripeSubscriptionService,
        protected SubscriptionEventService $subscriptionEventService
    ) {}

    public function plans(): JsonResponse
    {
        $plans = SubscriptionPlan::query()
            ->with([
                'prices' => fn ($query) => $query
                    ->where('is_active', true)
                    ->orderByDesc('is_default')
                    ->orderBy('amount'),
            ])
            ->where('is_active', true)
            ->orderBy('display_order')
            ->get();

        return $this->success([
            'plans' => $plans->map(fn (SubscriptionPlan $plan) => $this->transformPlan($plan)),
            'feature_catalog' => config('subscriptions.feature_catalog', []),
            'stripe' => [
                'configured' => $this->stripeSubscriptionService->isConfigured(),
            ],
        ], 'Available plans');
    }

    public function subscription(Request $request): JsonResponse
    {
        $user = $request->user();
        $subscription = $this->subscriptionManager
            ->currentSubscription($user)
            ->loadMissing(['plan', 'price']);

        $latestInvoice = $user->billingInvoices()
            ->latest('issued_at')
            ->latest('created_at')
            ->first();

        $latestFailedPayment = $user->billingInvoices()
            ->whereNotNull('failed_at')
            ->latest('failed_at')
            ->first();

        $historyPreview = $user->subscriptionEvents()
            ->with(['actor', 'fromPlan', 'toPlan', 'fromPrice', 'toPrice'])
            ->latest()
            ->limit(5)
            ->get();

        return $this->success([
            'subscription' => $this->transformSubscription($subscription, [
                'latest_invoice' => $latestInvoice ? $this->transformInvoice($latestInvoice) : null,
                'has_failed_payment' => (bool) $latestFailedPayment,
                'latest_failed_payment' => $latestFailedPayment ? $this->transformInvoice($latestFailedPayment) : null,
                'history_preview' => $historyPreview->map(fn (SubscriptionEvent $event) => $this->transformEvent($event))->values(),
            ]),
            'usage' => [
                'monthly_scans' => [
                    'used' => $this->subscriptionManager->monthlyScanUsage($user),
                    'limit' => $this->subscriptionManager->monthlyScanLimit($user),
                    'remaining' => $this->subscriptionManager->remainingMonthlyScans($user),
                ],
            ],
            'feature_catalog' => config('subscriptions.feature_catalog', []),
            'stripe' => [
                'configured' => $this->stripeSubscriptionService->isConfigured(),
                'can_checkout' => $this->stripeSubscriptionService->isConfigured(),
                'can_cancel' => $subscription->provider === 'stripe' && filled($subscription->stripe_subscription_id),
            ],
        ], 'Current subscription');
    }

    public function history(Request $request): JsonResponse
    {
        $events = $request->user()->subscriptionEvents()
            ->with(['actor', 'fromPlan', 'toPlan', 'fromPrice', 'toPrice'])
            ->latest()
            ->paginate(15);

        return $this->success([
            'history' => collect($events->items())
                ->map(fn (SubscriptionEvent $event) => $this->transformEvent($event))
                ->values(),
            'pagination' => $this->paginationMeta($events),
        ], 'Subscription history');
    }

    public function invoices(Request $request): JsonResponse
    {
        $invoices = $request->user()->billingInvoices()
            ->latest('issued_at')
            ->latest('created_at')
            ->paginate(15);

        return $this->success([
            'invoices' => collect($invoices->items())
                ->map(fn (BillingInvoice $invoice) => $this->transformInvoice($invoice))
                ->values(),
            'pagination' => $this->paginationMeta($invoices),
        ], 'Billing invoices');
    }

    public function transactions(Request $request): JsonResponse
    {
        $transactions = $request->user()->billingTransactions()
            ->with('invoice')
            ->latest('occurred_at')
            ->latest('created_at')
            ->paginate(15);

        return $this->success([
            'transactions' => collect($transactions->items())
                ->map(fn (BillingTransaction $transaction) => $this->transformTransaction($transaction))
                ->values(),
            'pagination' => $this->paginationMeta($transactions),
        ], 'Billing transactions');
    }

    public function failedPayments(Request $request): JsonResponse
    {
        $failedPayments = $request->user()->billingInvoices()
            ->whereNotNull('failed_at')
            ->latest('failed_at')
            ->paginate(15);

        return $this->success([
            'failed_payments' => collect($failedPayments->items())
                ->map(fn (BillingInvoice $invoice) => $this->transformInvoice($invoice))
                ->values(),
            'pagination' => $this->paginationMeta($failedPayments),
        ], 'Failed payments');
    }

    public function checkout(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'price_id' => ['required', 'exists:subscription_prices,id'],
            'success_url' => ['nullable', 'string', 'max:500', fn (string $attribute, mixed $value, \Closure $fail) => $this->validateCheckoutRedirectUrl($attribute, $value, $fail)],
            'cancel_url' => ['nullable', 'string', 'max:500', fn (string $attribute, mixed $value, \Closure $fail) => $this->validateCheckoutRedirectUrl($attribute, $value, $fail)],
        ]);

        $price = SubscriptionPrice::with('plan')->findOrFail($validated['price_id']);

        try {
            $session = $this->stripeSubscriptionService->createCheckoutSession(
                $request->user(),
                $price,
                $validated['success_url'] ?? null,
                $validated['cancel_url'] ?? null
            );

            return $this->success([
                'flow' => 'external_checkout',
                'checkout_url' => $session->url,
                'session_id' => $session->id,
                'price' => [
                    'id' => $price->id,
                    'name' => $price->name,
                    'amount' => $price->amount,
                    'currency' => $price->currency,
                ],
                'plan' => [
                    'id' => $price->plan->id,
                    'slug' => $price->plan->slug,
                    'name' => $price->plan->name,
                ],
            ], 'Checkout session created');
        } catch (Throwable $exception) {
            return $this->error($exception->getMessage(), null, 422);
        }
    }

    public function cancel(Request $request): JsonResponse
    {
        $subscription = $this->subscriptionManager
            ->currentSubscription($request->user())
            ->loadMissing(['plan', 'price']);

        if (! $subscription->isPaid() || ! $subscription->stripe_subscription_id) {
            return $this->error('Only active paid Stripe subscriptions can be canceled from mobile.', null, 422);
        }

        $previousStatus = $subscription->status;

        try {
            $stripeSubscription = $this->stripeSubscriptionService->cancel($subscription, false);

            $subscription->update([
                'status' => Subscription::STATUS_CANCELING,
                'canceled_at' => now(),
                'ends_at' => isset($stripeSubscription->current_period_end)
                    ? now()->createFromTimestamp($stripeSubscription->current_period_end)
                    : $subscription->current_period_ends_at,
                'metadata' => array_merge($subscription->metadata ?? [], [
                    'cancel_at_period_end' => true,
                ]),
            ]);

            $this->subscriptionEventService->record($request->user(), $subscription, 'api_canceled_at_period_end', [
                'source' => 'api',
                'actor_id' => $request->user()->id,
                'from_plan_id' => $subscription->plan_id,
                'to_plan_id' => $subscription->plan_id,
                'from_price_id' => $subscription->price_id,
                'to_price_id' => $subscription->price_id,
                'status_before' => $previousStatus,
                'status_after' => Subscription::STATUS_CANCELING,
                'effective_at' => $subscription->display_expiry_at,
            ]);

            return $this->success([
                'subscription' => $this->transformSubscription($subscription->fresh(['plan', 'price'])),
            ], 'Subscription will cancel at the end of the current billing period.');
        } catch (Throwable $exception) {
            return $this->error($exception->getMessage(), null, 422);
        }
    }

    protected function transformPlan(SubscriptionPlan $plan): array
    {
        return [
            'id' => $plan->id,
            'slug' => $plan->slug,
            'name' => $plan->name,
            'description' => $plan->description,
            'is_default' => (bool) $plan->is_default,
            'display_order' => (int) $plan->display_order,
            'features' => $this->expandFeatures($plan->features ?? []),
            'prices' => $plan->prices->map(fn (SubscriptionPrice $price) => [
                'id' => $price->id,
                'name' => $price->name,
                'amount' => $price->amount,
                'currency' => $price->currency,
                'formatted_amount' => $price->formattedAmount(),
                'billing_interval' => $price->billing_interval,
                'billing_interval_count' => $price->billing_interval_count,
                'trial_days' => $price->trial_days,
                'is_default' => (bool) $price->is_default,
                'is_free' => $price->isFree(),
                'stripe_enabled' => filled($price->stripe_price_id),
                'purchase_channel' => $price->isFree() ? 'included' : 'external_checkout',
            ])->values(),
        ];
    }

    protected function transformSubscription(Subscription $subscription, array $extras = []): array
    {
        return array_merge([
            'id' => $subscription->id,
            'provider' => $subscription->provider,
            'status' => $subscription->status,
            'is_paid' => $subscription->isPaid(),
            'amount' => $subscription->amount,
            'currency' => $subscription->currency,
            'quantity' => $subscription->quantity,
            'starts_at' => $subscription->starts_at,
            'current_period_starts_at' => $subscription->current_period_starts_at,
            'current_period_ends_at' => $subscription->current_period_ends_at,
            'ends_at' => $subscription->ends_at,
            'display_expiry_at' => $subscription->display_expiry_at,
            'trial_ends_at' => $subscription->trial_ends_at,
            'canceled_at' => $subscription->canceled_at,
            'refunded_at' => $subscription->refunded_at,
            'plan' => $subscription->plan ? $this->transformPlan($subscription->plan->setRelation('prices', collect())) : null,
            'price' => $subscription->price ? [
                'id' => $subscription->price->id,
                'name' => $subscription->price->name,
                'amount' => $subscription->price->amount,
                'currency' => $subscription->price->currency,
                'formatted_amount' => $subscription->price->formattedAmount(),
                'billing_interval' => $subscription->price->billing_interval,
                'billing_interval_count' => $subscription->price->billing_interval_count,
                'trial_days' => $subscription->price->trial_days,
                'is_free' => $subscription->price->isFree(),
            ] : null,
            'pending_change' => data_get($subscription->metadata, 'pending_change'),
        ], $extras);
    }

    protected function transformEvent(SubscriptionEvent $event): array
    {
        return [
            'id' => $event->id,
            'event_type' => $event->event_type,
            'source' => $event->source,
            'reason' => $event->reason,
            'status_before' => $event->status_before,
            'status_after' => $event->status_after,
            'effective_at' => $event->effective_at,
            'created_at' => $event->created_at,
            'actor' => $event->actor ? [
                'id' => $event->actor->id,
                'name' => $event->actor->name,
            ] : null,
            'from_plan' => $event->fromPlan ? [
                'id' => $event->fromPlan->id,
                'name' => $event->fromPlan->name,
            ] : null,
            'to_plan' => $event->toPlan ? [
                'id' => $event->toPlan->id,
                'name' => $event->toPlan->name,
            ] : null,
            'from_price' => $event->fromPrice ? [
                'id' => $event->fromPrice->id,
                'name' => $event->fromPrice->name,
            ] : null,
            'to_price' => $event->toPrice ? [
                'id' => $event->toPrice->id,
                'name' => $event->toPrice->name,
            ] : null,
            'metadata' => $event->metadata ?? [],
        ];
    }

    protected function transformInvoice(BillingInvoice $invoice): array
    {
        return [
            'id' => $invoice->id,
            'provider_invoice_id' => $invoice->provider_invoice_id,
            'provider_payment_intent_id' => $invoice->provider_payment_intent_id,
            'status' => $invoice->status,
            'currency' => $invoice->currency,
            'subtotal' => $invoice->subtotal,
            'tax' => $invoice->tax,
            'total' => $invoice->total,
            'amount_paid' => $invoice->amount_paid,
            'amount_due' => $invoice->amount_due,
            'billing_reason' => $invoice->billing_reason,
            'hosted_invoice_url' => $invoice->hosted_invoice_url,
            'invoice_pdf_url' => $invoice->invoice_pdf_url,
            'issued_at' => $invoice->issued_at,
            'due_at' => $invoice->due_at,
            'paid_at' => $invoice->paid_at,
            'failed_at' => $invoice->failed_at,
        ];
    }

    protected function transformTransaction(BillingTransaction $transaction): array
    {
        return [
            'id' => $transaction->id,
            'provider_transaction_id' => $transaction->provider_transaction_id,
            'type' => $transaction->type,
            'status' => $transaction->status,
            'amount' => $transaction->amount,
            'currency' => $transaction->currency,
            'description' => $transaction->description,
            'payment_method_brand' => $transaction->payment_method_brand,
            'payment_method_last4' => $transaction->payment_method_last4,
            'failure_code' => $transaction->failure_code,
            'failure_message' => $transaction->failure_message,
            'occurred_at' => $transaction->occurred_at,
            'invoice' => $transaction->invoice ? [
                'id' => $transaction->invoice->id,
                'provider_invoice_id' => $transaction->invoice->provider_invoice_id,
                'status' => $transaction->invoice->status,
            ] : null,
        ];
    }

    protected function expandFeatures(array $features): array
    {
        $catalog = config('subscriptions.feature_catalog', []);
        $mapped = [];

        foreach ($catalog as $key => $definition) {
            $mapped[$key] = [
                'label' => $definition['label'] ?? $key,
                'type' => $definition['type'] ?? 'boolean',
                'value' => data_get($features, $key),
            ];
        }

        return $mapped;
    }

    protected function validateCheckoutRedirectUrl(string $attribute, mixed $value, \Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (! is_string($value)) {
            $fail("The {$attribute} field must be a valid URL.");

            return;
        }

        if (preg_match('/^[a-z][a-z0-9+.-]*:\/\/\/\S+$/i', $value) === 1) {
            return;
        }

        $parts = parse_url($value);

        if ($parts === false || empty($parts['scheme'])) {
            $fail("The {$attribute} field must be a valid URL.");

            return;
        }

        $scheme = strtolower((string) $parts['scheme']);

        if (in_array($scheme, ['http', 'https'], true) && filter_var($value, FILTER_VALIDATE_URL) !== false) {
            return;
        }

        if (preg_match('/^[a-z][a-z0-9+.-]*$/i', $scheme) === 1) {
            return;
        }

        $fail("The {$attribute} field must be a valid URL.");
    }

    protected function paginationMeta(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ];
    }
}
