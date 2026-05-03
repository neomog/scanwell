<?php

return [
    'permissions' => [
        ['slug' => 'access.admin', 'name' => 'Access Admin Area', 'group' => 'access', 'description' => 'Open the internal admin dashboard.'],
        ['slug' => 'products.view', 'name' => 'View Products', 'group' => 'products', 'description' => 'Review products in the admin area.'],
        ['slug' => 'products.edit', 'name' => 'Edit Products', 'group' => 'products', 'description' => 'Create, update, and delete products.'],
        ['slug' => 'submissions.view', 'name' => 'View Submissions', 'group' => 'moderation', 'description' => 'Review pending and historical community submissions.'],
        ['slug' => 'submissions.approve', 'name' => 'Approve Submissions', 'group' => 'moderation', 'description' => 'Approve, reject, flag, and update moderated submissions.'],
        ['slug' => 'users.view', 'name' => 'View Users', 'group' => 'users', 'description' => 'See user profiles and user activity summaries.'],
        ['slug' => 'users.manage', 'name' => 'Manage Users', 'group' => 'users', 'description' => 'Create users, edit roles, ban users, and manage user access.'],
        ['slug' => 'plans.manage', 'name' => 'Manage Plans', 'group' => 'billing', 'description' => 'Manage subscription plans and prices.'],
        ['slug' => 'subscriptions.manage', 'name' => 'Manage Subscriptions', 'group' => 'billing', 'description' => 'Review and change subscription states.'],
        ['slug' => 'billing.view', 'name' => 'View Billing', 'group' => 'billing', 'description' => 'Access payment, invoice, refund, and billing details.'],
        ['slug' => 'logs.view', 'name' => 'View Logs', 'group' => 'support', 'description' => 'Review audit trails and internal activity logs.'],
        ['slug' => 'complaints.view', 'name' => 'View Complaints', 'group' => 'support', 'description' => 'Access complaint workflows when enabled.'],
        ['slug' => 'bugs.view', 'name' => 'View Bugs', 'group' => 'support', 'description' => 'Access bug-report workflows when enabled.'],
        ['slug' => 'leaderboard.view', 'name' => 'View Leaderboard', 'group' => 'moderation', 'description' => 'Review contribution leaderboard data.'],
        ['slug' => 'roles.manage', 'name' => 'Manage Roles', 'group' => 'security', 'description' => 'Create, edit, and assign permissions to roles.'],
        ['slug' => 'permissions.manage', 'name' => 'Manage Permissions', 'group' => 'security', 'description' => 'Create and maintain the permission catalog.'],
    ],
    'roles' => [
        'super_admin' => [
            'name' => 'Super Admin',
            'description' => 'Full platform control, including security and billing.',
            'permissions' => ['*'],
        ],
        'admin' => [
            'name' => 'Admin',
            'description' => 'Moderates contributions, manages users, and reviews operational logs without payment visibility.',
            'permissions' => [
                'access.admin',
                'products.view',
                'products.edit',
                'submissions.view',
                'submissions.approve',
                'users.view',
                'users.manage',
                'logs.view',
                'complaints.view',
                'leaderboard.view',
            ],
        ],
        'moderator' => [
            'name' => 'Moderator',
            'description' => 'Handles product submissions and quality review.',
            'permissions' => [
                'access.admin',
                'products.view',
                'submissions.view',
                'submissions.approve',
                'logs.view',
                'leaderboard.view',
            ],
        ],
        'support' => [
            'name' => 'Customer Service',
            'description' => 'Handles support-facing reviews without payment access.',
            'permissions' => [
                'access.admin',
                'users.view',
                'logs.view',
                'complaints.view',
                'bugs.view',
            ],
        ],
        'user' => [
            'name' => 'User',
            'description' => 'Default customer role for product scans and contributions.',
            'permissions' => [],
        ],
    ],
];
