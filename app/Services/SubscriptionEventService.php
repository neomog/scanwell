<?php

namespace App\Services;

use App\Models\Subscription;
use App\Models\SubscriptionEvent;
use App\Models\User;

class SubscriptionEventService
{
    public function record(User $user, ?Subscription $subscription, string $eventType, array $attributes = []): SubscriptionEvent
    {
        return SubscriptionEvent::create([
            'user_id' => $user->id,
            'subscription_id' => $subscription?->id,
            'event_type' => $eventType,
            'source' => $attributes['source'] ?? 'system',
            'actor_id' => $this->nullable($attributes['actor_id'] ?? null),
            'from_plan_id' => $this->nullable($attributes['from_plan_id'] ?? $subscription?->plan_id),
            'to_plan_id' => $this->nullable($attributes['to_plan_id'] ?? $subscription?->plan_id),
            'from_price_id' => $this->nullable($attributes['from_price_id'] ?? $subscription?->price_id),
            'to_price_id' => $this->nullable($attributes['to_price_id'] ?? $subscription?->price_id),
            'status_before' => $this->nullable($attributes['status_before'] ?? null),
            'status_after' => $this->nullable($attributes['status_after'] ?? $subscription?->status),
            'reason' => $this->nullable($attributes['reason'] ?? null),
            'effective_at' => $this->nullable($attributes['effective_at'] ?? null),
            'metadata' => $attributes['metadata'] ?? null,
        ]);
    }

    protected function nullable(mixed $value): mixed
    {
        return blank($value) ? null : $value;
    }
}
