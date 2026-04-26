<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProductContribution;
use App\Models\Scan;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionPrice;
use App\Models\User;
use App\Services\StripeSubscriptionService;
use App\Services\SubscriptionManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Throwable;

class UserController extends Controller
{
    public function __construct(
        protected SubscriptionManager $subscriptionManager,
        protected StripeSubscriptionService $stripeSubscriptionService
    ) {
    }

    // List all users
    public function index(Request $request)
    {
        $query = User::query();

        // Search
        if ($request->search) {
            $query->where('name', 'like', "%{$request->search}%")
                ->orWhere('email', 'like', "%{$request->search}%");
        }

        // Filter by role
        if ($request->role) {
            $query->where('role', $request->role);
        }

        $users = $query->latest()->paginate(10);

        return view('admin.users.index', compact('users'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'role' => 'required|in:user,admin',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => bcrypt($validated['password']),
            'role' => $validated['role'],
            'email_verified_at' => now(),
        ]);

        if ($request->boolean('send_welcome_email')) {
            // You can implement email sending here
            // Mail::to($user)->send(new WelcomeEmail($user, $request->password));
        }

        return redirect()->route('admin.users.show', $user)
            ->with('success', 'User created successfully.');
    }

    public function show(User $user)
    {
        $totalContributions = ProductContribution::where('user_id', $user->id)->count();

        $pendingContributions = ProductContribution::where('user_id', $user->id)
            ->where('status', 'pending')->count();

        $approvedContributions = ProductContribution::where('user_id', $user->id)
            ->where('status', 'approved')->count();

        $rejectedContributions = ProductContribution::where('user_id', $user->id)
            ->where('status', 'rejected')->count();

        $totalScans = Scan::where('user_id', $user->id)->count();

        $todayScans = Scan::where('user_id', $user->id)
            ->whereDate('created_at', today())
            ->count();

        $thisMonthScans = $user->scans()
            ->whereMonth('created_at', now()->month)
            ->count();

        $totalContributions = $user->contributions()->count();

        $pendingContributions = $user->contributions()
            ->where('status', 'pending')
            ->count();

        $approvedContributions = $user->contributions()
            ->where('status', 'approved')
            ->count();

        $recentContributions = $user->contributions()
            ->latest()
            ->limit(5)
            ->get();

        $currentSubscription = $this->subscriptionManager
            ->currentSubscription($user)
            ->loadMissing(['plan', 'price']);

        $subscriptionHistory = $user->subscriptions()
            ->with(['plan', 'price'])
            ->latest('created_at')
            ->limit(5)
            ->get();

        $assignablePlans = SubscriptionPlan::query()
            ->where('is_active', true)
            ->with(['prices' => fn ($query) => $query
                ->where('is_active', true)
                ->orderByDesc('is_default')
                ->orderBy('amount')])
            ->orderBy('display_order')
            ->get();

        return view('admin.users.show', compact(
            'user',
            'totalScans',
            'todayScans',
            'thisMonthScans',
            'totalContributions',
            'pendingContributions',
            'approvedContributions',
            'recentContributions',
            'currentSubscription',
            'subscriptionHistory',
            'assignablePlans'
        ));
    }

