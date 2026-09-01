<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PermissionController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->can('permissions.manage'), 403);

        return view('admin.permissions.index', [
            'permissions' => Permission::query()->orderBy('group')->orderBy('name')->get()->groupBy('group'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('permissions.manage'), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:permissions,slug'],
            'group' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        Permission::query()->create([
            'name' => $validated['name'],
            'slug' => $validated['slug'] ?: Str::slug($validated['name'], '.'),
            'group' => $validated['group'] ?? 'custom',
            'description' => $validated['description'] ?? null,
            'is_system' => false,
        ]);

        return back()->with('success', 'Permission created successfully.');
    }

    public function update(Request $request, Permission $permission): RedirectResponse
    {
        abort_unless($request->user()->can('permissions.manage'), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:permissions,slug,'.$permission->id],
            'group' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($permission->is_system && $permission->slug !== $validated['slug']) {
            return back()->with('error', 'System permission slugs cannot be changed.');
        }

        $permission->update($validated);

        return back()->with('success', 'Permission updated successfully.');
    }
}
