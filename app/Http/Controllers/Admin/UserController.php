<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProductContribution;
use App\Models\Scan;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    // List all users
    public function index(Request $request)
    {
        $query = User::query();

        // Search
        if ($request->search) {
            $query->where('name', 'like', "%{$request->search}%")
                ->orWhere('email', 'like', "%{$request->search}%");
        }

        // Filter by role
        if ($request->role) {
            $query->where('role', $request->role);
        }

        $users = $query->latest()->paginate(10);

        return view('admin.users.index', compact('users'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'role' => 'required|in:user,admin',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => bcrypt($validated['password']),
            'role' => $validated['role'],
            'email_verified_at' => now(),
        ]);

        if ($request->boolean('send_welcome_email')) {
            // You can implement email sending here
            // Mail::to($user)->send(new WelcomeEmail($user, $request->password));
        }

        return redirect()->route('admin.users.show', $user)
            ->with('success', 'User created successfully.');
    }

    public function show(User $user)
    {
        $totalContributions = ProductContribution::where('user_id', $user->id)->count();

        $pendingContributions = ProductContribution::where('user_id', $user->id)
            ->where('status', 'pending')->count();

        $approvedContributions = ProductContribution::where('user_id', $user->id)
            ->where('status', 'approved')->count();

        $rejectedContributions = ProductContribution::where('user_id', $user->id)
            ->where('status', 'rejected')->count();

        $totalScans = Scan::where('user_id', $user->id)->count();

        $todayScans = Scan::where('user_id', $user->id)
            ->whereDate('created_at', today())
            ->count();

        $thisMonthScans = $user->scans()
            ->whereMonth('created_at', now()->month)
            ->count();

        $totalContributions = $user->contributions()->count();

        $pendingContributions = $user->contributions()
            ->where('status', 'pending')
            ->count();

        $approvedContributions = $user->contributions()
            ->where('status', 'approved')
            ->count();

        $recentContributions = $user->contributions()
            ->latest()
            ->limit(5)
            ->get();

        return view('admin.users.show', compact(
            'user',
            'totalScans',
            'todayScans',
            'thisMonthScans',
            'totalContributions',
            'pendingContributions',
            'approvedContributions',
            'recentContributions'
        ));
    }

    public function edit(User $user)
    {
        return view('admin.users.edit', compact('user'));
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'role' => 'required|in:user,admin',
        ]);

        $user->update($validated);

        return redirect()
            ->route('admin.users.show', $user)
            ->with('success', 'User updated successfully');
    }

    // Ban / Unban user
    public function toggleBan(User $user)
    {
        $user->is_banned = !$user->is_banned;
        $user->save();

        return back()->with('success', 'User status updated');
    }

    // Verify user manually
    public function verify(User $user)
    {
        $user->email_verified_at = now();
        $user->save();

        return back()->with('success', 'User verified');
    }

    // Reset password (admin action)
    public function resetPassword(User $user)
    {
        $newPassword = 'password123';

        $user->password = Hash::make($newPassword);
        $user->save();

        return back()->with('success', 'Password reset to: ' . $newPassword);
    }

    public function toggleRole(User $user)
    {
        if (auth()->id() === $user->id) {
            return back()->with('error', 'You cannot change your own role.');
        }

        $user->role = $user->role === 'admin' ? 'user' : 'admin';
        $user->save();

        return back()->with('success', "User role updated to {$user->role}.");
    }

    public function destroy(User $user)
    {
        // optional safety: prevent deleting yourself
        if (auth()->id() === $user->id) {
            return back()->with('error', 'You cannot delete yourself.');
        }

        if ($user->role === 'admin') {
            return back()->with('error', 'You cannot delete an admin.');
        }

        $user->delete();

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'User deleted successfully');
    }
}
