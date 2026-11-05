<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Akahu app credentials
    |--------------------------------------------------------------------------
    |
    | Your app's ID token (app_token_...) and app secret, from
    | https://my.akahu.nz/developers.
    |
    */

    'app_token' => env('AKAHU_APP_TOKEN'),

    'app_secret' => env('AKAHU_APP_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | Default payee
    |--------------------------------------------------------------------------
    |
    | The account that receives payments. Account numbers use the format
    | 12-3456-7890123-00. Individual purchase() calls can override these.
    |
    */

    'payee_name' => env('AKAHU_PAYEE_NAME'),

    'payee_account_number' => env('AKAHU_PAYEE_ACCOUNT_NUMBER'),

    /*
    |--------------------------------------------------------------------------
    | Webhook signing key cache
    |--------------------------------------------------------------------------
    |
    | Akahu's webhook public keys are cached in this cache store so they are
    | not fetched on every webhook. A null store uses your default cache store;
    | ttl is in seconds. Set this option to false to turn the cache off.
    |
    */

    'webhook_key_cache' => [
        'store' => env('AKAHU_WEBHOOK_KEY_CACHE_STORE'),
        'ttl' => 60 * 60 * 24,
    ],

];
