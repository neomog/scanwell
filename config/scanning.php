<?php

use App\Services\OpenFoodFactsService;

return [
    'providers' => [
        OpenFoodFactsService::class,
    ],

    'min_candidate_confidence' => (int) env('SCAN_MIN_CANDIDATE_CONFIDENCE', 45),
    'perfect_score_cap' => (int) env('SCAN_PERFECT_SCORE_CAP', 95),
    'sparse_food_score_cap' => (int) env('SCAN_SPARSE_FOOD_SCORE_CAP', 65),
    'sparse_cosmetic_score_cap' => (int) env('SCAN_SPARSE_COSMETIC_SCORE_CAP', 55),
    'water_plastic_score_cap' => (int) env('SCAN_WATER_PLASTIC_SCORE_CAP', 89),
    'stale_after_days' => (int) env('SCAN_STALE_AFTER_DAYS', 30),

    'open_food_facts' => [
        'timeout' => (int) env('OPENFOODFACTS_TIMEOUT', 10),
        'retry_attempts' => (int) env('OPENFOODFACTS_RETRY_ATTEMPTS', 3),
        'sources' => [
            [
                'key' => 'open_food_facts',
                'base_url' => env('OPENFOODFACTS_BASE_URL', 'https://world.openfoodfacts.org/api/v2'),
                'family_hint' => 'food',
            ],
            [
                'key' => 'open_beauty_facts',
                'base_url' => env('OPENBEAUTYFACTS_BASE_URL', 'https://world.openbeautyfacts.org/api/v2'),
                'family_hint' => 'cosmetic',
            ],
            [
                'key' => 'open_product_facts',
                'base_url' => env('OPENPRODUCTFACTS_BASE_URL', 'https://world.openproductfacts.org/api/v2'),
                'family_hint' => 'general',
            ],
            [
                'key' => 'open_pet_food_facts',
                'base_url' => env('OPENPETFOODFACTS_BASE_URL', 'https://world.openpetfoodfacts.org/api/v2'),
                'family_hint' => 'pet_food',
            ],
        ],
    ],
];
