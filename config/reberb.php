<?php

return [
    'servers' => [
        'reverb' => [
            'host' => env('REVERB_SERVER_HOST', '0.0.0.0'),
            'port' => env('REVERB_SERVER_PORT', 8080),
            'scaling' => [
                'enabled' => false,
            ],
            'options' => [
                'tls' => env('REVERB_SERVER_TLS', false),
            ],
        ],
    ],

    'apps' => [
        [
            'app_id' => env('REVERB_APP_ID', 'chat-app'),
            'key' => env('REVERB_APP_KEY'),
            'secret' => env('REVERB_APP_SECRET'),
            'capacity' => null,
            'allowed_origins' => explode(',', env('REVERB_ALLOWED_ORIGINS', '*')),
            'ping_interval' => 60,
            'max_message_size' => 10_000,
        ],
    ],
];
