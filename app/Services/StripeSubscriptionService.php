<?php

namespace App\Services;

use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionPrice;
use App\Models\User;
use RuntimeException;
use Stripe\Checkout\Session;
use Stripe\Event;
use Stripe\Exception\ApiErrorException;
use Stripe\Refund;
use Stripe\StripeClient;
use Stripe\Webhook;

class StripeSubscriptionService
{
    protected ?StripeClient $client = null;

    public function isConfigured(): bool
    {
        return filled(config('subscriptions.stripe.secret'));
    }

    public function client(): StripeClient
    {
        if (!$this->isConfigured()) {
            throw new RuntimeException('Stripe is not configured.');
        }

        return $this->client ??= new StripeClient(config('subscriptions.stripe.secret'));
    }

    public function ensureCustomer(User $user): string
    {
        if ($user->stripe_customer_id) {
            return $user->stripe_customer_id;
        }

        $customer = $this->client()->customers->create([
            'email' => $user->email,
            'name' => $user->name,
            'metadata' => [
                'user_id' => $user->id,
            ],
        ]);

        $user->forceFill([
            'stripe_customer_id' => $customer->id,
        ])->save();

        return $customer->id;
    }

    /**
     * @throws ApiErrorException
     */
    public function createCheckoutSession(
        User $user,
        SubscriptionPrice $price,
        ?string $successUrl = null,
        ?string $cancelUrl = null
    ): Session
    {
        if (!$price->is_active || !$price->plan || !$price->plan->is_active || $price->isFree()) {
            throw new RuntimeException('This price is not eligible for checkout.');
        }

        $customerId = $this->ensureCustomer($user);
        $subscriptionData = [
            'metadata' => [
                'user_id' => $user->id,
                'plan_id' => (string) $price->plan_id,
                'price_id' => (string) $price->id,
            ],
        ];

        if ($price->trial_days > 0) {
            $subscriptionData['trial_period_days'] = $price->trial_days;
        }

        $lineItem = $price->stripe_price_id
            ? [
                'price' => $price->stripe_price_id,
                'quantity' => 1,
            ]
            : [
                'price_data' => [
                    'currency' => strtolower($price->currency),
                    'unit_amount' => $price->amount,
                    'product_data' => [
                        'name' => $price->plan->name.' '.$price->name,
                        'metadata' => [
                            'plan_id' => (string) $price->plan_id,
                            'price_id' => (string) $price->id,
                        ],
                    ],
                    'recurring' => [
                        'interval' => $price->billing_interval,
                        'interval_count' => $price->billing_interval_count,
                    ],
                ],
                'quantity' => 1,
            ];

        return $this->client()->checkout->sessions->create([
            'mode' => 'subscription',
            'customer' => $customerId,
            'client_reference_id' => $user->id,
            'line_items' => [$lineItem],
            'success_url' => $successUrl ?: config('subscriptions.stripe.success_url'),
            'cancel_url' => $cancelUrl ?: config('subscriptions.stripe.cancel_url'),
            'allow_promotion_codes' => true,
            'metadata' => [
                'user_id' => $user->id,
                'plan_id' => (string) $price->plan_id,
                'price_id' => (string) $price->id,
            ],
            'subscription_data' => $subscriptionData,
        ]);
    }

    public function constructWebhook(string $payload, string $signature): Event
    {
        $secret = config('subscriptions.stripe.webhook_secret');

        if (!$secret) {
            throw new RuntimeException('Stripe webhook secret is not configured.');
        }

        return Webhook::constructEvent($payload, $signature, $secret);
    }

    /**
     * @throws ApiErrorException
     */
    public function retrieveSubscription(string $subscriptionId): object
    {
        return $this->client()->subscriptions->retrieve($subscriptionId, []);
    }

