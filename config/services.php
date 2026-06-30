<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // oauth provider configuration
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT'),
        'expo_client_id' => env('GOOGLE_EXPO_CLIENT_ID'),
        'android_client_id' => env('GOOGLE_ANDROID_CLIENT_ID'),
        'ios_client_id' => env('GOOGLE_IOS_CLIENT_ID'),
    ],

    'facebook' => [
        'client_id' => env('FACEBOOK_CLIENT_ID'),
        'client_secret' => env('FACEBOOK_CLIENT_SECRET'),
        'redirect' => env('FACEBOOK_REDIRECT'),
    ],

    'github' => [
        'client_id' => env('GITHUB_CLIENT_ID'),
        'client_secret' => env('GITHUB_CLIENT_SECRET'),
        'redirect' => env('GITHUB_REDIRECT'),
    ],

    'openfoodfacts' => [
        'base_url' => env('OPENFOODFACTS_BASE_URL', 'https://world.openfoodfacts.org/api/v2'),
        'beauty_url' => env('OPENBEAUTYFACTS_BASE_URL', 'https://world.openbeautyfacts.org/api/v2'),
        'timeout' => env('OPENFOODFACTS_TIMEOUT', 10),
        'retry_attempts' => env('OPENFOODFACTS_RETRY_ATTEMPTS', 3),
    ],

    'edamam' => [
        'base_url' => env('EDAMAM_BASE_URL', 'https://api.edamam.com/api/food-database/v2/parser'),
        'app_id' => env('EDAMAM_APP_ID'),
        'app_key' => env('EDAMAM_APP_KEY'),
        'category' => env('EDAMAM_CATEGORY', 'packaged-foods'),
        'nutrition_type' => env('EDAMAM_NUTRITION_TYPE', 'cooking'),
        'timeout' => env('EDAMAM_TIMEOUT', 10),
        'retry_attempts' => env('EDAMAM_RETRY_ATTEMPTS', 1),
        'cooldown_seconds' => env('EDAMAM_COOLDOWN_SECONDS', 60),
    ],

    'gs1_us' => [
        'base_url' => env('GS1_US_BASE_URL'),
        'api_key' => env('GS1_US_API_KEY'),
        'account_id' => env('GS1_US_ACCOUNT_ID'),
        'http_method' => env('GS1_US_HTTP_METHOD', 'GET'),
        'barcode_field' => env('GS1_US_BARCODE_FIELD', 'gtin'),
        'barcode_path' => env('GS1_US_BARCODE_PATH', 'gtin'),
        'timeout' => env('GS1_US_TIMEOUT', 12),
        'retry_attempts' => env('GS1_US_RETRY_ATTEMPTS', 1),
    ],

    'google_cloud_vision' => [
        'base_url' => env('GOOGLE_CLOUD_VISION_BASE_URL', 'https://vision.googleapis.com/v1'),
        'token_url' => env('GOOGLE_CLOUD_TOKEN_URL', 'https://oauth2.googleapis.com/token'),
        'credentials_path' => env('GOOGLE_CLOUD_VISION_CREDENTIALS_PATH'),
        'credentials_json' => env('GOOGLE_CLOUD_VISION_CREDENTIALS_JSON'),
        'timeout' => env('GOOGLE_CLOUD_VISION_TIMEOUT', 30),
    ],

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
        'timeout' => env('OPENAI_TIMEOUT', 30),
        'image_recognition_model' => env('OPENAI_IMAGE_RECOGNITION_MODEL', 'gpt-5.4-mini'),
        'ingredient_extraction_model' => env('OPENAI_INGREDIENT_EXTRACTION_MODEL', env('OPENAI_IMAGE_RECOGNITION_MODEL', 'gpt-5.4-mini')),
        'nutrition_extraction_model' => env('OPENAI_NUTRITION_EXTRACTION_MODEL', env('OPENAI_IMAGE_RECOGNITION_MODEL', 'gpt-5.4-mini')),
    ],

];
