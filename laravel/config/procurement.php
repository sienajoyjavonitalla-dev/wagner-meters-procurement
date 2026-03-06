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
];
