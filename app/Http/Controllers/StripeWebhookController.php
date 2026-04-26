<?php

namespace App\Http\Controllers;

use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionPrice;
use App\Models\User;
use App\Services\StripeSubscriptionService;
use App\Services\SubscriptionManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Throwable;

class StripeWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        StripeSubscriptionService $stripeSubscriptionService,
        SubscriptionManager $subscriptionManager
    ): JsonResponse {
        $signature = (string) $request->header('Stripe-Signature');

        if ($signature === '') {
            return response()->json(['message' => 'Missing Stripe signature.'], 400);
        }

        try {
            $event = $stripeSubscriptionService->constructWebhook($request->getContent(), $signature);
        } catch (Throwable $exception) {
            return response()->json(['message' => $exception->getMessage()], 400);
        }

        match ($event->type) {
            'checkout.session.completed' => $this->handleCheckoutCompleted(
                $event->data->object,
                $stripeSubscriptionService,
                $subscriptionManager
            ),
            'customer.subscription.created',
            'customer.subscription.updated' => $this->handleSubscriptionUpdated(
                $event->data->object,
                $subscriptionManager
            ),
            'customer.subscription.deleted' => $this->handleSubscriptionDeleted(
                $event->data->object,
                $stripeSubscriptionService,
                $subscriptionManager
            ),
            'invoice.payment_succeeded' => $this->handleInvoicePaid($event->data->object),
            default => null,
        };

        return response()->json(['received' => true]);
    }

    protected function handleCheckoutCompleted(
        object $session,
        StripeSubscriptionService $stripeSubscriptionService,
        SubscriptionManager $subscriptionManager
    ): void {
        if (($session->mode ?? null) !== 'subscription' || empty($session->subscription)) {
            return;
        }

        $user = User::find(data_get($session, 'metadata.user_id'));
        $plan = SubscriptionPlan::find(data_get($session, 'metadata.plan_id'));
        $price = SubscriptionPrice::find(data_get($session, 'metadata.price_id'));

        if (!$user || !$plan || !$price) {
            return;
        }

        $stripeSubscription = $stripeSubscriptionService->retrieveSubscription((string) $session->subscription);

        $subscriptionManager->syncStripeSubscription(
            $user,
            $plan,
            $price,
            $stripeSubscription,
            [
                'stripe_checkout_session_id' => $session->id ?? null,
            ]
        );
    }

    protected function handleSubscriptionUpdated(object $stripeSubscription, SubscriptionManager $subscriptionManager): void
    {
        $localSubscription = Subscription::query()
            ->where('stripe_subscription_id', $stripeSubscription->id ?? null)
            ->first();

        $user = User::find(data_get($stripeSubscription, 'metadata.user_id') ?: $localSubscription?->user_id);
        $plan = SubscriptionPlan::find(data_get($stripeSubscription, 'metadata.plan_id') ?: $localSubscription?->plan_id);
        $price = SubscriptionPrice::find(data_get($stripeSubscription, 'metadata.price_id') ?: $localSubscription?->price_id);

        if (!$user || !$plan || !$price) {
            return;
        }

        $subscriptionManager->syncStripeSubscription($user, $plan, $price, $stripeSubscription);
    }

    protected function handleSubscriptionDeleted(
        object $stripeSubscription,
        StripeSubscriptionService $stripeSubscriptionService,
        SubscriptionManager $subscriptionManager
    ): void {
        $localSubscription = Subscription::query()
            ->where('stripe_subscription_id', $stripeSubscription->id ?? null)
            ->first();

        $user = User::find(data_get($stripeSubscription, 'metadata.user_id') ?: $localSubscription?->user_id);
        $plan = SubscriptionPlan::find(data_get($stripeSubscription, 'metadata.plan_id') ?: $localSubscription?->plan_id);
        $price = SubscriptionPrice::find(data_get($stripeSubscription, 'metadata.price_id') ?: $localSubscription?->price_id);

        if (!$user || !$plan || !$price) {
            return;
        }

        $pendingPlan = SubscriptionPlan::find(data_get($stripeSubscription, 'metadata.pending_plan_id'));
        $pendingPriceId = data_get($stripeSubscription, 'metadata.pending_price_id');
        $pendingPrice = $pendingPriceId ? SubscriptionPrice::find($pendingPriceId) : null;
        $pendingType = data_get($stripeSubscription, 'metadata.pending_change_type');
        $pendingReason = data_get($stripeSubscription, 'metadata.pending_change_reason');
        $pendingAdminId = data_get($stripeSubscription, 'metadata.pending_admin_id');

        $shouldSuppressDefaultAssignment = $pendingPlan && in_array($pendingType, ['stripe_recreate', 'system_assign'], true);

        $subscriptionManager->syncStripeSubscription(
            $user,
            $plan,
            $price,
            $stripeSubscription,
            [],
            !$shouldSuppressDefaultAssignment
        );

        if (!$shouldSuppressDefaultAssignment || !$pendingPlan) {
            return;
        }

        if ($pendingType === 'stripe_recreate' && $pendingPrice && !$pendingPrice->isFree()) {
            try {
                $newStripeSubscription = $stripeSubscriptionService->createSubscriptionForCustomer(
                    (string) $stripeSubscription->customer,
                    $pendingPrice,
                    [
                        'user_id' => $user->id,
                        'plan_id' => (string) $pendingPlan->id,
                        'price_id' => (string) $pendingPrice->id,
                        'admin_transition' => 'scheduled_plan_change',
                        'pending_change_reason' => (string) $pendingReason,
                        'pending_admin_id' => (string) $pendingAdminId,
                    ],
                    $stripeSubscription->default_payment_method ?? null
                );

                $subscriptionManager->syncStripeSubscription($user, $pendingPlan, $pendingPrice, $newStripeSubscription, [
                    'metadata' => [
                        'assigned_reason' => 'scheduled_plan_change',
                        'reason' => $pendingReason,
                        'admin_id' => $pendingAdminId,
                    ],
                ]);

                return;
            } catch (Throwable) {
                $subscriptionManager->ensureDefaultSubscription($user);

                return;
            }
        }

        $subscriptionManager->assignPlan($user, $pendingPlan, $pendingPrice, [
            'provider' => 'system',
            'status' => Subscription::STATUS_ACTIVE,
            'metadata' => [
                'assigned_reason' => 'scheduled_plan_change',
                'reason' => $pendingReason,
                'admin_id' => $pendingAdminId,
            ],
        ]);
    }

    protected function handleInvoicePaid(object $invoice): void
    {
        $subscription = Subscription::query()
            ->where('stripe_subscription_id', $invoice->subscription ?? null)
            ->first();

        if (!$subscription) {
            return;
        }

        $subscription->update([
            'stripe_invoice_id' => $invoice->id ?? $subscription->stripe_invoice_id,
            'stripe_payment_intent_id' => $invoice->payment_intent ?? $subscription->stripe_payment_intent_id,
            'metadata' => array_merge($subscription->metadata ?? [], [
                'last_invoice' => [
                    'id' => $invoice->id ?? null,
                    'status' => $invoice->status ?? null,
                    'amount_paid' => $invoice->amount_paid ?? null,
                    'paid_at' => isset($invoice->status_transitions->paid_at) && $invoice->status_transitions->paid_at
                        ? Carbon::createFromTimestamp($invoice->status_transitions->paid_at)->toIso8601String()
                        : null,
                ],
            ]),
        ]);
    }
}
