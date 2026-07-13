<?php

return [
    // Portal authentication is delegated to auth.zigpaw.com. This guard exists
    // only so Laravel can run its session middleware without a local user store.
    'defaults' => [
        'guard' => 'web',
        'passwords' => null,
    ],

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'portal',
        ],
    ],

    'providers' => [
        'portal' => [
            'driver' => 'null',
        ],
    ],

    'passwords' => [],

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),
];
