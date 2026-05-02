<?php

namespace App\Services;

use App\Exceptions\PlanFeatureException;
use App\Models\Scan;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionPrice;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class SubscriptionManager
{
    public function __construct(
        protected SubscriptionEventService $subscriptionEventService
    ) {}

    public function defaultPlan(): SubscriptionPlan
    {
        $configuredSlug = config('subscriptions.default_slug', 'free');

        return SubscriptionPlan::query()
            ->where('is_active', true)
            ->where(function ($query) use ($configuredSlug) {
                $query->where('is_default', true)->orWhere('slug', $configuredSlug);
            })
            ->orderByDesc('is_default')
            ->orderBy('display_order')
            ->first()
            ?? SubscriptionPlan::query()
                ->where('is_active', true)
                ->orderByDesc('is_default')
                ->orderBy('display_order')
                ->firstOrFail();
    }

    public function defaultPrice(?SubscriptionPlan $plan = null): ?SubscriptionPrice
    {
        $plan ??= $this->defaultPlan();

        return $plan->prices()
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('amount')
            ->first();
    }

    public function currentSubscription(User $user): Subscription
    {
        $subscription = $user->subscriptions()
            ->with(['plan', 'price'])
            ->current()
            ->latest('created_at')
            ->first();

        if ($subscription) {
            return $subscription;
        }

        return $this->ensureDefaultSubscription($user)->loadMissing(['plan', 'price']);
    }

    public function currentPlan(User $user): SubscriptionPlan
    {
        return $this->currentSubscription($user)->plan ?? $this->defaultPlan();
    }

    public function ensureDefaultSubscription(User $user): Subscription
    {
        $existing = $user->subscriptions()
            ->with(['plan', 'price'])
            ->current()
            ->latest('created_at')
            ->first();

        if ($existing) {
            return $existing;
        }

        $plan = $this->defaultPlan();
        $price = $this->defaultPrice($plan);

        return $this->assignPlan($user, $plan, $price, [
            'provider' => 'system',
            'status' => Subscription::STATUS_ACTIVE,
            'metadata' => [
                'assigned_reason' => 'default_plan',
            ],
        ]);
    }

    public function assignPlan(
        User $user,
        SubscriptionPlan $plan,
        ?SubscriptionPrice $price = null,
        array $attributes = [],
        bool $replaceCurrent = true
    ): Subscription {
        return DB::transaction(function () use ($user, $plan, $price, $attributes, $replaceCurrent) {
            $previousSubscription = $replaceCurrent
                ? $user->subscriptions()->with(['plan', 'price'])->current()->latest('created_at')->first()
                : null;

            $audit = (array) ($attributes['audit'] ?? []);
            unset($attributes['audit']);

            if ($replaceCurrent) {
                $this->retireCurrentSubscriptions($user);
            }

            $subscription = Subscription::create(array_merge([
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'price_id' => $price?->id,
                'provider' => 'system',
                'status' => Subscription::STATUS_ACTIVE,
                'quantity' => 1,
                'currency' => $price?->currency ?? config('subscriptions.currency', 'usd'),
                'amount' => $price?->amount ?? 0,
                'starts_at' => now(),
                'current_period_starts_at' => now(),
                'current_period_ends_at' => null,
            ], $attributes));

            $eventType = $audit['event_type'] ?? $this->inferAssignmentEventType($attributes);

            if ($eventType) {
                $this->subscriptionEventService->record($user, $subscription, $eventType, [
                    'source' => $audit['source'] ?? 'system',
                    'actor_id' => $audit['actor_id'] ?? null,
                    'from_plan_id' => $previousSubscription?->plan_id,
                    'to_plan_id' => $subscription->plan_id,
                    'from_price_id' => $previousSubscription?->price_id,
                    'to_price_id' => $subscription->price_id,
                    'status_before' => $previousSubscription?->status,
                    'status_after' => $subscription->status,
                    'reason' => $audit['reason'] ?? data_get($attributes, 'metadata.reason'),
                    'effective_at' => $audit['effective_at'] ?? $subscription->starts_at,
                    'metadata' => array_merge([
                        'assigned_reason' => data_get($attributes, 'metadata.assigned_reason'),
                    ], (array) ($audit['metadata'] ?? [])),
                ]);
            }

            return $subscription;
        });
    }

    public function retireCurrentSubscriptions(
        User $user,
        ?Subscription $except = null,
        string $status = Subscription::STATUS_REPLACED,
        ?CarbonInterface $endedAt = null
    ): void {
        $endedAt ??= now();

        $query = $user->subscriptions()->current();

        if ($except) {
            $query->whereKeyNot($except->getKey());
        }

        $query->update([
            'status' => $status,
            'ends_at' => $endedAt,
            'canceled_at' => $endedAt,
            'updated_at' => now(),
        ]);
    }

    public function feature(User $user, string $key, mixed $default = null): mixed
    {
        $plan = $this->currentPlan($user);

        return $plan->feature($key, $default);
    }

    public function can(User $user, string $feature): bool
    {
        return (bool) $this->feature($user, $feature, false);
    }

    public function ensureFeature(User $user, string $feature, ?string $message = null): void
    {
        if (! $this->can($user, $feature)) {
            throw new PlanFeatureException(
                $feature,
                $message ?? 'This feature is not available on your current plan.'
            );
        }
    }

    public function monthlyScanLimit(User $user): ?int
    {
        $limit = $this->feature($user, 'scans.monthly_limit');

        return $limit === null || $limit === '' ? null : (int) $limit;
    }

    public function monthlyScanUsage(User $user): int
    {
        return Scan::query()
            ->where('user_id', $user->id)
            ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->count();
    }

    public function remainingMonthlyScans(User $user): ?int
    {
        $limit = $this->monthlyScanLimit($user);

        if ($limit === null) {
            return null;
        }

        return max($limit - $this->monthlyScanUsage($user), 0);
    }

    public function enforceMonthlyScanLimit(User $user): void
    {
        $limit = $this->monthlyScanLimit($user);

        if ($limit === null) {
            return;
        }

        if ($this->monthlyScanUsage($user) >= $limit) {
            throw new PlanFeatureException(
                'scans.monthly_limit',
                'You have reached the monthly scan limit for your current plan.'
            );
        }
    }

    public function syncStripeSubscription(
        User $user,
        SubscriptionPlan $plan,
        SubscriptionPrice $price,
        object $stripeSubscription,
        array $extra = [],
        bool $assignDefaultOnEnd = true
    ): Subscription {
        return DB::transaction(function () use ($user, $plan, $price, $stripeSubscription, $extra, $assignDefaultOnEnd) {
            $subscription = Subscription::query()
                ->where('stripe_subscription_id', $stripeSubscription->id)
                ->first();

            $wasExisting = (bool) $subscription;
            $previous = $subscription ? [
                'plan_id' => $subscription->plan_id,
                'price_id' => $subscription->price_id,
                'status' => $subscription->status,
                'current_period_ends_at' => $subscription->current_period_ends_at?->getTimestamp(),
            ] : null;

            if (! $subscription) {
                $this->retireCurrentSubscriptions($user);
                $subscription = new Subscription([
                    'user_id' => $user->id,
                ]);
            }

            $mappedStatus = $this->mapStripeStatus($stripeSubscription->status);

            if (
                $mappedStatus === Subscription::STATUS_ACTIVE
                && (bool) ($stripeSubscription->cancel_at_period_end ?? false)
            ) {
                $mappedStatus = Subscription::STATUS_CANCELING;
            }

            $baseAttributes = [
                'plan_id' => $plan->id,
                'price_id' => $price->id,
                'provider' => 'stripe',
                'status' => $mappedStatus,
                'quantity' => (int) ($stripeSubscription->items->data[0]->quantity ?? 1),
                'currency' => strtolower((string) ($stripeSubscription->currency ?? $price->currency)),
                'amount' => (int) ($stripeSubscription->items->data[0]->price->unit_amount ?? $price->amount),
                'stripe_customer_id' => (string) $stripeSubscription->customer,
                'stripe_subscription_id' => (string) $stripeSubscription->id,
                'starts_at' => isset($stripeSubscription->start_date) ? now()->createFromTimestamp($stripeSubscription->start_date) : now(),
                'current_period_starts_at' => isset($stripeSubscription->current_period_start) ? now()->createFromTimestamp($stripeSubscription->current_period_start) : now(),
                'current_period_ends_at' => isset($stripeSubscription->current_period_end) ? now()->createFromTimestamp($stripeSubscription->current_period_end) : null,
                'trial_ends_at' => isset($stripeSubscription->trial_end) && $stripeSubscription->trial_end ? now()->createFromTimestamp($stripeSubscription->trial_end) : null,
                'canceled_at' => isset($stripeSubscription->canceled_at) && $stripeSubscription->canceled_at ? now()->createFromTimestamp($stripeSubscription->canceled_at) : null,
                'ends_at' => isset($stripeSubscription->ended_at) && $stripeSubscription->ended_at ? now()->createFromTimestamp($stripeSubscription->ended_at) : null,
                'metadata' => array_merge($subscription->metadata ?? [], [
                    'stripe_status' => $stripeSubscription->status,
                ]),
            ];

            if (array_key_exists('metadata', $extra)) {
                $baseAttributes['metadata'] = array_merge(
                    $baseAttributes['metadata'] ?? [],
                    (array) $extra['metadata']
                );

                unset($extra['metadata']);
            }

            $subscription->fill(array_merge($baseAttributes, $extra));
            $subscription->save();

            if ($user->stripe_customer_id !== $subscription->stripe_customer_id) {
                $user->forceFill([
                    'stripe_customer_id' => $subscription->stripe_customer_id,
                ])->save();
            }

            if (
                $assignDefaultOnEnd
                && in_array($subscription->status, [Subscription::STATUS_CANCELED, Subscription::STATUS_REFUNDED], true)
            ) {
                $hasCurrentPaidSubscription = $user->subscriptions()
                    ->whereKeyNot($subscription->getKey())
                    ->current()
                    ->exists();

                if (! $hasCurrentPaidSubscription) {
                    $this->ensureDefaultSubscription($user);
                }
            }

            $eventType = null;

            if (! $wasExisting) {
                $eventType = 'subscription_started';
            } elseif (($previous['plan_id'] ?? null) !== $subscription->plan_id || ($previous['price_id'] ?? null) !== $subscription->price_id) {
                $eventType = 'subscription_plan_changed';
            } elseif (($previous['status'] ?? null) !== $subscription->status) {
                $eventType = 'subscription_status_changed';
            } elseif (($previous['current_period_ends_at'] ?? null) !== $subscription->current_period_ends_at?->getTimestamp()) {
                $eventType = 'subscription_renewed';
            }

            if ($eventType) {
                $this->subscriptionEventService->record($user, $subscription, $eventType, [
                    'source' => 'webhook',
                    'from_plan_id' => $previous['plan_id'] ?? $subscription->plan_id,
                    'to_plan_id' => $subscription->plan_id,
                    'from_price_id' => $previous['price_id'] ?? $subscription->price_id,
                    'to_price_id' => $subscription->price_id,
                    'status_before' => $previous['status'] ?? null,
                    'status_after' => $subscription->status,
                    'effective_at' => $subscription->current_period_starts_at ?? $subscription->starts_at,
                    'metadata' => [
                        'stripe_status' => $stripeSubscription->status ?? null,
                        'stripe_subscription_id' => $subscription->stripe_subscription_id,
                    ],
                ]);
            }

            return $subscription->loadMissing(['plan', 'price']);
        });
    }

    public function mapStripeStatus(string $stripeStatus): string
    {
        return match ($stripeStatus) {
            'trialing' => Subscription::STATUS_TRIALING,
            'active' => Subscription::STATUS_ACTIVE,
            'past_due' => Subscription::STATUS_PAST_DUE,
            'canceled' => Subscription::STATUS_CANCELED,
            'unpaid' => Subscription::STATUS_UNPAID,
            'incomplete', 'incomplete_expired' => Subscription::STATUS_INCOMPLETE,
            default => Subscription::STATUS_ACTIVE,
        };
    }

    protected function inferAssignmentEventType(array $attributes): ?string
    {
        return match (data_get($attributes, 'metadata.assigned_reason')) {
            'default_plan' => 'default_plan_assigned',
            'manual_override' => 'manual_override_applied',
            'admin_billing_change' => 'admin_billing_change_applied',
            'scheduled_plan_change' => 'scheduled_plan_change_applied',
            default => data_get($attributes, 'provider') === 'system' ? 'subscription_assigned' : null,
        };
    }
}
