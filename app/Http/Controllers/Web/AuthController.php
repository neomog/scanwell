<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('web.auth.login');
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        try {
            $response = Http::post(config('app.url') . '/api/login', [
                'email' => $request->email,
                'password' => $request->password,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                Session::put('api_token', $data['token']);
                Session::put('user', $data['user']);

                return redirect()->route('web.dashboard')->with('success', 'Welcome back!');
            }

            return back()->with('error', 'Invalid credentials');

        } catch (\Exception $e) {
            \Log::error('Login error: ' . $e->getMessage());
            return back()->with('error', 'Unable to connect to server');
        }
    }

    public function showRegister()
    {
        return view('web.auth.register');
    }

    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        try {
            $response = Http::post(config('app.url') . '/api/register', [
                'name' => $request->name,
                'email' => $request->email,
                'password' => $request->password,
                'password_confirmation' => $request->password_confirmation,
            ]);

            if ($response->successful()) {
                return redirect()->route('web.login')->with('success', 'Registration successful! Please login.');
            }

            return back()->with('error', 'Registration failed');

        } catch (\Exception $e) {
            return back()->with('error', 'Unable to connect to server');
        }
    }

    public function logout()
    {
        try {
            $token = Session::get('api_token');
            if ($token) {
                Http::withToken($token)->post(config('app.url') . '/api/logout');
            }
        } catch (\Exception $e) {
            // Ignore logout errors
        }

        Session::flush();
        return redirect()->route('web.login')->with('success', 'Logged out successfully');
    }

    public function showForgotPassword()
    {
        return view('web.auth.forgot-password');
    }

    public function forgotPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        try {
            $response = Http::post(config('app.url') . '/api/forgot-password', [
                'email' => $request->email,
            ]);

            if ($response->successful()) {
                return back()->with('success', 'Password reset link sent to your email');
            }

            return back()->with('error', 'Unable to send reset link');

        } catch (\Exception $e) {
            return back()->with('error', 'Unable to connect to server');
        }
    }

    public function showResetPassword($token)
    {
        return view('web.auth.reset-password', ['token' => $token]);
    }

    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        try {
            $response = Http::post(config('app.url') . '/api/reset-password', [
                'token' => $request->token,
                'email' => $request->email,
                'password' => $request->password,
                'password_confirmation' => $request->password_confirmation,
            ]);

            if ($response->successful()) {
                return redirect()->route('web.login')->with('success', 'Password reset successful! Please login.');
            }

            return back()->with('error', 'Unable to reset password');

        } catch (\Exception $e) {
            return back()->with('error', 'Unable to connect to server');
        }
    }
}
