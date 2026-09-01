<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionPrice;
use App\Services\SubscriptionManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SubscriptionPlanController extends Controller
{
    public function __construct(
        protected SubscriptionManager $subscriptionManager
    ) {
    }

    public function index(): View
    {
        return view('admin.plans.index', [
            'plans' => SubscriptionPlan::query()
                ->withCount(['prices', 'subscriptions'])
                ->with(['prices' => fn ($query) => $query->orderByDesc('is_default')->orderBy('amount')])
                ->orderBy('display_order')
                ->get(),
            'featureCatalog' => config('subscriptions.feature_catalog', []),
        ]);
    }

    public function create(): View
    {
        return view('admin.plans.create', [
            'featureCatalog' => config('subscriptions.feature_catalog', []),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatePlan($request);

        $plan = SubscriptionPlan::create([
            'slug' => $validated['slug'],
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'display_order' => (int) ($validated['display_order'] ?? 0),
            'is_active' => $request->boolean('is_active'),
            'is_default' => $request->boolean('is_default'),
            'features' => $this->normalizeFeatures($request),
        ]);

        $this->syncDefaultPlan($plan);

        return redirect()
            ->route('admin.plans.index')
            ->with('success', 'Plan created successfully.');
    }

    public function edit(SubscriptionPlan $plan): View
    {
        $plan->loadMissing('prices');
        $plan->loadCount(['prices', 'subscriptions']);

        return view('admin.plans.edit', [
            'plan' => $plan,
            'featureCatalog' => config('subscriptions.feature_catalog', []),
        ]);
    }

    public function update(Request $request, SubscriptionPlan $plan): RedirectResponse
    {
        $validated = $this->validatePlan($request, $plan);

        $plan->update([
            'slug' => $validated['slug'],
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'display_order' => (int) ($validated['display_order'] ?? 0),
            'is_active' => $request->boolean('is_active'),
            'is_default' => $request->boolean('is_default'),
            'features' => $this->normalizeFeatures($request),
        ]);

        $this->syncDefaultPlan($plan);

        return redirect()
            ->route('admin.plans.edit', $plan)
            ->with('success', 'Plan updated successfully.');
    }

    public function createPrice(SubscriptionPlan $plan): View
    {
        return view('admin.plans.price-create', [
            'plan' => $plan,
        ]);
    }

    public function storePrice(Request $request, SubscriptionPlan $plan): RedirectResponse
    {
        $validated = $this->validatePrice($request, $plan);

        $price = $plan->prices()->create([
            'name' => $validated['name'],
            'amount' => (int) $validated['amount'],
            'currency' => strtolower($validated['currency']),
            'billing_interval' => $validated['billing_interval'],
            'billing_interval_count' => (int) $validated['billing_interval_count'],
            'trial_days' => (int) ($validated['trial_days'] ?? 0),
            'stripe_price_id' => $validated['stripe_price_id'] ?? null,
            'is_active' => $request->boolean('is_active'),
            'is_default' => $request->boolean('is_default'),
        ]);

        $this->syncDefaultPrice($price);

        return redirect()
            ->route('admin.plans.edit', $plan)
            ->with('success', 'Price created successfully.');
    }

    public function editPrice(SubscriptionPrice $price): View
    {
        $price->loadMissing('plan');

        return view('admin.plans.price-edit', [
            'price' => $price,
            'plan' => $price->plan,
        ]);
    }

    public function updatePrice(Request $request, SubscriptionPrice $price): RedirectResponse
    {
        $price->loadMissing('plan');
        $validated = $this->validatePrice($request, $price->plan, $price);

        $price->update([
            'name' => $validated['name'],
            'amount' => (int) $validated['amount'],
            'currency' => strtolower($validated['currency']),
            'billing_interval' => $validated['billing_interval'],
            'billing_interval_count' => (int) $validated['billing_interval_count'],
            'trial_days' => (int) ($validated['trial_days'] ?? 0),
            'stripe_price_id' => $validated['stripe_price_id'] ?? null,
            'is_active' => $request->boolean('is_active'),
            'is_default' => $request->boolean('is_default'),
        ]);

        $this->syncDefaultPrice($price);

        return redirect()
            ->route('admin.plans.edit', $price->plan)
            ->with('success', 'Price updated successfully.');
    }

    public function toggleActive(SubscriptionPlan $plan): RedirectResponse
    {
        if ($plan->is_default && $plan->is_active) {
            return back()->with('error', 'The default plan cannot be archived.');
        }

        $plan->update([
            'is_active' => !$plan->is_active,
        ]);

        return back()->with('success', $plan->is_active
            ? 'Plan activated successfully.'
            : 'Plan archived successfully.');
    }

    public function destroy(SubscriptionPlan $plan): RedirectResponse
    {
        $plan->loadCount(['prices', 'subscriptions']);

        if ($plan->is_default) {
            return back()->with('error', 'The default plan cannot be deleted.');
        }

        if ($plan->prices_count > 0) {
            return back()->with('error', 'Delete or archive this plan’s prices before deleting the plan.');
        }

        if ($plan->subscriptions_count > 0) {
            return back()->with('error', 'Plans with subscription history cannot be deleted. Archive the plan instead.');
        }

        $plan->delete();

        return redirect()
            ->route('admin.plans.index')
            ->with('success', 'Plan deleted successfully.');
    }

    public function togglePriceActive(SubscriptionPrice $price): RedirectResponse
    {
        $price->loadMissing('plan');

        if ($price->is_default && $price->is_active) {
            $replacement = $price->plan->prices()
                ->whereKeyNot($price->id)
                ->where('is_active', true)
                ->orderBy('amount')
                ->first();

            if (!$replacement) {
                return back()->with('error', 'The default price cannot be archived unless another active price exists for this plan.');
            }

            $replacement->update(['is_default' => true]);
            $price->update(['is_default' => false]);
        }

        $price->update([
            'is_active' => !$price->is_active,
        ]);

        return back()->with('success', $price->is_active
            ? 'Price activated successfully.'
            : 'Price archived successfully.');
    }

    public function destroyPrice(SubscriptionPrice $price): RedirectResponse
    {
        $price->loadMissing('plan');
        $price->loadCount('subscriptions');

        if ($price->subscriptions_count > 0) {
            return back()->with('error', 'Prices with subscription history cannot be deleted. Archive the price instead.');
        }

        if ($price->is_default) {
            $replacement = $price->plan->prices()
                ->whereKeyNot($price->id)
                ->where('is_active', true)
                ->orderBy('amount')
                ->first();

            if ($replacement) {
                $replacement->update(['is_default' => true]);
            } elseif ($price->plan->is_active) {
                return back()->with('error', 'The last default price for an active plan cannot be deleted.');
            }
        }

        $plan = $price->plan;
        $price->delete();

        return redirect()
            ->route('admin.plans.edit', $plan)
            ->with('success', 'Price deleted successfully.');
    }

    protected function validatePlan(Request $request, ?SubscriptionPlan $plan = null): array
    {
        $validated = $request->validate([
            'slug' => ['required', 'string', 'max:100', 'alpha_dash', Rule::unique('subscription_plans', 'slug')->ignore($plan?->id)],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'display_order' => ['nullable', 'integer', 'min:0'],
            'features.scans.monthly_limit' => ['nullable', 'integer', 'min:0'],
            'features.team.seats' => ['nullable', 'integer', 'min:1'],
        ]);

        if ($request->boolean('is_default') && !$request->boolean('is_active')) {
            throw ValidationException::withMessages([
                'is_active' => 'The default plan must remain active.',
            ]);
        }

        return $validated;
    }

    protected function validatePrice(
        Request $request,
        SubscriptionPlan $plan,
        ?SubscriptionPrice $price = null
    ): array {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'integer', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'billing_interval' => ['required', Rule::in(['month', 'year'])],
            'billing_interval_count' => ['required', 'integer', 'min:1', 'max:12'],
            'trial_days' => ['nullable', 'integer', 'min:0', 'max:60'],
            'stripe_price_id' => ['nullable', 'string', 'max:255'],
        ]);

        if ($plan->slug === config('subscriptions.default_slug', 'free') && (int) $validated['amount'] !== 0) {
            throw ValidationException::withMessages([
                'amount' => 'The default free plan must keep a zero-price billing option.',
            ]);
        }

        if ($request->boolean('is_default') && !$request->boolean('is_active')) {
            throw ValidationException::withMessages([
                'is_active' => 'The default price must remain active.',
            ]);
        }

        return $validated;
    }

    protected function normalizeFeatures(Request $request): array
    {
        $catalog = config('subscriptions.feature_catalog', []);
        $input = $request->input('features', []);
        $features = [];

        foreach ($catalog as $key => $definition) {
            $type = $definition['type'] ?? 'boolean';
            $value = data_get($input, $key);

            if ($type === 'integer') {
                data_set($features, $key, $value === null || $value === '' ? null : (int) $value);
                continue;
            }

            data_set($features, $key, (bool) $value);
        }

        return $features;
    }

    protected function syncDefaultPlan(SubscriptionPlan $plan): void
    {
        if ($plan->is_default) {
            SubscriptionPlan::query()
                ->whereKeyNot($plan->id)
                ->update(['is_default' => false]);

            return;
        }

        if (!SubscriptionPlan::query()->where('is_default', true)->exists()) {
            $plan->update(['is_default' => true]);
        }
    }

    protected function syncDefaultPrice(SubscriptionPrice $price): void
    {
        if ($price->is_default) {
            SubscriptionPrice::query()
                ->where('plan_id', $price->plan_id)
                ->whereKeyNot($price->id)
                ->update(['is_default' => false]);

            return;
        }

        if (!SubscriptionPrice::query()->where('plan_id', $price->plan_id)->where('is_default', true)->exists()) {
            $price->update(['is_default' => true]);
        }
    }
}
