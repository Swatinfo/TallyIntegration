<?php

return [

    /*
    |--------------------------------------------------------------------------
    | TallyPrime Connection Settings
    |--------------------------------------------------------------------------
    |
    | Default connection used when no specific connection code is provided.
    | For multi-company setups, connections are stored in the
    | tally_connections database table and resolved via route middleware.
    |
    */

    'host' => env('TALLY_HOST', 'localhost'),

    'port' => env('TALLY_PORT', 9000),

    'company' => env('TALLY_COMPANY', ''),

    'timeout' => env('TALLY_TIMEOUT', 30),

];
