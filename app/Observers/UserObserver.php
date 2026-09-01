<?php

namespace App\Observers;

use App\Models\User;
use App\Services\SubscriptionManager;
use Illuminate\Support\Facades\Schema;

class UserObserver
{
    public function created(User $user): void
    {
        if (!Schema::hasTable('subscription_plans') || !Schema::hasTable('subscriptions')) {
            return;
        }

        app(SubscriptionManager::class)->ensureDefaultSubscription($user);
    }
}
