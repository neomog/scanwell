<?php

namespace App\Http\Middleware;

use App\Exceptions\PlanFeatureException;
use App\Services\SubscriptionManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlanFeature
{
    public function __construct(
        protected SubscriptionManager $subscriptionManager
    ) {
    }

    public function handle(Request $request, Closure $next, string $feature): Response
    {
        try {
            if ($request->user()) {
                $this->subscriptionManager->ensureFeature($request->user(), $feature);
            }
        } catch (PlanFeatureException $exception) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => $exception->getMessage(),
                    'feature' => $feature,
                ], 403);
            }

            return redirect()
                ->route('billing.index')
                ->with('error', $exception->getMessage());
        }

        return $next($request);
    }
}
