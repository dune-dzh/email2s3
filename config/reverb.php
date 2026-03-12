<?php

return [

    'apps' => [
        [
            'app_id' => env('REVERB_APP_ID'),
            'app_key' => env('REVERB_APP_KEY'),
            'app_secret' => env('REVERB_APP_SECRET'),
            'allowed_origins' => ['*'],
            'ping_interval' => 30,
            'activity_timeout' => 30,
        ],
    ],

    'options' => [
        'host' => env('REVERB_SERVER_HOST', '0.0.0.0'),
        'port' => env('REVERB_SERVER_PORT', 6001),
        'hostname' => env('REVERB_HOST') ?: 'localhost',
        'tls' => [],
    ],

];
