<?php

use App\Services\EdamamFoodDatabaseService;
use App\Services\OpenAiBarcodeWebSearchService;
use App\Services\OpenFoodFactsService;
use App\Services\UsdaFoodDataCentralService;

return [
    'providers' => [
        OpenFoodFactsService::class,
        EdamamFoodDatabaseService::class,
        UsdaFoodDataCentralService::class,
        OpenAiBarcodeWebSearchService::class,
    ],

    'min_candidate_confidence' => (int) env('SCAN_MIN_CANDIDATE_CONFIDENCE', 45),
    'perfect_score_cap' => (int) env('SCAN_PERFECT_SCORE_CAP', 95),
    'sparse_food_score_cap' => (int) env('SCAN_SPARSE_FOOD_SCORE_CAP', 65),
    'sparse_cosmetic_score_cap' => (int) env('SCAN_SPARSE_COSMETIC_SCORE_CAP', 55),
    'water_plastic_score_cap' => (int) env('SCAN_WATER_PLASTIC_SCORE_CAP', 89),
    'stale_after_days' => (int) env('SCAN_STALE_AFTER_DAYS', 30),
    'trusted_search_enrichment' => [
        'enabled' => (bool) env('SCAN_TRUSTED_SEARCH_ENRICHMENT_ENABLED', true),
        'page_size' => (int) env('SCAN_TRUSTED_SEARCH_PAGE_SIZE', 6),
        'min_match_score' => (int) env('SCAN_TRUSTED_SEARCH_MIN_MATCH_SCORE', 80),
    ],
    'ai_catalog_enrichment' => [
        'enabled' => (bool) env('SCAN_AI_CATALOG_ENRICHMENT_ENABLED', true),
        'cache_ttl_minutes' => (int) env('SCAN_AI_CATALOG_ENRICHMENT_CACHE_TTL', 10080),
        'image_download_timeout' => (int) env('SCAN_AI_CATALOG_IMAGE_TIMEOUT', 8),
    ],

    'open_food_facts' => [
        'timeout' => (int) env('OPENFOODFACTS_TIMEOUT', 4),
        'retry_attempts' => (int) env('OPENFOODFACTS_RETRY_ATTEMPTS', 1),
        'sources' => [
            [
                'key' => 'open_food_facts',
                'base_url' => env('OPENFOODFACTS_BASE_URL', 'https://world.openfoodfacts.org/api/v2'),
                'family_hint' => 'food',
                'enabled' => true,
            ],
            [
                'key' => 'open_beauty_facts',
                'base_url' => env('OPENBEAUTYFACTS_BASE_URL', 'https://world.openbeautyfacts.org/api/v2'),
                'family_hint' => 'cosmetic',
                'enabled' => true,
            ],
            [
                'key' => 'open_product_facts',
                'base_url' => env('OPENPRODUCTFACTS_BASE_URL', 'https://world.openproductfacts.org/api/v2'),
                'family_hint' => 'general',
                'enabled' => (bool) env('OPENPRODUCTFACTS_ENABLED', false),
            ],
            [
                'key' => 'open_pet_food_facts',
                'base_url' => env('OPENPETFOODFACTS_BASE_URL', 'https://world.openpetfoodfacts.org/api/v2'),
                'family_hint' => 'pet_food',
                'enabled' => true,
            ],
        ],
    ],
];
