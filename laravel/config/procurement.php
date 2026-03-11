<?php

return [
    'digikey' => [
        'client_id' => env('DIGIKEY_CLIENT_ID'),
        'client_secret' => env('DIGIKEY_CLIENT_SECRET'),
        'token_url' => env('DIGIKEY_TOKEN_URL', 'https://api.digikey.com/v1/oauth2/token'),
        'product_url' => env(
            'DIGIKEY_PRODUCT_URL',
            'https://api.digikey.com/products/v4/search/{part_number}/productdetails'
        ),
        'locale_site' => env('DIGIKEY_LOCALE_SITE', 'US'),
        'locale_language' => env('DIGIKEY_LOCALE_LANGUAGE', 'en'),
        'locale_currency' => env('DIGIKEY_LOCALE_CURRENCY', 'USD'),
        'account_id' => env('DIGIKEY_ACCOUNT_ID', '0'),
    ],

    'mouser' => [
        'api_key' => env('MOUSER_API_KEY', env('MOUSER_SEARCH_API_KEY')),
        'search_url' => env(
            'MOUSER_PART_SEARCH_URL',
            'https://api.mouser.com/api/v1.0/search/partnumber'
        ),
    ],

    'nexar' => [
        'client_id' => env('NEXAR_CLIENT_ID'),
        'client_secret' => env('NEXAR_CLIENT_SECRET'),
        'token_url' => env('NEXAR_TOKEN_URL', 'https://identity.nexar.com/connect/token'),
        'graphql_url' => env('NEXAR_GRAPHQL_URL', 'https://api.nexar.com/graphql'),
    ],

    'claude' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
        'base_url' => env('ANTHROPIC_BASE_URL', 'https://api.anthropic.com/v1'),
        'model' => env('ANTHROPIC_MODEL', 'claude-sonnet-4-20250514'),
        'max_tokens' => (int) env('ANTHROPIC_MAX_TOKENS', 1024),
        'anthropic_version' => env('ANTHROPIC_VERSION', '2023-06-01'),
    ],

    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
        'model' => env('GEMINI_MODEL', 'gemini-1.5-flash'),
        'max_output_tokens' => (int) env('GEMINI_MAX_OUTPUT_TOKENS', 2048),
    ],

    'research' => [
        'strict_mapping' => (bool) env('PROCUREMENT_STRICT_MAPPING', true),
        'min_match_score' => (float) env('PROCUREMENT_MIN_MATCH_SCORE', 0.9),
        'claude_batch_size' => (int) env('PROCUREMENT_CLAUDE_BATCH_SIZE', 50),
        'gemini_batch_size' => (int) env('PROCUREMENT_GEMINI_BATCH_SIZE', 50),
        'top_vendors' => (int) env('PROCUREMENT_TOP_VENDORS', 20),
        'items_per_vendor' => (int) env('PROCUREMENT_ITEMS_PER_VENDOR', 50),
        'top_spread_items' => (int) env('PROCUREMENT_TOP_SPREAD_ITEMS', 100),
    ],

    'schedule' => [
        'nightly_enabled' => (bool) env('PROCUREMENT_NIGHTLY_ENABLED', false),
        'nightly_time' => env('PROCUREMENT_NIGHTLY_TIME', '01:00'),
    ],
];
