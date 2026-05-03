# Roles And Permissions Documentation

## Summary

This document describes the Scanwell role-based access control implementation across:

- admin web access
- admin API access
- user management
- billing visibility
- moderation permissions
- security role management

It also explains:

- the default role matrix
- how permissions are seeded
- which routes are protected
- where to extend the model when new modules such as complaints or bugs are added

## Why This Exists

The earlier admin access model used a single `role` string and treated `admin` as full access.

That was not enough for the current operational model because the platform now needs:

- a `Super Admin` who can manage security and billing
- an `Admin` who can manage users and approve contributions without seeing payment information
- a `Moderator` who can review products and submissions
- a `Support` user who can review logs, complaints, and bugs without product or billing control
- normal `User` accounts for scanning, subscriptions, and contributions

The new RBAC layer makes those boundaries explicit.

## Current Authorization Model

Authorization is now permission-based.

The key rule is:

- access to `/admin` requires `access.admin`

After that, each functional area is protected by narrower permissions such as:

- `users.view`
- `users.manage`
- `products.view`
- `products.edit`
- `submissions.view`
- `submissions.approve`
- `billing.view`
- `subscriptions.manage`
- `plans.manage`
- `roles.manage`
- `permissions.manage`

## Default Roles

The default roles are defined in:

- [config/rbac.php](../config/rbac.php)

### 1. Super Admin

Purpose:

- full platform control

Capabilities:

- everything in the admin area
- billing visibility
- plan and subscription management
- role management
- permission management

Notes:

- `super_admin` is treated as an authorization override
- if a user has this role, all permission checks pass

### 2. Admin

Purpose:

- operational administration without payment visibility

Capabilities:

- access admin dashboard
- view and edit products
- review and approve submissions
- manage users
- review logs
- review complaints
- view leaderboard

Restrictions:

- cannot access billing pages
- cannot access plan management
- cannot access subscription management
- cannot manage roles or permission definitions

This matches the security requirement:

- admin can mark contributions and permit them
- admin can check logs and complaints
- admin cannot see payment information

### 3. Moderator

Purpose:

- moderation-only product and submission review

Capabilities:

- access admin dashboard
- view products
- review pending submissions
- approve, reject, and flag submissions
- view logs
- view leaderboard

Restrictions:

- cannot manage users
- cannot view billing
- cannot manage plans or subscriptions

### 4. Support

Purpose:

- customer service and operational review

Capabilities:

- access admin dashboard
- view users
- review logs
- review complaints
- review bugs

Restrictions:

- cannot moderate submissions
- cannot edit products
- cannot view billing
- cannot manage plans, subscriptions, roles, or permissions

Important note:

- `complaints.view` and `bugs.view` are seeded and reserved for future support modules
- there is no complaint or bug management feature in the current codebase yet

### 5. User

Purpose:

- standard customer account

Capabilities:

- register and login
- scan products
- use subscriptions
- contribute product changes if the plan allows it

Restrictions:

- no admin access

## Permission Catalog

The current seeded permission set is:

- `access.admin`
- `products.view`
- `products.edit`
- `submissions.view`
- `submissions.approve`
- `users.view`
- `users.manage`
- `plans.manage`
- `subscriptions.manage`
- `billing.view`
- `logs.view`
- `complaints.view`
- `bugs.view`
- `leaderboard.view`
- `notifications.view`
- `notifications.manage`
- `roles.manage`
- `permissions.manage`

## Data Model

The RBAC schema is introduced in:

- [database/migrations/2026_05_03_010000_create_roles_and_permissions_tables.php](../database/migrations/2026_05_03_010000_create_roles_and_permissions_tables.php)

Tables:

- `roles`
- `permissions`
- `permission_role`

User assignment remains on the existing `users.role` column.

That means:

- the user stores a role slug such as `admin` or `support`
- the `roles` table is the source of truth for role metadata
- the `permission_role` pivot defines the permissions each role receives

## Seeders

The RBAC defaults are seeded by:

- [database/seeders/RbacSeeder.php](../database/seeders/RbacSeeder.php)
- [database/seeders/DatabaseSeeder.php](../database/seeders/DatabaseSeeder.php)

Current seeding behavior:

- creates or updates the default permission catalog
- creates or updates the default roles
- syncs role-to-permission assignments
- ensures users without a role fall back to `user`
- ensures `superadmin@example.com` exists as a seeded super admin

## Application-Level Authorization

### User Helpers

The user authorization helpers live in:

- [app/Models/User.php](../app/Models/User.php)

Important methods:

- `hasRole()`
- `hasPermission()`
- `canAnyPermission()`

Behavior:

- `super_admin` always passes
- if role relations are loaded, permission checks use the database relation
- if role relations are not yet seeded, the app can still fall back to `config/rbac.php`

That fallback keeps older test flows and bootstrap scenarios working.

### Gate Registration

Gate bootstrap lives in:

- [app/Providers/AuthServiceProvider.php](../app/Providers/AuthServiceProvider.php)
- [bootstrap/providers.php](../bootstrap/providers.php)

Behavior:

- permissions such as `users.manage` and `billing.view` are resolved through Laravel Gates
- Blade `@can(...)` checks and middleware `can:...` checks now use the same source of truth

### Admin Access Middleware

Admin entry middleware lives in:

- [app/Http/Middleware/AdminMiddleware.php](../app/Http/Middleware/AdminMiddleware.php)

Behavior:

- old behavior: allowed only `role === admin`
- new behavior: allows any role with `access.admin`

That is what lets `support` and `moderator` into the admin shell without giving them billing or security control.

## Protected Web Routes

The main web protection map lives in:

