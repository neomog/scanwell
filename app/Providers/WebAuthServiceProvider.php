<?php
// app/Providers/WebAuthServiceProvider.php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Blade;

class WebAuthServiceProvider extends ServiceProvider
{
    public function boot()
    {
        // Register blade directives for permissions
        Blade::if('can', function ($permission) {
            $user = session('user');
            if (!$user) return false;

            // Check if user has permission (you'll implement this based on your role system)
            return in_array($permission, $user['permissions'] ?? []);
        });

        Blade::if('role', function ($role) {
            $user = session('user');
            return $user && ($user['role'] ?? '') === $role;
        });
    }
}
