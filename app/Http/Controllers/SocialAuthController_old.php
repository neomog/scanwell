<?php


namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Facades\Auth;
use Exception;

class SocialAuthController_old extends Controller
{
    use ApiResponse;

    /**
     * Redirect to provider for authentication
     */
    public function redirectToProvider($provider)
    {
        return Socialite::driver($provider)->stateless()->redirect();
    }

    /**
     * Handle provider callback
     */
    public function handleProviderCallback($provider)
    {
        try {
            $socialUser = Socialite::driver($provider)->stateless()->user();

            // Check if user exists with this email
            $user = User::where('email', $socialUser->getEmail())->first();

            if (!$user) {
                // Create new user
                $user = User::create([
                    'name' => $socialUser->getName(),
                    'email' => $socialUser->getEmail(),
                    'password' => Hash::make($socialUser->getEmail()),
                    'provider' => $provider,
                    'provider_id' => $socialUser->getId(),
                    'avatar' => $socialUser->getAvatar(),
                    'email_verified_at' => now(), // Social logins are verified
                ]);
            } else {
                // Update provider info if user exists
                $user->update([
                    'provider' => $provider,
                    'provider_id' => $socialUser->getId(),
                    'avatar' => $socialUser->getAvatar(),
                ]);
            }

            // Generate token
            $token = $user->createToken('auth_token')->plainTextToken;

            // Redirect back to mobile app with token
            // Using the scheme from your app.json
            $appScheme = 'scanwellapp';

            // For iOS Simulator/Android Emulator development
            // You might want to use exp://192.168.0.3:8081 for development
            // But for production, use your custom scheme

            // Redirect to your app with the token
            return redirect("{$appScheme}://auth/callback?token=" . urlencode($token));
//            return redirect("{$appScheme}://login?token={$token}");

        } catch (Exception $e) {
            // Redirect with error
            $appScheme = 'scanwellapp';
            return redirect("{$appScheme}://auth/callback?error=" . urlencode($e->getMessage()));
//            return redirect("{$appScheme}://login?error=" . urlencode($e->getMessage()));
        }
    }
}
//
//namespace App\Http\Controllers;
//
//use App\Http\Resources\UserResource;
//use App\Models\User;
//use App\Traits\ApiResponse;
//use Illuminate\Http\Request;
//use Illuminate\Support\Facades\Hash;
//use Illuminate\Support\Str;
//use Laravel\Socialite\Facades\Socialite;
//use Illuminate\Support\Facades\Auth;
//use Exception;
//
//class SocialAuthController extends Controller
//{
//    use ApiResponse;
//    /**
//     * Redirect to provider for authentication
//     */
//    public function redirectToProvider($provider)
//    {
//        return Socialite::driver($provider)->stateless()->redirect();
//    }
//
//    /**
//     * Handle provider callback
//     */
//    public function handleProviderCallback($provider)
//    {
//        try {
//            $socialUser = Socialite::driver($provider)->stateless()->user();
//
//            // Check if user exists with this email
//            $user = User::where('email', $socialUser->getEmail())->first();
//
//            if (!$user) {
//                // Create new user
//                $user = User::create([
//                    'name' => $socialUser->getName(),
//                    'email' => $socialUser->getEmail(),
//                    'password' => Hash::make(Str::random(24)),
//                    'provider' => $provider,
//                    'provider_id' => $socialUser->getId(),
//                    'avatar' => $socialUser->getAvatar(),
//                    'email_verified_at' => now(), // Social logins are verified
//                ]);
//            } else {
//                // Update provider info if user exists
//                $user->update([
//                    'provider' => $provider,
//                    'provider_id' => $socialUser->getId(),
//                    'avatar' => $socialUser->getAvatar(),
//                ]);
//            }
//
//            // Generate token
//            $token = $user->createToken('auth_token')->plainTextToken;
//
//            return $this->success(
//                [
//                    'user' => new UserResource($user),
//                    'token' => $token,
//                    'token_type' => 'Bearer',
//                ],
//                ucfirst($provider) . ' login successful'
//            );
//
//        } catch (Exception $e) {
//            return $this->error(
//                'Social authentication failed',
//                $e->getMessage(),
//                401
//            );
//        }
//    }
//}
