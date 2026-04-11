<?php

namespace App\Http\Controllers;

use Google_Client;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SocialAuthController extends Controller
{
    public function googleAuth(Request $request)
    {
        $request->validate([
            'token' => 'required|string'
        ]);

//        $client = new Google_Client([
//            'client_id' => config('services.google.client_id')
//        ]);
        $client = new Google_Client([
            'client_id' => [
                config('services.google.expo_client_id'),
                config('services.google.android_client_id'),
//                config('services.google.ios_client_id'),
            ]
        ]);

        $payload = $client->verifyIdToken($request->token);

        if (!$payload) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid Google token'
            ], 401);
        }

// 🔥 Unified login + registration + linking
        $user = User::where('email', $payload['email'])->first();

        if ($user) {
// Link Google if not linked
            if (!$user->provider_id) {
                $user->update([
                    'provider' => 'google',
                    'provider_id' => $payload['sub'],
                ]);
            }
        } else {
            $user = User::create([
                'name' => $payload['name'] ?? explode('@', $payload['email'])[0],
                'email' => $payload['email'],
                'provider' => 'google',
                'provider_id' => $payload['sub'],
                'avatar' => $payload['picture'] ?? null,
                'email_verified_at' => now(),
                'password' => bcrypt(Str::random(24)),
            ]);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'data' => [
                'token' => $token,
                'user' => $user
            ]
        ]);
    }
}
