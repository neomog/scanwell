<?php

namespace App\Http\Controllers\Web;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class UserController extends BaseController
{
    public function index()
    {
        $users = $this->apiGet('admin/users');

        return view('web.users.index', [
            'users' => $users['data'] ?? []
        ]);
    }

    public function show($id)
    {
        $user = $this->apiGet("admin/users/{$id}");

        return view('web.users.show', [
            'user' => $user['data'] ?? null
        ]);
    }

    public function ban($id)
    {
        $response = $this->apiPost("admin/users/{$id}/ban");

        if ($response['success'] ?? false) {
            return back()->with('success', 'User banned successfully');
        }

        return back()->with('error', 'Failed to ban user');
    }

    public function unban($id)
    {
        $response = $this->apiPost("admin/users/{$id}/unban");

        if ($response['success'] ?? false) {
            return back()->with('success', 'User unbanned successfully');
        }

        return back()->with('error', 'Failed to unban user');
    }
}
