<?php

return [
    'default_slug' => env('SUBSCRIPTIONS_DEFAULT_PLAN', 'free'),

    'currency' => env('SUBSCRIPTIONS_CURRENCY', 'usd'),

    'feature_catalog' => [
        'scans.enabled' => [
            'label' => 'Barcode scans',
            'type' => 'boolean',
        ],
        'scans.monthly_limit' => [
            'label' => 'Monthly scan limit',
            'type' => 'integer',
        ],
        'recommendations.enabled' => [
            'label' => 'Personalized recommendations',
            'type' => 'boolean',
        ],
        'contributions.enabled' => [
            'label' => 'Community contributions',
            'type' => 'boolean',
        ],
        'history.export.enabled' => [
            'label' => 'History export',
            'type' => 'boolean',
        ],
        'priority_support.enabled' => [
            'label' => 'Priority support',
            'type' => 'boolean',
        ],
        'team.seats' => [
            'label' => 'Team seats',
            'type' => 'integer',
        ],
    ],

    'defaults' => [
        [
            'slug' => 'free',
            'name' => 'Free',
            'description' => 'For individual users getting started with Scanwell.',
            'is_default' => true,
            'display_order' => 1,
            'features' => [
                'scans' => [
                    'enabled' => true,
                    'monthly_limit' => 50,
                ],
                'recommendations' => [
                    'enabled' => false,
                ],
                'contributions' => [
                    'enabled' => true,
                ],
                'history' => [
                    'export' => [
                        'enabled' => false,
                    ],
                ],
                'priority_support' => [
                    'enabled' => false,
                ],
                'team' => [
                    'seats' => 1,
                ],
            ],
            'prices' => [
                [
                    'name' => 'Free',
                    'amount' => 0,
                    'currency' => 'usd',
                    'billing_interval' => 'month',
                    'billing_interval_count' => 1,
                    'trial_days' => 0,
                    'is_default' => true,
                ],
            ],
        ],
        [
            'slug' => 'pro',
            'name' => 'Pro',
            'description' => 'For power users who want more scans and premium insights.',
            'is_default' => false,
            'display_order' => 2,
            'features' => [
                'scans' => [
                    'enabled' => true,
                    'monthly_limit' => null,
                ],
                'recommendations' => [
                    'enabled' => true,
                ],
                'contributions' => [
                    'enabled' => true,
                ],
                'history' => [
                    'export' => [
                        'enabled' => true,
                    ],
                ],
                'priority_support' => [
                    'enabled' => true,
                ],
                'team' => [
                    'seats' => 1,
                ],
            ],
            'prices' => [
                [
                    'name' => 'Monthly',
                    'amount' => 1900,
                    'currency' => 'usd',
                    'billing_interval' => 'month',
                    'billing_interval_count' => 1,
                    'trial_days' => 7,
                    'is_default' => true,
                ],
                [
                    'name' => 'Annual',
                    'amount' => 19000,
                    'currency' => 'usd',
                    'billing_interval' => 'year',
                    'billing_interval_count' => 1,
                    'trial_days' => 7,
                    'is_default' => false,
                ],
            ],
        ],
        [
            'slug' => 'team',
            'name' => 'Team',
            'description' => 'For organizations managing multiple members and priority support.',
            'is_default' => false,
            'display_order' => 3,
            'features' => [
                'scans' => [
                    'enabled' => true,
                    'monthly_limit' => null,
                ],
                'recommendations' => [
                    'enabled' => true,
                ],
                'contributions' => [
                    'enabled' => true,
                ],
                'history' => [
                    'export' => [
                        'enabled' => true,
                    ],
                ],
                'priority_support' => [
                    'enabled' => true,
                ],
                'team' => [
                    'seats' => 10,
                ],
            ],
            'prices' => [
                [
                    'name' => 'Monthly',
                    'amount' => 7900,
                    'currency' => 'usd',
                    'billing_interval' => 'month',
                    'billing_interval_count' => 1,
                    'trial_days' => 14,
                    'is_default' => true,
                ],
                [
                    'name' => 'Annual',
                    'amount' => 79000,
                    'currency' => 'usd',
                    'billing_interval' => 'year',
                    'billing_interval_count' => 1,
                    'trial_days' => 14,
                    'is_default' => false,
                ],
            ],
        ],
    ],

    'stripe' => [
        'publishable_key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        'success_url' => env('STRIPE_CHECKOUT_SUCCESS_URL', rtrim((string) env('APP_URL', 'http://localhost'), '/').'/billing?checkout=success'),
        'cancel_url' => env('STRIPE_CHECKOUT_CANCEL_URL', rtrim((string) env('APP_URL', 'http://localhost'), '/').'/billing?checkout=cancelled'),
    ],
];
