<?php

$localClientSecret = in_array(env('APP_ENV'), ['local', 'testing'], true)
    ? hash('sha256', 'zigpaw-local-business-bff-secret')
    : null;

return [
    'api_url' => rtrim((string) env('PLATFORM_API_URL', 'https://api.zigpaw.test'), '/'),
    'auth_url' => rtrim((string) env('PLATFORM_AUTH_URL', 'https://login.zigpaw.test'), '/'),
    'session_endpoint' => '/v1/business/session',
    'oauth_client_id' => env('PLATFORM_OAUTH_CLIENT_ID'),
    'oauth_client_secret' => env('PLATFORM_OAUTH_CLIENT_SECRET', $localClientSecret),
    'oauth_scopes' => array_values(array_filter(explode(' ', (string) env('PLATFORM_OAUTH_SCOPES', 'business:read business:profile:write business:providers:read business:providers:write business:bookings:read business:bookings:write business:programs:read business:programs:write business:financials:read business:team:read business:team:write')))),
    'oauth_redirect_uri' => env('PLATFORM_OAUTH_REDIRECT_URI', rtrim((string) env('APP_URL', 'http://localhost'), '/').'/auth/callback'),
    'asset_url' => rtrim((string) env('ASSET_URL', ''), '/'),
    'business_contract_source' => env('PLATFORM_BUSINESS_CONTRACT_SOURCE'),
    'clinical_contract_source' => env('PLATFORM_CLINICAL_CONTRACT_SOURCE'),
];
