<?php

namespace App\Http\Controllers;

use Google_Client;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
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

    public function googleMobileAuth(Request $request)
    {
        $request->validate([
            'token' => ['required', 'string'],
            'accessToken' => ['nullable', 'string'],
        ]);
        Log::info('Request Object', ['request' => $request->all()]);

        $payload = $this->verifyGoogleIdToken($request->token);

        if (!$payload) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid Google token',
            ], 401);
        }

        if (empty($payload['email'])) {
            return response()->json([
                'success' => false,
                'message' => 'Google account email not available',
            ], 422);
        }

        $user = $this->findOrCreateGoogleUser($payload);

        $token = $user->createToken('mobile_auth_token')->plainTextToken;
        Log::info('Expected Ressponse', [
            'success' => true,
            'message' => 'Mobile Google authentication successful',
            'data' => [
                'token' => $token,
                'user' => $user,
            ],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Mobile Google authentication successful',
            'data' => [
                'token' => $token,
                'user' => $user,
            ],
        ]);
    }

    /**
     * Verify Google ID token against allowed client IDs
     */
    protected function verifyGoogleIdToken(string $idToken): array|false
    {
        $allowedClientIds = array_filter([
            config('services.google.expo_client_id'),
            config('services.google.android_client_id'),
            config('services.google.ios_client_id'),
            config('services.google.client_id'),
        ]);

        $client = new Google_Client();

        $payload = $client->verifyIdToken($idToken);

        if (!$payload) {
            return false;
        }

        if (!isset($payload['aud']) || !in_array($payload['aud'], $allowedClientIds, true)) {
            return false;
        }

        return $payload;
    }

    /**
     * Find existing user by email or create a new one, then link Google provider details
     */
    protected function findOrCreateGoogleUser(array $payload): User
    {
        $user = User::where('email', $payload['email'])->first();

        if ($user) {
            $updates = [];

            if (empty($user->provider)) {
                $updates['provider'] = 'google';
            }

            if (empty($user->provider_id)) {
                $updates['provider_id'] = $payload['sub'] ?? null;
            }

            if (empty($user->avatar) && !empty($payload['picture'])) {
                $updates['avatar'] = $payload['picture'];
            }

            if (empty($user->email_verified_at)) {
                $updates['email_verified_at'] = now();
            }

            if (!empty($updates)) {
                $user->update($updates);
                $user->refresh();
            }

            return $user;
        }

        return User::create([
            'name' => $payload['name'] ?? explode('@', $payload['email'])[0],
            'email' => $payload['email'],
            'provider' => 'google',
            'provider_id' => $payload['sub'] ?? null,
            'avatar' => $payload['picture'] ?? null,
            'email_verified_at' => now(),
            'password' => Hash::make(Str::random(32)),
        ]);
    }
}
