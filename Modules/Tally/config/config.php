<?php

return [

    'name' => 'Tally',

    /*
    |--------------------------------------------------------------------------
    | TallyPrime Default Connection Settings
    |--------------------------------------------------------------------------
    */

    'host' => env('TALLY_HOST', 'localhost'),

    'port' => env('TALLY_PORT', 9000),

    'company' => env('TALLY_COMPANY', ''),

    'timeout' => env('TALLY_TIMEOUT', 30),

    /*
    |--------------------------------------------------------------------------
    | Request/Response Logging
    |--------------------------------------------------------------------------
    */

    'logging' => [
        'enabled' => env('TALLY_LOG_REQUESTS', true),
        'channel' => 'tally',
        'max_body_size' => 10240,
    ],

    /*
    |--------------------------------------------------------------------------
    | Master Data Caching
    |--------------------------------------------------------------------------
    */

    'cache' => [
        'enabled' => env('TALLY_CACHE_ENABLED', true),
        'ttl' => env('TALLY_CACHE_TTL', 300),
        'prefix' => 'tally',
    ],

    /*
    |--------------------------------------------------------------------------
    | Circuit Breaker
    |--------------------------------------------------------------------------
    */

    'circuit_breaker' => [
        'enabled' => env('TALLY_CIRCUIT_BREAKER', true),
        'failure_threshold' => 5,
        'recovery_timeout' => 60,
    ],

];
