<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckBanned
{
    public function handle(Request $request, Closure $next)
    {
        if (auth()->check()) {
            $bannedKey = "user_banned_" . auth()->id();

            if (cache()->has($bannedKey)) {
                auth()->logout();

                return redirect()->route('login')->withErrors([
                    'email' => 'Your account has been banned. Please contact support.'
                ]);
            }
        }

        return $next($request);
    }
}