- [routes/web.php](../routes/web.php)

Examples:

- `/admin/users` requires `users.view`
- `/admin/users/{user}/edit` requires `users.manage`
- `/admin/products` requires `products.view`
- `/admin/products/create` requires `products.edit`
- `/admin/contributions` requires `submissions.view`
- `/admin/contributions/{id}/approve` requires `submissions.approve`
- `/admin/billing` requires `billing.view`
- `/admin/notifications` requires `notifications.view`
- `/admin/notifications/create` requires `notifications.manage`
- `/admin/plans` requires `plans.manage`
- `/admin/subscriptions` requires `subscriptions.manage`
- `/admin/roles` requires `roles.manage`
- `/admin/permissions` requires `permissions.manage`

## Protected API Routes

Admin API protection lives in:

- [routes/api.php](../routes/api.php)

Examples:

- `GET /api/v1/admin/contributions/pending` requires `submissions.view`
- `POST /api/v1/admin/contributions/{id}/approve` requires `submissions.approve`
- `POST /api/v1/admin/products` requires `products.edit`
- `GET /api/v1/admin/notifications` requires `notifications.view`
- `POST /api/v1/admin/notifications` requires `notifications.manage`

The API still enters through the admin middleware alias, then narrows per endpoint with permission checks.

## Admin User Management Changes

The user management implementation now lives in:

- [app/Http/Controllers/Admin/UserController.php](../app/Http/Controllers/Admin/UserController.php)

Important changes:

- role validation now uses `exists:roles,slug`
- only authorized managers can create, update, ban, verify, or reset users
- non-super-admins cannot assign `super_admin`
- a user cannot change their own role
- super admins are protected from deletion

## Billing Visibility Rules

This was one of the most important security requirements.

The current behavior is:

- `billing.view` controls access to billing pages
- `subscriptions.manage` controls subscription reassignment and subscription admin pages
- user detail pages only load billing and subscription data if the viewer has the correct permission

Practical outcome:

- `Admin` can manage users and contributions
- `Admin` cannot see invoices, refunds, failures, or billing dashboards

## Role And Permission Management UI

The security management UI lives in:

- [app/Http/Controllers/Admin/RoleController.php](../app/Http/Controllers/Admin/RoleController.php)
- [app/Http/Controllers/Admin/PermissionController.php](../app/Http/Controllers/Admin/PermissionController.php)
- [resources/views/admin/roles/index.blade.php](../resources/views/admin/roles/index.blade.php)
- [resources/views/admin/permissions/index.blade.php](../resources/views/admin/permissions/index.blade.php)

Capabilities:

- create custom roles
- update role names and descriptions
- assign permissions to roles
- create custom permissions
- update permission metadata

System safety rules:

- system role slugs cannot be renamed casually
- system permission slugs cannot be renamed casually
- `super_admin` access is still enforced in the Gate layer

## Sidebar And Admin Navigation

The admin sidebar now hides links based on permissions.

Implementation:

- [resources/views/layouts/sidebar.blade.php](../resources/views/layouts/sidebar.blade.php)

Examples:

- support users do not see billing links
- admins do not see roles/permissions links
- moderators do not see user-management links

## Related Existing Modules

This RBAC implementation touches and protects existing modules documented elsewhere:

- [Contribution And Moderation API Contract](contribution-moderation-api.md)
- [Subscription Module Documentation](subscription-module.md)
- [Leaderboard Documentation](leaderboard.md)

Relationship to those modules:

- contribution moderation routes are now permission-gated
- billing and subscription administration are now restricted to the correct roles
- leaderboard visibility is now explicitly permission-gated

## How To Extend The Model

When a new internal module is added, the recommended pattern is:

1. Add a new permission slug in [config/rbac.php](../config/rbac.php).
2. Add that permission to the appropriate seeded roles.
3. Re-run seeding.
4. Protect the route with `->middleware('can:permission.slug')`.
5. Hide or show navigation using Blade `@can('permission.slug')`.
6. If needed, add tests for allowed and forbidden access.

Example future permissions:

- `complaints.manage`
- `bugs.manage`
- `reports.view`
- `reports.export`
- `audit_logs.view`

## Deployment Notes

After pulling this implementation into an environment, run:

```bash
php artisan migrate
php artisan db:seed --class=RbacSeeder
```

Or, if this is a fresh setup:

```bash
php artisan migrate --seed
```

## Testing Notes

Security tests were added in:

- [tests/Feature/RbacAccessTest.php](../tests/Feature/RbacAccessTest.php)

Coverage includes:

- admin can moderate but cannot view billing
- support can view users but cannot review contributions
- super admin can access role and permission management

Environment note:

- in the current workspace, `php artisan test` could not complete because the SQLite PDO driver is missing
- syntax validation and route listing were still checked successfully

## Related Files

- [config/rbac.php](../config/rbac.php)
- [app/Models/User.php](../app/Models/User.php)
- [app/Models/Role.php](../app/Models/Role.php)
- [app/Models/Permission.php](../app/Models/Permission.php)
- [app/Providers/AuthServiceProvider.php](../app/Providers/AuthServiceProvider.php)
- [app/Http/Middleware/AdminMiddleware.php](../app/Http/Middleware/AdminMiddleware.php)
- [app/Http/Controllers/Admin/UserController.php](../app/Http/Controllers/Admin/UserController.php)
- [app/Http/Controllers/Admin/RoleController.php](../app/Http/Controllers/Admin/RoleController.php)
- [app/Http/Controllers/Admin/PermissionController.php](../app/Http/Controllers/Admin/PermissionController.php)
- [routes/web.php](../routes/web.php)
- [routes/api.php](../routes/api.php)
