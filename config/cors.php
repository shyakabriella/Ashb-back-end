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
        | Add the domain where your Next.js / React website is running.
        |--------------------------------------------------------------------------
        */
        'https://ashbhub.com',
        'https://www.ashbhub.com',

        /*
        |--------------------------------------------------------------------------
        | Dashboard / API domain URLs
        |--------------------------------------------------------------------------
        | Keep these if you also open frontend/dashboard from these domains.
        |--------------------------------------------------------------------------
        */
        'https://d.ashbhub.com',
        'https://www.d.ashbhub.com',

        /*
        |--------------------------------------------------------------------------
        | Optional HTTP versions
        |--------------------------------------------------------------------------
        | Only needed if your site sometimes opens without HTTPS.
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
    | false is okay because your public chatbot API does not use cookies.
    | Keep false unless you are using Sanctum cookie authentication from frontend.
    |
    */
    'supports_credentials' => false,

];