<?php

return [
    'channels' => [
        'in_app' => 'In-app',
        'email' => 'Email',
        'push' => 'Push',
    ],
    'types' => [
        'announcement' => 'Announcement',
        'campaign' => 'Campaign',
    ],
    'audiences' => [
        'all_users' => 'All users',
        'roles' => 'Specific roles',
        'users' => 'Specific users',
    ],
    'push' => [
        'enabled' => env('PUSH_NOTIFICATIONS_ENABLED', false),
        'endpoint' => env('PUSH_NOTIFICATIONS_ENDPOINT'),
        'token' => env('PUSH_NOTIFICATIONS_TOKEN'),
        'timeout' => (int) env('PUSH_NOTIFICATIONS_TIMEOUT', 10),
    ],
];