    public function edit(User $user)
    {
        return view('admin.users.edit', compact('user'));
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'role' => 'required|in:user,admin',
        ]);

        $user->update($validated);

        return redirect()
            ->route('admin.users.show', $user)
            ->with('success', 'User updated successfully');
    }

    // Ban / Unban user
    public function toggleBan(User $user)
    {
        $user->is_banned = !$user->is_banned;
        $user->save();

        return back()->with('success', 'User status updated');
    }

    // Verify user manually
    public function verify(User $user)
    {
        $user->email_verified_at = now();
        $user->save();

        return back()->with('success', 'User verified');
    }

    // Reset password (admin action)
    public function resetPassword(User $user)
    {
        $newPassword = 'password123';

        $user->password = Hash::make($newPassword);
        $user->save();

        return back()->with('success', 'Password reset to: ' . $newPassword);
    }

    public function toggleRole(User $user)
    {
        if (auth()->id() === $user->id) {
            return back()->with('error', 'You cannot change your own role.');
        }

        $user->role = $user->role === 'admin' ? 'user' : 'admin';
        $user->save();

        return back()->with('success', "User role updated to {$user->role}.");
    }

    public function destroy(User $user)
    {
        // optional safety: prevent deleting yourself
        if (auth()->id() === $user->id) {
            return back()->with('error', 'You cannot delete yourself.');
        }

        if ($user->role === 'admin') {
            return back()->with('error', 'You cannot delete an admin.');
        }

        $user->delete();

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'User deleted successfully');
    }

    public function changeSubscription(Request $request, User $user)
    {
        $validated = $request->validate([
            'price_id' => ['required', 'exists:subscription_prices,id'],
            'transition_type' => ['required', Rule::in(['manual_override', 'billing_now', 'billing_next_cycle'])],
            'reason' => ['nullable', 'string', 'max:500'],
            'cancel_current_stripe' => ['nullable', 'boolean'],
        ]);

        $price = SubscriptionPrice::with('plan')->findOrFail($validated['price_id']);
        $plan = $price->plan;
        $currentSubscription = $this->subscriptionManager
            ->currentSubscription($user)
            ->loadMissing(['plan', 'price']);

        try {
            return match ($validated['transition_type']) {
                'manual_override' => $this->applyManualOverride(
                    $user,
                    $currentSubscription,
                    $plan,
                    $price,
                    $validated['reason'] ?? null,
                    (bool) ($validated['cancel_current_stripe'] ?? false)
                ),
                'billing_now' => $this->applyImmediateBillingChange(
                    $user,
                    $currentSubscription,
                    $plan,
                    $price,
                    $validated['reason'] ?? null
                ),
                'billing_next_cycle' => $this->applyEndOfCycleBillingChange(
                    $user,
                    $currentSubscription,
                    $plan,
                    $price,
                    $validated['reason'] ?? null
                ),
            };
        } catch (Throwable $exception) {
            return back()->with('error', $exception->getMessage());
        }
    }

    protected function applyManualOverride(
        User $user,
        Subscription $currentSubscription,
        SubscriptionPlan $plan,
        SubscriptionPrice $price,
        ?string $reason,
        bool $cancelCurrentStripe
    ) {
        if ($currentSubscription->provider === 'stripe' && $currentSubscription->isCurrent()) {
            if (!$cancelCurrentStripe) {
                return back()->with('error', 'Manual overrides for active Stripe subscriptions require explicit immediate cancellation of the current Stripe billing.');
            }

            $this->stripeSubscriptionService->cancel($currentSubscription, true);

            $currentSubscription->update([
                'status' => Subscription::STATUS_CANCELED,
                'canceled_at' => now(),
                'ends_at' => now(),
            ]);
        }

        $this->subscriptionManager->assignPlan($user, $plan, $price, [
            'provider' => 'system',
            'status' => Subscription::STATUS_ACTIVE,
            'metadata' => [
                'assigned_reason' => 'manual_override',
                'reason' => $reason,
                'admin_id' => auth()->id(),
            ],
        ]);

        return back()->with('success', 'Manual plan override applied successfully.');
    }

    protected function applyImmediateBillingChange(
        User $user,
        Subscription $currentSubscription,
        SubscriptionPlan $plan,
        SubscriptionPrice $price,
        ?string $reason
    ) {
        if ($price->isFree()) {
            if ($currentSubscription->provider === 'stripe' && $currentSubscription->isCurrent()) {
                $this->stripeSubscriptionService->cancel($currentSubscription, true);
                $currentSubscription->update([
                    'status' => Subscription::STATUS_CANCELED,
                    'canceled_at' => now(),
                    'ends_at' => now(),
                ]);
            }

            $this->subscriptionManager->assignPlan($user, $plan, $price, [
                'provider' => 'system',
                'status' => Subscription::STATUS_ACTIVE,
                'metadata' => [
                    'assigned_reason' => 'admin_billing_change',
                    'reason' => $reason,
                    'admin_id' => auth()->id(),
                ],
            ]);

            return back()->with('success', 'User moved to the selected free plan immediately.');
        }

        if ($currentSubscription->provider !== 'stripe' || !$currentSubscription->stripe_subscription_id) {
            return back()->with('error', 'Immediate paid billing changes are only supported for Stripe-managed subscriptions. Use a manual override instead.');
        }

        $stripeSubscription = $this->stripeSubscriptionService->updateSubscriptionPrice(
            $currentSubscription,
            $price,
            [
                'user_id' => $user->id,
                'plan_id' => (string) $plan->id,
                'price_id' => (string) $price->id,
                'admin_transition' => 'billing_now',
                'pending_change_reason' => (string) $reason,
                'pending_admin_id' => (string) auth()->id(),
                'pending_plan_id' => '',
                'pending_price_id' => '',
                'pending_change_type' => '',
            ]
        );

        $this->subscriptionManager->syncStripeSubscription($user, $plan, $price, $stripeSubscription, [
            'metadata' => [
                'assigned_reason' => 'admin_billing_change',
                'reason' => $reason,
                'admin_id' => auth()->id(),
            ],
        ]);

        return back()->with('success', 'Stripe subscription updated immediately.');
    }

    protected function applyEndOfCycleBillingChange(
        User $user,
        Subscription $currentSubscription,
        SubscriptionPlan $plan,
        SubscriptionPrice $price,
        ?string $reason
    ) {
        if ($currentSubscription->provider !== 'stripe' || !$currentSubscription->stripe_subscription_id) {
            return back()->with('error', 'End-of-cycle billing changes are only supported for Stripe-managed subscriptions.');
        }

        $stripeSubscription = $this->stripeSubscriptionService->schedulePlanChangeAtPeriodEnd(
            $currentSubscription,
            $plan,
            $price,
            [
                'user_id' => $user->id,
                'plan_id' => (string) $currentSubscription->plan_id,
                'price_id' => (string) ($currentSubscription->price_id ?? ''),
                'pending_change_reason' => (string) $reason,
                'pending_admin_id' => (string) auth()->id(),
            ]
        );

        $currentSubscription->update([
            'status' => Subscription::STATUS_CANCELING,
            'canceled_at' => now(),
            'ends_at' => isset($stripeSubscription->current_period_end)
                ? now()->createFromTimestamp($stripeSubscription->current_period_end)
                : $currentSubscription->current_period_ends_at,
            'metadata' => array_merge($currentSubscription->metadata ?? [], [
                'pending_change' => [
                    'plan_id' => $plan->id,
                    'plan_name' => $plan->name,
                    'price_id' => $price->id,
                    'price_name' => $price->name,
                    'type' => $price->isFree() ? 'system_assign' : 'stripe_recreate',
                    'reason' => $reason,
                    'admin_id' => auth()->id(),
                    'effective_at' => isset($stripeSubscription->current_period_end)
                        ? now()->createFromTimestamp($stripeSubscription->current_period_end)->toIso8601String()
                        : null,
                ],
            ]),
        ]);

        return back()->with('success', 'Plan change scheduled for the end of the current billing cycle.');
    }
}
