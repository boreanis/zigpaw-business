<?php

$localClientSecret = in_array(env('APP_ENV'), ['local', 'testing'], true)
    ? hash('sha256', 'zigpaw-local-business-clinical-bff-secret')
    : null;

return [
    'api_url' => rtrim((string) env('PLATFORM_API_URL', 'https://api.zigpaw.test'), '/'),
    'auth_url' => rtrim((string) env('PLATFORM_AUTH_URL', 'https://login.zigpaw.test'), '/'),
    'session_endpoint' => '/v1/business/clinical/session',
    'oauth_client_id' => env('PLATFORM_CLINICAL_OAUTH_CLIENT_ID'),
    'oauth_client_secret' => env('PLATFORM_CLINICAL_OAUTH_CLIENT_SECRET', $localClientSecret),
    'oauth_scopes' => array_values(array_filter(explode(' ', (string) env('PLATFORM_CLINICAL_OAUTH_SCOPES', 'clinical:read clinical:submit')))),
    'oauth_redirect_uri' => env('PLATFORM_CLINICAL_OAUTH_REDIRECT_URI', rtrim((string) env('APP_URL', 'http://localhost'), '/').'/clinical/auth/callback'),
];
