<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class WebAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = Session::get('api_token');

        if (!$token) {
            return redirect()->route('web.login');
        }

        // Verify token with API
        try {
            $response = Http::withToken($token)
                ->timeout(5)
                ->get(config('app.url') . '/api/v1/user');

            if ($response->successful()) {
                $userData = $response->json('data');
                Session::put('user', $userData);
                return $next($request);
            }
        } catch (\Exception $e) {
            // Token verification failed
        }

        Session::forget(['api_token', 'user']);
        return redirect()->route('web.login')->with('error', 'Session expired. Please login again.');
//    }
//        return $next($request);
    }
}
