<?php

$applicationEnvironment = (string) env('APP_ENV', 'production');
$localClientSecret = in_array($applicationEnvironment, ['local', 'testing'], true)
    ? hash('sha256', 'zigpaw-local-vets-bff-secret')
    : null;

return [
    'api_url' => rtrim((string) env('PLATFORM_API_URL', 'https://api.zigpaw.test'), '/'),
    'auth_url' => rtrim((string) env('PLATFORM_AUTH_URL', 'https://login.zigpaw.test'), '/'),
    'session_endpoint' => '/v1/vets/session',
    'oauth_client_id' => env('PLATFORM_OAUTH_CLIENT_ID'),
    'oauth_client_secret' => env('PLATFORM_OAUTH_CLIENT_SECRET', $localClientSecret),
    'oauth_scopes' => array_values(array_filter(explode(' ', (string) env('PLATFORM_OAUTH_SCOPES', 'clinical:read clinical:submit')))),
    'oauth_redirect_uri' => env('PLATFORM_OAUTH_REDIRECT_URI', rtrim((string) env('APP_URL', 'http://localhost'), '/').'/auth/callback'),
    'asset_url' => rtrim((string) env('ASSET_URL', ''), '/'),
];