    /**
     * @throws ApiErrorException
     */
    public function updateSubscriptionPrice(
        Subscription $subscription,
        SubscriptionPrice $price,
        array $metadata = [],
        string $prorationBehavior = 'always_invoice'
    ): object {
        if (!$subscription->stripe_subscription_id) {
            throw new RuntimeException('This subscription is not managed by Stripe.');
        }

        if ($price->isFree() || !$price->stripe_price_id) {
            throw new RuntimeException('The selected price cannot be synced to Stripe because it has no Stripe price ID.');
        }

        $stripeSubscription = $this->retrieveSubscription($subscription->stripe_subscription_id);
        $item = $stripeSubscription->items->data[0] ?? null;

        if (!$item) {
            throw new RuntimeException('Stripe subscription items could not be resolved.');
        }

        return $this->client()->subscriptions->update($subscription->stripe_subscription_id, [
            'cancel_at_period_end' => false,
            'proration_behavior' => $prorationBehavior,
            'items' => [[
                'id' => $item->id,
                'price' => $price->stripe_price_id,
                'quantity' => (int) ($item->quantity ?? $subscription->quantity ?? 1),
            ]],
            'metadata' => array_merge((array) ($stripeSubscription->metadata ?? []), [
                'plan_id' => (string) $price->plan_id,
                'price_id' => (string) $price->id,
            ], $metadata),
        ]);
    }

    /**
     * @throws ApiErrorException
     */
    public function schedulePlanChangeAtPeriodEnd(
        Subscription $subscription,
        SubscriptionPlan $plan,
        ?SubscriptionPrice $price,
        array $metadata = []
    ): object {
        if (!$subscription->stripe_subscription_id) {
            throw new RuntimeException('This subscription is not managed by Stripe.');
        }

        if ($price && !$price->isFree() && !$price->stripe_price_id) {
            throw new RuntimeException('The selected paid price cannot be scheduled because it has no Stripe price ID.');
        }

        $stripeSubscription = $this->retrieveSubscription($subscription->stripe_subscription_id);

        return $this->client()->subscriptions->update($subscription->stripe_subscription_id, [
            'cancel_at_period_end' => true,
            'metadata' => array_merge((array) ($stripeSubscription->metadata ?? []), [
                'pending_plan_id' => (string) $plan->id,
                'pending_price_id' => $price?->id ? (string) $price->id : '',
                'pending_change_type' => $price && !$price->isFree() ? 'stripe_recreate' : 'system_assign',
            ], $metadata),
        ]);
    }

    /**
     * @throws ApiErrorException
     */
    public function createSubscriptionForCustomer(
        string $customerId,
        SubscriptionPrice $price,
        array $metadata = [],
        ?string $defaultPaymentMethod = null
    ): object {
        if ($price->isFree() || !$price->stripe_price_id) {
            throw new RuntimeException('The selected price cannot be created in Stripe because it has no Stripe price ID.');
        }

        $payload = [
            'customer' => $customerId,
            'items' => [[
                'price' => $price->stripe_price_id,
            ]],
            'metadata' => array_merge([
                'plan_id' => (string) $price->plan_id,
                'price_id' => (string) $price->id,
            ], $metadata),
        ];

        if ($defaultPaymentMethod) {
            $payload['default_payment_method'] = $defaultPaymentMethod;
        }

        return $this->client()->subscriptions->create($payload);
    }

    /**
     * @throws ApiErrorException
     */
    public function cancel(Subscription $subscription, bool $immediately = false): object
    {
        if (!$subscription->stripe_subscription_id) {
            throw new RuntimeException('This subscription is not managed by Stripe.');
        }

        if ($immediately) {
            return $this->client()->subscriptions->cancel($subscription->stripe_subscription_id, []);
        }

        return $this->client()->subscriptions->update($subscription->stripe_subscription_id, [
            'cancel_at_period_end' => true,
        ]);
    }

    /**
     * @throws ApiErrorException
     */
    public function refund(Subscription $subscription, ?int $amount = null, ?string $reason = null): Refund
    {
        if (!$subscription->stripe_payment_intent_id) {
            throw new RuntimeException('This subscription does not have a refundable payment intent.');
        }

        $payload = [
            'payment_intent' => $subscription->stripe_payment_intent_id,
        ];

        if ($amount !== null) {
            $payload['amount'] = $amount;
        }

        if ($reason) {
            $payload['reason'] = $reason;
        }

        return $this->client()->refunds->create($payload);
    }
}
