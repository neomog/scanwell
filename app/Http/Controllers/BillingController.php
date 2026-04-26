<?php

namespace App\Http\Controllers;

use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionPrice;
use App\Services\StripeSubscriptionService;
use App\Services\SubscriptionManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class BillingController extends Controller
{
    public function __construct(
        protected SubscriptionManager $subscriptionManager,
        protected StripeSubscriptionService $stripeSubscriptionService
    ) {
    }

    public function index(Request $request): View
    {
        $currentSubscription = $this->subscriptionManager
            ->currentSubscription($request->user())
            ->loadMissing(['plan', 'price']);

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

        return view('billing.index', [
            'currentSubscription' => $currentSubscription,
            'plans' => $plans,
            'featureCatalog' => config('subscriptions.feature_catalog', []),
            'remainingMonthlyScans' => $this->subscriptionManager->remainingMonthlyScans($request->user()),
            'stripeConfigured' => $this->stripeSubscriptionService->isConfigured(),
            'checkoutState' => $request->query('checkout'),
        ]);
    }

    public function checkout(Request $request, SubscriptionPrice $price): RedirectResponse
    {
        try {
            $price->loadMissing('plan');
            $session = $this->stripeSubscriptionService->createCheckoutSession($request->user(), $price);

            return redirect()->away($session->url);
        } catch (Throwable $exception) {
            return back()->with('error', $exception->getMessage());
        }
    }

    public function cancel(Request $request): RedirectResponse
    {
        $subscription = $this->subscriptionManager
            ->currentSubscription($request->user())
            ->loadMissing(['plan', 'price']);

        if (!$subscription->isPaid() || !$subscription->stripe_subscription_id) {
            return back()->with('error', 'Only active paid subscriptions can be canceled.');
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

            return back()->with('success', 'Your subscription will remain active until the end of the current billing period.');
        } catch (Throwable $exception) {
            return back()->with('error', $exception->getMessage());
        }
    }
}
