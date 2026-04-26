<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Services\StripeSubscriptionService;
use App\Services\SubscriptionManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class SubscriptionController extends Controller
{
    public function __construct(
        protected StripeSubscriptionService $stripeSubscriptionService,
        protected SubscriptionManager $subscriptionManager
    ) {
    }

    public function index(Request $request): View
    {
        $query = Subscription::query()
            ->with(['user', 'plan', 'price'])
            ->latest('created_at');

        $status = $request->input('status');

        if ($status === 'current' || $status === null || $status === '') {
            $query->current();
        } elseif ($status !== 'all') {
            $query->where('status', $status);
        }

        if ($plan = $request->input('plan')) {
            $query->whereHas('plan', fn ($planQuery) => $planQuery->where('slug', $plan));
        }

        if ($search = trim((string) $request->input('search'))) {
            $query->whereHas('user', function ($userQuery) use ($search) {
                $userQuery
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        return view('admin.subscriptions.index', [
            'subscriptions' => $query->paginate(15)->withQueryString(),
            'plans' => SubscriptionPlan::query()->orderBy('display_order')->get(),
            'metrics' => [
                'current' => Subscription::query()->current()->count(),
                'active' => Subscription::query()->where('status', Subscription::STATUS_ACTIVE)->count(),
                'canceling' => Subscription::query()->where('status', Subscription::STATUS_CANCELING)->count(),
                'ended' => Subscription::query()
                    ->whereIn('status', [
                        Subscription::STATUS_CANCELED,
                        Subscription::STATUS_REPLACED,
                        Subscription::STATUS_REFUNDED,
                    ])
                    ->count(),
            ],
        ]);
    }

    public function show(Subscription $subscription): View
    {
        $subscription->loadMissing(['user', 'plan', 'price']);

        return view('admin.subscriptions.show', [
            'subscription' => $subscription,
        ]);
    }

    public function cancel(Request $request, Subscription $subscription): RedirectResponse
    {
        $subscription->loadMissing(['user', 'plan', 'price']);

        if (!$subscription->isPaid() || !$subscription->stripe_subscription_id) {
            return back()->with('error', 'Only Stripe-managed paid subscriptions can be canceled.');
        }

        $immediately = $request->boolean('immediately');

        try {
            $stripeSubscription = $this->stripeSubscriptionService->cancel($subscription, $immediately);

            $subscription->update([
                'status' => $immediately ? Subscription::STATUS_CANCELED : Subscription::STATUS_CANCELING,
                'canceled_at' => now(),
                'ends_at' => $immediately
                    ? now()
                    : (isset($stripeSubscription->current_period_end)
                        ? now()->createFromTimestamp($stripeSubscription->current_period_end)
                        : $subscription->current_period_ends_at),
            ]);

            if ($immediately) {
                $this->subscriptionManager->ensureDefaultSubscription($subscription->user);
            }

            return back()->with('success', $immediately
                ? 'Subscription canceled immediately.'
                : 'Subscription marked to cancel at the end of the current billing period.');
        } catch (Throwable $exception) {
            return back()->with('error', $exception->getMessage());
        }
    }

    public function refund(Request $request, Subscription $subscription): RedirectResponse
    {
        $subscription->loadMissing(['user', 'plan', 'price']);

        $validated = $request->validate([
            'amount' => ['nullable', 'integer', 'min:1'],
            'reason' => ['nullable', 'string', 'in:duplicate,fraudulent,requested_by_customer'],
        ]);

        try {
            $refund = $this->stripeSubscriptionService->refund(
                $subscription,
                $validated['amount'] ?? null,
                $validated['reason'] ?? null
            );

            $isFullRefund = ($validated['amount'] ?? $subscription->amount) >= $subscription->amount;

            $subscription->update([
                'refunded_at' => now(),
                'status' => $isFullRefund && !$subscription->isCurrent()
                    ? Subscription::STATUS_REFUNDED
                    : $subscription->status,
                'metadata' => array_merge($subscription->metadata ?? [], [
                    'last_refund' => [
                        'id' => $refund->id,
                        'amount' => $refund->amount,
                        'status' => $refund->status,
                        'reason' => $validated['reason'] ?? null,
                    ],
                ]),
            ]);

            return back()->with('success', 'Refund request created successfully.');
        } catch (Throwable $exception) {
            return back()->with('error', $exception->getMessage());
        }
    }
}
