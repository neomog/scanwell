<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Exception;

class SocialAuthController_new_old extends Controller
{
    /**
     * Redirect to provider for authentication
     */
    public function redirectToProvider(Request $request, $provider)
    {
        $redirect = $request->get('redirect');

        return Socialite::driver($provider)
            ->stateless()
            ->with([
                'state' => base64_encode($redirect),
            ])
            ->redirect();
    }

    /**
     * Handle provider callback
     */
    public function handleProviderCallback(Request $request, $provider)
    {
        try {
            $socialUser = Socialite::driver($provider)->stateless()->user();

            // Get redirect from state
            $state = $request->get('state');
            $redirect = $state ? base64_decode($state) : 'scanwellapp://auth/callback';

            // Create or update user
            $user = User::updateOrCreate(
                ['email' => $socialUser->getEmail()],
                [
                    'name' => $socialUser->getName(),
                    'provider' => $provider,
                    'provider_id' => $socialUser->getId(),
                    'avatar' => $socialUser->getAvatar(),
                    'email_verified_at' => now(),
                    'password' => Hash::make(Str::random(24)),
                ]
            );

            $token = $user->createToken('auth_token')->plainTextToken;

            return redirect($redirect . '?token=' . urlencode($token));

        } catch (Exception $e) {

            $state = $request->get('state');
            $redirect = $state ? base64_decode($state) : 'scanwellapp://auth/callback';

            return redirect($redirect . '?error=' . urlencode($e->getMessage()));
        }
    }
}
