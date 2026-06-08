<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductContributionResource;
use App\Http\Resources\ScanResource;
use App\Http\Resources\SupportCaseResource;
use App\Http\Resources\UserNotificationResource;
use App\Http\Resources\UserPreferenceResource;
use App\Http\Resources\UserResource;
use App\Services\StripeSubscriptionService;
use App\Services\SubscriptionManager;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Throwable;

class AccountController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected SubscriptionManager $subscriptionManager,
        protected StripeSubscriptionService $stripeSubscriptionService
    ) {}

    public function export(Request $request): JsonResponse
    {
        $user = $request->user()->loadMissing('preferences');

        $scans = $user->scans()
            ->with('product')
            ->latest()
            ->get();

        $contributions = $user->contributions()
            ->with(['product.images', 'product.barcodes', 'reviewer'])
            ->latest()
            ->get();

        $supportCases = $user->supportCases()
            ->with(['user', 'assignee'])
            ->latest()
            ->get();

        $notifications = $user->notifications()
            ->latest()
            ->get();

        return $this->success([
            'generated_at' => now()->toIso8601String(),
            'account' => (new UserResource($user->fresh()))->resolve(),
            'preferences' => $user->preferences
                ? (new UserPreferenceResource($user->preferences))->resolve()
                : null,
            'summary' => [
                'scans_count' => $scans->count(),
                'contributions_count' => $contributions->count(),
                'support_cases_count' => $supportCases->count(),
                'notifications_count' => $notifications->count(),
            ],
            'scans' => ScanResource::collection($scans)->resolve(),
            'contributions' => ProductContributionResource::collection($contributions)->resolve(),
            'support_cases' => SupportCaseResource::collection($supportCases)->resolve(),
            'notifications' => UserNotificationResource::collection($notifications)->resolve(),
        ], 'Account export generated');
    }

    public function destroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'confirmation' => ['required', 'string'],
            'password' => ['nullable', 'string'],
            'email' => ['nullable', 'email'],
        ]);

        $user = $request->user();
        $confirmation = strtoupper(trim((string) $validated['confirmation']));

        if ($confirmation !== 'DELETE') {
            return $this->error('Type DELETE to confirm account deletion.', [
                'confirmation' => ['Type DELETE to confirm account deletion.'],
            ], 422);
        }

        if ($user->provider === 'google') {
            if (strcasecmp((string) ($validated['email'] ?? ''), (string) $user->email) !== 0) {
                return $this->error('Enter your account email to confirm deletion.', [
                    'email' => ['Enter your account email to confirm deletion.'],
                ], 422);
            }
        } else {
            if (empty($validated['password']) || ! Hash::check((string) $validated['password'], (string) $user->password)) {
                return $this->error('Your current password is incorrect.', [
                    'password' => ['Your current password is incorrect.'],
                ], 422);
            }
        }

        $subscription = $this->subscriptionManager
            ->currentSubscription($user)
            ->loadMissing(['plan', 'price']);

        if (
            $subscription->provider === 'stripe'
            && $subscription->isPaid()
            && filled($subscription->stripe_subscription_id)
        ) {
            try {
                $this->stripeSubscriptionService->cancel($subscription, true);
            } catch (Throwable) {
                return $this->error(
                    'We could not close your active paid subscription right now. Please try again in a moment.',
                    null,
                    422
                );
            }
        }

        $user->tokens()->delete();
        $user->delete();

        return $this->success(null, 'Account deleted successfully');
    }
}
