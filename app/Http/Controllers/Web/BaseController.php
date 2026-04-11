<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Session;

class BaseController extends Controller
{
    protected $apiToken;
    protected $currentUser;

    public function __construct()
    {
        // This will be called after middleware
        if (Session::has('api_token')) {
            $this->apiToken = Session::get('api_token');
            $this->currentUser = Session::get('user');
        }
    }

    protected function apiGet($endpoint, $params = [])
    {
        try {
            $response = Http::withToken($this->apiToken)
                ->timeout(10)
                ->get(config('app.url') . '/api/v1/' . $endpoint, $params);

            if ($response->successful()) {
                return $response->json();
            }

            \Log::error('API GET failed', [
                'endpoint' => $endpoint,
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            return ['success' => false, 'data' => []];

        } catch (\Exception $e) {
            \Log::error('API GET exception', [
                'endpoint' => $endpoint,
                'error' => $e->getMessage()
            ]);

            return ['success' => false, 'data' => []];
        }
    }

    protected function apiPost($endpoint, $data = [])
    {
        try {
            $response = Http::withToken($this->apiToken)
                ->timeout(10)
                ->post(config('app.url') . '/api/v1/' . $endpoint, $data);

            if ($response->successful()) {
                return $response->json();
            }

            return ['success' => false, 'message' => 'Request failed'];

        } catch (\Exception $e) {
            \Log::error('API POST exception', [
                'endpoint' => $endpoint,
                'error' => $e->getMessage()
            ]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    protected function apiPut($endpoint, $data = [])
    {
        try {
            $response = Http::withToken($this->apiToken)
                ->timeout(10)
                ->put(config('app.url') . '/api/v1/' . $endpoint, $data);

            if ($response->successful()) {
                return $response->json();
            }

            return ['success' => false, 'message' => 'Request failed'];

        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    protected function apiDelete($endpoint)
    {
        try {
            $response = Http::withToken($this->apiToken)
                ->timeout(10)
                ->delete(config('app.url') . '/api/v1/' . $endpoint);

            if ($response->successful()) {
                return $response->json();
            }

            return ['success' => false, 'message' => 'Request failed'];

        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
