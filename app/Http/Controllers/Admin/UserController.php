<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProductContribution;
use App\Models\Role;
use App\Models\Scan;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionPrice;
use App\Models\User;
use App\Services\StripeSubscriptionService;
use App\Services\SubscriptionEventService;
use App\Services\SubscriptionManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Throwable;

class UserController extends Controller
{
    public function __construct(
        protected SubscriptionManager $subscriptionManager,
        protected StripeSubscriptionService $stripeSubscriptionService,
        protected SubscriptionEventService $subscriptionEventService
    ) {}

    // List all users
    public function index(Request $request)
    {
        abort_unless($request->user()->can('users.view'), 403);

        $query = User::query()->with('roleDefinition');

        // Search
        if ($request->search) {
            $query->where('name', 'like', "%{$request->search}%")
                ->orWhere('email', 'like', "%{$request->search}%");
        }

        // Filter by role
        if ($request->role) {
            $query->where('role', $request->role);
        }

        if ($request->status === 'active') {
            $query->where('is_banned', false);
        }

        if ($request->status === 'banned') {
            $query->where('is_banned', true);
        }

        $users = $query->latest()->paginate(10);

        return view('admin.users.index', [
            'users' => $users,
            'roles' => Role::query()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->can('users.manage'), 403);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'role' => 'required|exists:roles,slug',
        ]);

        if (! $request->user()->hasRole('super_admin') && $validated['role'] === 'super_admin') {
            abort(403);
        }

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

    public function create()
    {
        abort_unless(request()->user()->can('users.manage'), 403);

        return redirect()->route('admin.users.index');
    }

