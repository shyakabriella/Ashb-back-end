<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | This allows your frontend website to call your Laravel API.
    | Example:
    | Frontend: https://ashbhub.com
    | API:      https://d.ashbhub.com/api
    |
    */

    'paths' => [
        'api/*',
        'sanctum/csrf-cookie',
        '*', // catches all routes, not just api/*
    ],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        /*
        |--------------------------------------------------------------------------
        | Local development frontend URLs
        |--------------------------------------------------------------------------
        */
        'http://localhost:3000',
        'http://127.0.0.1:3000',
        'http://localhost:5173',
        'http://127.0.0.1:5173',

        /*
        |--------------------------------------------------------------------------
        | Production frontend URLs
        |--------------------------------------------------------------------------
        */
        'https://ashbhub.com',
        'https://www.ashbhub.com',

        /*
        |--------------------------------------------------------------------------
        | Dashboard / API domain URLs
        |--------------------------------------------------------------------------
        */
        'https://d.ashbhub.com',
        'https://www.d.ashbhub.com',

        /*
        |--------------------------------------------------------------------------
        | Optional HTTP versions
        |--------------------------------------------------------------------------
        */
        'http://ashbhub.com',
        'http://www.ashbhub.com',
        'http://d.ashbhub.com',
        'http://www.d.ashbhub.com',
    ],

    'allowed_origins_patterns' => [
        /*
        |--------------------------------------------------------------------------
        | Allow localhost with any port
        |--------------------------------------------------------------------------
        */
        '/^http:\/\/localhost:\d+$/',
        '/^http:\/\/127\.0\.0\.1:\d+$/',

        /*
        |--------------------------------------------------------------------------
        | Allow AshBHub subdomains
        |--------------------------------------------------------------------------
        | Example:
        | https://dashboard.ashbhub.com
        | https://d.ashbhub.com
        |--------------------------------------------------------------------------
        */
        '/^https:\/\/([a-z0-9-]+\.)?ashbhub\.com$/',
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 86400,

    /*
    |--------------------------------------------------------------------------
    | supports_credentials
    |--------------------------------------------------------------------------
    |
    | Set to true because Sanctum cookie authentication is used from frontend.
    |
    */
    'supports_credentials' => true,

];