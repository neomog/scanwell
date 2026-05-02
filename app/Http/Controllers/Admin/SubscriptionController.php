<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Services\BillingSyncService;
use App\Services\StripeSubscriptionService;
use App\Services\SubscriptionEventService;
use App\Services\SubscriptionManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class SubscriptionController extends Controller
{
    public function __construct(
        protected StripeSubscriptionService $stripeSubscriptionService,
        protected SubscriptionManager $subscriptionManager,
        protected SubscriptionEventService $subscriptionEventService,
        protected BillingSyncService $billingSyncService
    ) {}

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
            'subscriptionEvents' => $subscription->events()
                ->with(['actor', 'fromPlan', 'toPlan', 'fromPrice', 'toPrice'])
                ->latest()
                ->limit(10)
                ->get(),
            'invoiceHistory' => $subscription->invoices()
                ->latest('issued_at')
                ->limit(10)
                ->get(),
            'refundHistory' => $subscription->refunds()
                ->latest('refunded_at')
                ->limit(10)
                ->get(),
        ]);
    }

    public function cancel(Request $request, Subscription $subscription): RedirectResponse
    {
        $subscription->loadMissing(['user', 'plan', 'price']);

        if (! $subscription->isPaid() || ! $subscription->stripe_subscription_id) {
            return back()->with('error', 'Only Stripe-managed paid subscriptions can be canceled.');
        }

        $immediately = $request->boolean('immediately');
        $previousStatus = $subscription->status;

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

            $this->subscriptionEventService->record($subscription->user, $subscription, $immediately
                ? 'admin_canceled_immediately'
                : 'admin_canceled_at_period_end', [
                    'source' => 'admin',
                    'actor_id' => auth()->id(),
                    'from_plan_id' => $subscription->plan_id,
                    'to_plan_id' => $subscription->plan_id,
                    'from_price_id' => $subscription->price_id,
                    'to_price_id' => $subscription->price_id,
                    'status_before' => $previousStatus,
                    'status_after' => $subscription->status,
                    'effective_at' => $subscription->display_expiry_at,
                    'metadata' => [
                        'stripe_subscription_id' => $subscription->stripe_subscription_id,
                    ],
                ]);

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
                'status' => $isFullRefund && ! $subscription->isCurrent()
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

            $this->billingSyncService->recordRefundFromStripe(
                $subscription,
                $refund,
                $validated['reason'] ?? null,
                'admin',
                (string) auth()->id()
            );

            $this->subscriptionEventService->record($subscription->user, $subscription, 'admin_refund_created', [
                'source' => 'admin',
                'actor_id' => auth()->id(),
                'from_plan_id' => $subscription->plan_id,
                'to_plan_id' => $subscription->plan_id,
                'from_price_id' => $subscription->price_id,
                'to_price_id' => $subscription->price_id,
                'status_before' => $subscription->status,
                'status_after' => $subscription->status,
                'reason' => $validated['reason'] ?? null,
                'effective_at' => now(),
                'metadata' => [
                    'refund_id' => $refund->id,
                    'amount' => $refund->amount,
                    'refund_status' => $refund->status,
                ],
            ]);

            return back()->with('success', 'Refund request created successfully.');
        } catch (Throwable $exception) {
            return back()->with('error', $exception->getMessage());
        }
    }
}