    public function show(User $user)
    {
        abort_unless(request()->user()->can('users.view'), 403);

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

        $viewer = request()->user();
        $canViewBilling = $viewer->can('billing.view');
        $canManageSubscriptions = $viewer->can('subscriptions.manage');

        $currentSubscription = null;
        $subscriptionHistory = collect();
        $subscriptionEvents = collect();
        $billingInvoices = collect();
        $failedInvoices = collect();
        $assignablePlans = collect();

        if ($canManageSubscriptions || $canViewBilling) {
            $currentSubscription = $this->subscriptionManager
                ->currentSubscription($user)
                ->loadMissing(['plan', 'price']);

            $subscriptionHistory = $user->subscriptions()
                ->with(['plan', 'price'])
                ->latest('created_at')
                ->limit(10)
                ->get();
        }

        if ($canViewBilling) {
            $subscriptionEvents = $user->subscriptionEvents()
                ->with(['actor', 'fromPlan', 'toPlan', 'fromPrice', 'toPrice'])
                ->latest()
                ->limit(10)
                ->get();

            $billingInvoices = $user->billingInvoices()
                ->latest('issued_at')
                ->limit(10)
                ->get();

            $failedInvoices = $user->billingInvoices()
                ->whereNotNull('failed_at')
                ->latest('failed_at')
                ->limit(5)
                ->get();
        }

        if ($canManageSubscriptions) {
            $assignablePlans = SubscriptionPlan::query()
                ->where('is_active', true)
                ->with(['prices' => fn ($query) => $query
                    ->where('is_active', true)
                    ->orderByDesc('is_default')
                    ->orderBy('amount')])
                ->orderBy('display_order')
                ->get();
        }

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
            'subscriptionEvents',
            'billingInvoices',
            'failedInvoices',
            'assignablePlans'
        ) + [
            'roles' => Role::query()->orderBy('name')->get(),
            'canViewBilling' => $canViewBilling,
            'canManageSubscriptions' => $canManageSubscriptions,
        ]);
    }

    public function edit(User $user)
    {
        abort_unless(request()->user()->can('users.manage'), 403);

        return view('admin.users.edit', [
            'user' => $user->load('roleDefinition'),
            'roles' => Role::query()->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, User $user)
    {
        abort_unless($request->user()->can('users.manage'), 403);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,'.$user->id,
            'role' => 'required|exists:roles,slug',
        ]);

        if (! $request->user()->hasRole('super_admin') && $validated['role'] === 'super_admin') {
            abort(403);
        }

        if ($request->user()->id === $user->id && $request->user()->role !== $validated['role']) {
            return back()->with('error', 'You cannot change your own role.');
        }

        $user->update($validated);

        return redirect()
            ->route('admin.users.show', $user)
            ->with('success', 'User updated successfully');
    }

    // Ban / Unban user
    public function toggleBan(User $user)
    {
        abort_unless(request()->user()->can('users.manage'), 403);

        $user->is_banned = ! $user->is_banned;
        $user->save();

        return back()->with('success', 'User status updated');
    }

    // Verify user manually
    public function verify(User $user)
    {
        abort_unless(request()->user()->can('users.manage'), 403);

        $user->email_verified_at = now();
        $user->save();

        return back()->with('success', 'User verified');
    }

    // Reset password (admin action)
    public function resetPassword(User $user)
    {
        abort_unless(request()->user()->can('users.manage'), 403);

        $newPassword = 'password123';

        $user->password = Hash::make($newPassword);
        $user->save();

        return back()->with('success', 'Password reset to: '.$newPassword);
    }

    public function toggleRole(User $user)
    {
        abort_unless(request()->user()->can('users.manage'), 403);

        if (auth()->id() === $user->id) {
            return back()->with('error', 'You cannot change your own role.');
        }

        if ($user->role === 'super_admin' || auth()->user()->role !== 'super_admin' && $user->role !== 'user') {
            return back()->with('error', 'This role must be changed by a super admin from the edit screen.');
        }

        $user->role = $user->role === 'admin' ? 'user' : 'admin';
        $user->save();

        return back()->with('success', "User role updated to {$user->role}.");
    }

    public function destroy(User $user)
    {
        abort_unless(request()->user()->can('users.manage'), 403);

        // optional safety: prevent deleting yourself
        if (auth()->id() === $user->id) {
            return back()->with('error', 'You cannot delete yourself.');
        }

        if ($user->role === 'super_admin') {
            return back()->with('error', 'You cannot delete a super admin.');
        }

        $user->delete();

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'User deleted successfully');
    }

    public function changeSubscription(Request $request, User $user)
    {
        abort_unless($request->user()->can('subscriptions.manage'), 403);

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
            if (! $cancelCurrentStripe) {
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
            'audit' => [
                'event_type' => 'manual_override_applied',
                'source' => 'admin',
                'actor_id' => auth()->id(),
                'reason' => $reason,
                'metadata' => [
                    'transition_type' => 'manual_override',
                ],
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
                'audit' => [
                    'event_type' => 'admin_billing_change_applied',
                    'source' => 'admin',
                    'actor_id' => auth()->id(),
                    'reason' => $reason,
                    'metadata' => [
                        'transition_type' => 'billing_now',
                    ],
                ],
            ]);

            return back()->with('success', 'User moved to the selected free plan immediately.');
        }

        if ($currentSubscription->provider !== 'stripe' || ! $currentSubscription->stripe_subscription_id) {
            return back()->with('error', 'Immediate paid billing changes are only supported for Stripe-managed subscriptions. Use a manual override instead.');
        }

        $this->subscriptionEventService->record($user, $currentSubscription, 'admin_billing_change_requested', [
            'source' => 'admin',
            'actor_id' => auth()->id(),
            'from_plan_id' => $currentSubscription->plan_id,
            'to_plan_id' => $plan->id,
            'from_price_id' => $currentSubscription->price_id,
            'to_price_id' => $price->id,
            'status_before' => $currentSubscription->status,
            'status_after' => $currentSubscription->status,
            'reason' => $reason,
            'effective_at' => now(),
            'metadata' => [
                'transition_type' => 'billing_now',
            ],
        ]);

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
        if ($currentSubscription->provider !== 'stripe' || ! $currentSubscription->stripe_subscription_id) {
            return back()->with('error', 'End-of-cycle billing changes are only supported for Stripe-managed subscriptions.');
        }

        $this->subscriptionEventService->record($user, $currentSubscription, 'admin_scheduled_plan_change_requested', [
            'source' => 'admin',
            'actor_id' => auth()->id(),
            'from_plan_id' => $currentSubscription->plan_id,
            'to_plan_id' => $plan->id,
            'from_price_id' => $currentSubscription->price_id,
            'to_price_id' => $price->id,
            'status_before' => $currentSubscription->status,
            'status_after' => Subscription::STATUS_CANCELING,
            'reason' => $reason,
            'effective_at' => $currentSubscription->current_period_ends_at,
            'metadata' => [
                'transition_type' => 'billing_next_cycle',
            ],
        ]);

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
