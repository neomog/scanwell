<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionPrice;
use App\Services\StripeSubscriptionService;
use App\Services\SubscriptionManager;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class BillingController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected SubscriptionManager $subscriptionManager,
        protected StripeSubscriptionService $stripeSubscriptionService
    ) {
    }

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

        return $this->success([
            'subscription' => $this->transformSubscription($subscription),
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

        if (!$subscription->isPaid() || !$subscription->stripe_subscription_id) {
            return $this->error('Only active paid Stripe subscriptions can be canceled from mobile.', null, 422);
        }

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
            ])->values(),
        ];
    }

    protected function transformSubscription(Subscription $subscription): array
    {
        return [
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
            'trial_ends_at' => $subscription->trial_ends_at,
            'ends_at' => $subscription->ends_at,
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

        if (!is_string($value)) {
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
}
