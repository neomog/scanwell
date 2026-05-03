<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class RbacSeeder extends Seeder
{
    public function run(): void
    {
        $permissionIds = collect(config('rbac.permissions', []))
            ->mapWithKeys(function (array $permission) {
                $record = Permission::query()->updateOrCreate(
                    ['slug' => $permission['slug']],
                    Arr::except($permission, ['slug']) + ['is_system' => true]
                );

                return [$record->slug => $record->id];
            });

        foreach (config('rbac.roles', []) as $slug => $roleConfig) {
            $role = Role::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $roleConfig['name'],
                    'description' => $roleConfig['description'] ?? null,
                    'is_system' => true,
                ]
            );

            if (($roleConfig['permissions'] ?? []) === ['*']) {
                $role->permissions()->sync($permissionIds->values()->all());
                continue;
            }

            $role->permissions()->sync(
                collect($roleConfig['permissions'] ?? [])
                    ->map(fn (string $permissionSlug) => $permissionIds->get($permissionSlug))
                    ->filter()
                    ->values()
                    ->all()
            );
        }

        DB::table('users')
            ->whereNull('role')
            ->orWhere('role', '')
            ->update(['role' => 'user']);

        User::query()
            ->where('email', 'superadmin@example.com')
            ->when(
                ! User::query()->where('role', 'super_admin')->exists(),
                fn ($query) => $query->orWhere('email', 'test@example.com')
            )
            ->update(['role' => 'super_admin']);
    }
}
