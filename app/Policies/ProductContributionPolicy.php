<?php

namespace App\Policies;

use App\Models\User;
use App\Models\ProductContribution;

class ProductContributionPolicy
{
    public function viewPending(User $user): bool
    {
        return $user->role === 'admin';
    }

    public function approve(User $user): bool
    {
        return $user->role === 'admin';
    }

    public function reject(User $user): bool
    {
        return $user->role === 'admin';
    }

    public function flag(User $user): bool
    {
        return $user->role === 'admin';
    }
}
