<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Session;

class DashboardController extends Controller
{
    public function index()
    {
        $token = Session::get('api_token');
        $user = Session::get('user');

        // Fetch dashboard statistics from API
        try {
            $stats = Http::withToken($token)
                ->get(config('app.url') . '/api/v1/admin/dashboard/stats')
                ->json();
        } catch (\Exception $e) {
            $stats = [];
        }

        return view('web.dashboard.index', compact('stats', 'user'));
    }
}
