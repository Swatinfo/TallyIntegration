<?php

return [

    /*
    |--------------------------------------------------------------------------
    | TallyPrime Connection Settings
    |--------------------------------------------------------------------------
    |
    | Configure the connection to your TallyPrime instance running in
    | server mode. Tally must be running with HTTP server enabled on
    | the specified port for the integration to work.
    |
    */

    'host' => env('TALLY_HOST', 'localhost'),

    'port' => env('TALLY_PORT', 9000),

    'company' => env('TALLY_COMPANY', ''),

    'timeout' => env('TALLY_TIMEOUT', 30),

];
