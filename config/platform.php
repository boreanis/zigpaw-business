<?php

return [
    'api_url' => rtrim((string) env('PLATFORM_API_URL', 'https://api.zigpaw.test'), '/'),
    'auth_url' => rtrim((string) env('PLATFORM_AUTH_URL', 'https://auth.zigpaw.test'), '/'),
    'oauth_client_id' => env('PLATFORM_OAUTH_CLIENT_ID'),
    'oauth_scopes' => array_values(array_filter(explode(' ', (string) env('PLATFORM_OAUTH_SCOPES', 'clinical:read')))),
    'asset_url' => rtrim((string) env('ASSET_URL', ''), '/'),
];
