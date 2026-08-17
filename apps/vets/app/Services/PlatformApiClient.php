<?php

namespace App\Services;

use App\Exceptions\PlatformApiException;
use App\Support\PlatformConfiguration;
use App\Support\RequestCorrelation;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use InvalidArgumentException;
use JsonException;

class PlatformApiClient
{
    /**
     * The BFF may only call this exact, versioned clinical API surface.
     *
     * @var array<string, list<string>>
     */
    private const ALLOWED_ROUTES = [
        'GET' => [
            '#^/v1/vets/organizations$#D',
            '#^/v1/vets/me$#D',
            '#^/v1/vets/dashboard$#D',
            '#^/v1/vets/provider-grants$#D',
            '#^/v1/vets/provider-grants/[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$#Di',
            '#^/v1/vets/provider-grants/[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}/care-context$#Di',
            '#^/v1/vets/provider-grants/[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}/media$#Di',
            '#^/v1/vets/provider-grants/[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}/media/[1-9][0-9]*$#Di',
            '#^/v1/vets/submissions$#D',
            '#^/v1/vets/submissions/[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$#Di',
        ],
        'POST' => [
            '#^/v1/vets/provider-grants/[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}/care-submissions$#Di',
        ],
    ];

    /** @return list<array<string, mixed>> */
    public function organizations(string $accessToken): array
    {
        return $this->listData(
            $this->requestJson('GET', '/v1/vets/organizations', $accessToken),
        );
    }

    /** @return array<string, mixed> */
    public function identity(string $accessToken, string $organizationId): array
    {
        return $this->resourceData(
            $this->requestJson('GET', '/v1/vets/me', $accessToken, $organizationId),
        );
    }

    /** @return array<string, mixed> */
    public function dashboard(string $accessToken, string $organizationId): array
    {
        return $this->resourceData(
            $this->requestJson('GET', '/v1/vets/dashboard', $accessToken, $organizationId),
        );
    }

    /**
     * @param  array{status?: string|null, search?: string|null, per_page?: int|null, page?: int|null}  $query
     * @return array{data: list<array<string, mixed>>, links: array<string, mixed>, meta: array<string, mixed>}
     */
    public function providerGrants(string $accessToken, string $organizationId, array $query = []): array
    {
        return $this->paginatedData(
            $this->requestJson(
                'GET',
                '/v1/vets/provider-grants',
                $accessToken,
                $organizationId,
                $this->providerGrantQuery($query),
            ),
        );
    }

    /** @return array<string, mixed> */
    public function providerGrant(string $accessToken, string $organizationId, string $grantId): array
    {
        return $this->resourceData(
            $this->requestJson(
                'GET',
                '/v1/vets/provider-grants/'.$this->uuid($grantId, 'provider grant'),
                $accessToken,
                $organizationId,
            ),
        );
    }

    /** @return array<string, mixed> */
    public function careContext(string $accessToken, string $organizationId, string $grantId): array
    {
        return $this->resourceData(
            $this->requestJson(
                'GET',
                '/v1/vets/provider-grants/'.$this->uuid($grantId, 'provider grant').'/care-context',
                $accessToken,
                $organizationId,
            ),
        );
    }

    /** @return list<array<string, mixed>> */
    public function providerMedia(string $accessToken, string $organizationId, string $grantId): array
    {
        return $this->listData(
            $this->requestJson(
                'GET',
                '/v1/vets/provider-grants/'.$this->uuid($grantId, 'provider grant').'/media',
                $accessToken,
                $organizationId,
            ),
        );
    }

    /**
     * Return the untouched upstream response so the BFF controller can proxy only
     * explicitly approved content headers and the binary body to the browser.
     */
    public function providerMediaDownload(
        string $accessToken,
        string $organizationId,
        string $grantId,
        int|string $mediaId,
    ): Response {
        $path = '/v1/vets/provider-grants/'
            .$this->uuid($grantId, 'provider grant')
            .'/media/'
            .$this->positiveInteger($mediaId, 'media');

        return $this->send('GET', $path, $accessToken, $organizationId);
    }

    /**
     * @param  array{status?: string|null, per_page?: int|null, page?: int|null}  $query
     * @return array{data: list<array<string, mixed>>, links: array<string, mixed>, meta: array<string, mixed>}
     */
    public function submissions(string $accessToken, string $organizationId, array $query = []): array
    {
        return $this->paginatedData(
            $this->requestJson(
                'GET',
                '/v1/vets/submissions',
                $accessToken,
                $organizationId,
                $this->submissionQuery($query),
            ),
        );
    }

    /** @return array<string, mixed> */
    public function submission(string $accessToken, string $organizationId, string $submissionId): array
    {
        return $this->resourceData(
            $this->requestJson(
                'GET',
                '/v1/vets/submissions/'.$this->uuid($submissionId, 'submission'),
                $accessToken,
                $organizationId,
            ),
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createCareSubmission(
        string $accessToken,
        string $organizationId,
        string $grantId,
        array $payload,
        string $idempotencyKey,
    ): array {
        return $this->resourceData(
            $this->requestJson(
                'POST',
                '/v1/vets/provider-grants/'.$this->uuid($grantId, 'provider grant').'/care-submissions',
                $accessToken,
                $organizationId,
                payload: $payload,
                idempotencyKey: $this->idempotencyKey($idempotencyKey),
            ),
        );
    }

    /**
     * @param  array<string, scalar>  $query
     * @param  array<string, mixed>|null  $payload
     * @return array<string, mixed>
     */
    private function requestJson(
        string $method,
        string $path,
        string $accessToken,
        ?string $organizationId = null,
        array $query = [],
        ?array $payload = null,
        ?string $idempotencyKey = null,
    ): array {
        $response = $this->send(
            $method,
            $path,
            $accessToken,
            $organizationId,
            $query,
            $payload,
            $idempotencyKey,
        );

        return $this->decodeJson($response);
    }

    /**
     * @param  array<string, scalar>  $query
     * @param  array<string, mixed>|null  $payload
     */
    private function send(
        string $method,
        string $path,
        string $accessToken,
        ?string $organizationId = null,
        array $query = [],
        ?array $payload = null,
        ?string $idempotencyKey = null,
    ): Response {
        $method = strtoupper($method);
        $this->assertAllowedRoute($method, $path);

        $request = $this->request($accessToken, $organizationId);
        if ($idempotencyKey !== null) {
            $request = $request->withHeader('Idempotency-Key', $idempotencyKey);
        }

        try {
            $response = match ($method) {
                'GET' => $request->get($path, $query),
                'POST' => $request->post($path, $payload ?? []),
                default => throw new InvalidArgumentException('Unsupported platform API method.'),
            };
        } catch (ConnectionException) {
            throw new PlatformApiException(
                503,
                'Zigpaw is unavailable right now. Please try again shortly.',
            );
        }

        if (! $response->successful()) {
            $this->throwForResponse($response);
        }

        return $response;
    }

    private function request(string $accessToken, ?string $organizationId): PendingRequest
    {
        if (! PlatformConfiguration::isSafe()) {
            throw new PlatformApiException(503, 'Zigpaw clinical is temporarily unavailable.');
        }

        $request = Http::baseUrl((string) config('platform.api_url'))
            ->acceptJson()
            ->withToken($this->headerValue($accessToken, 'access token'))
            ->withHeader('X-Request-ID', RequestCorrelation::id())
            ->connectTimeout(3)
            ->timeout(8);

        return $organizationId !== null
            ? $request->withHeader(
                'X-Zigpaw-Organization-ID',
                $this->headerValue($organizationId, 'organization identifier'),
            )
            : $request;
    }

    /** @return array<string, mixed> */
    private function decodeJson(Response $response): array
    {
        try {
            $decoded = $response->json();
        } catch (JsonException) {
            throw $this->malformedResponse($response);
        }

        if (! is_array($decoded)) {
            throw $this->malformedResponse($response);
        }

        return $decoded;
    }

    /** @return array<string, mixed> */
    private function resourceData(array $response): array
    {
        $data = $response['data'] ?? null;
        if (! is_array($data) || array_is_list($data)) {
            throw new PlatformApiException(502, 'Zigpaw returned an unexpected response.');
        }

        return $data;
    }

    /** @return list<array<string, mixed>> */
    private function listData(array $response): array
    {
        $data = $response['data'] ?? null;
        if (! is_array($data) || ! array_is_list($data)) {
            throw new PlatformApiException(502, 'Zigpaw returned an unexpected response.');
        }

        foreach ($data as $item) {
            if (! is_array($item) || array_is_list($item)) {
                throw new PlatformApiException(502, 'Zigpaw returned an unexpected response.');
            }
        }

        /** @var list<array<string, mixed>> $data */
        return $data;
    }

    /**
     * @return array{data: list<array<string, mixed>>, links: array<string, mixed>, meta: array<string, mixed>}
     */
    private function paginatedData(array $response): array
    {
        $data = $this->listData($response);
        $links = $response['links'] ?? [];
        $meta = $response['meta'] ?? [];

        if (! is_array($links) || ! is_array($meta)) {
            throw new PlatformApiException(502, 'Zigpaw returned an unexpected response.');
        }

        return [
            'data' => $data,
            'links' => $links,
            'meta' => $meta,
        ];
    }

    private function throwForResponse(Response $response): never
    {
        $decoded = $this->safeJson($response);
        $message = is_string($decoded['message'] ?? null) && trim($decoded['message']) !== ''
            ? trim($decoded['message'])
            : $this->fallbackMessage($response->status());

        if ($response->serverError()) {
            $message = $this->fallbackMessage($response->status());
        }

        throw new PlatformApiException(
            $response->status(),
            $message,
            $this->validationErrors($decoded['errors'] ?? null),
            RequestCorrelation::valid($response->header('X-Request-ID')),
        );
    }

    /** @return array<string, mixed> */
    private function safeJson(Response $response): array
    {
        try {
            $decoded = $response->json();
        } catch (JsonException) {
            return [];
        }

        return is_array($decoded) ? $decoded : [];
    }

    /** @return array<string, list<string>> */
    private function validationErrors(mixed $errors): array
    {
        if (! is_array($errors)) {
            return [];
        }

        $normalized = [];
        foreach ($errors as $field => $messages) {
            if (! is_string($field)) {
                continue;
            }

            if (is_string($messages)) {
                $normalized[$field] = [$messages];

                continue;
            }

            if (! is_array($messages)) {
                continue;
            }

            $normalizedMessages = array_values(array_filter(
                $messages,
                static fn (mixed $message): bool => is_string($message),
            ));

            if ($normalizedMessages !== []) {
                $normalized[$field] = $normalizedMessages;
            }
        }

        return $normalized;
    }

    private function fallbackMessage(int $status): string
    {
        return match ($status) {
            401 => 'Your Zigpaw session has expired. Please sign in again.',
            403 => 'You no longer have permission to complete that action.',
            404 => 'That clinical record is no longer available.',
            409 => 'That request conflicts with a recent change. Please refresh and try again.',
            422 => 'Please review the highlighted details and try again.',
            429 => 'Too many requests were made. Please wait a moment and try again.',
            default => 'Zigpaw is temporarily unavailable. Please try again shortly.',
        };
    }

    private function malformedResponse(Response $response): PlatformApiException
    {
        return new PlatformApiException(
            502,
            'Zigpaw returned an unexpected response.',
            requestId: RequestCorrelation::valid($response->header('X-Request-ID')),
        );
    }

    private function assertAllowedRoute(string $method, string $path): void
    {
        foreach (self::ALLOWED_ROUTES[$method] ?? [] as $pattern) {
            if (preg_match($pattern, $path) === 1) {
                return;
            }
        }

        throw new InvalidArgumentException('The requested platform API route is not allowed.');
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, scalar>
     */
    private function providerGrantQuery(array $query): array
    {
        $this->assertQueryKeys($query, ['status', 'search', 'per_page', 'page']);
        $normalized = [];

        if (isset($query['status'])) {
            if (! is_string($query['status']) || ! in_array($query['status'], ['active', 'expired', 'revoked', 'all'], true)) {
                throw new InvalidArgumentException('Invalid provider grant status filter.');
            }

            $normalized['status'] = $query['status'];
        }

        if (isset($query['search'])) {
            if (! is_string($query['search']) || mb_strlen($query['search']) > 80) {
                throw new InvalidArgumentException('Invalid provider grant search filter.');
            }

            $search = trim($query['search']);
            if ($search !== '') {
                $normalized['search'] = $search;
            }
        }

        foreach (['per_page' => 100, 'page' => PHP_INT_MAX] as $key => $maximum) {
            if (isset($query[$key])) {
                $normalized[$key] = $this->boundedPositiveInteger($query[$key], $key, $maximum);
            }
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, scalar>
     */
    private function submissionQuery(array $query): array
    {
        $this->assertQueryKeys($query, ['status', 'per_page', 'page']);
        $normalized = [];

        if (isset($query['status'])) {
            if (! is_string($query['status']) || ! in_array($query['status'], ['pending', 'approved', 'partially_approved', 'rejected'], true)) {
                throw new InvalidArgumentException('Invalid submission status filter.');
            }

            $normalized['status'] = $query['status'];
        }

        foreach (['per_page' => 100, 'page' => PHP_INT_MAX] as $key => $maximum) {
            if (isset($query[$key])) {
                $normalized[$key] = $this->boundedPositiveInteger($query[$key], $key, $maximum);
            }
        }

        return $normalized;
    }

    /** @param array<string, mixed> $query */
    private function assertQueryKeys(array $query, array $allowedKeys): void
    {
        $unknownKeys = array_diff(array_keys($query), $allowedKeys);
        if ($unknownKeys !== []) {
            throw new InvalidArgumentException('Unsupported platform API query parameter.');
        }
    }

    private function uuid(string $identifier, string $label): string
    {
        if (! Str::isUuid($identifier)) {
            throw new InvalidArgumentException("Invalid {$label} identifier.");
        }

        return strtolower($identifier);
    }

    private function positiveInteger(int|string $identifier, string $label): string
    {
        if ((! is_int($identifier) && preg_match('/^[1-9][0-9]*$/D', $identifier) !== 1)
            || (int) $identifier < 1) {
            throw new InvalidArgumentException("Invalid {$label} identifier.");
        }

        return (string) $identifier;
    }

    private function boundedPositiveInteger(mixed $value, string $label, int $maximum): int
    {
        if (! is_int($value) || $value < 1 || $value > $maximum) {
            throw new InvalidArgumentException("Invalid {$label} query parameter.");
        }

        return $value;
    }

    private function headerValue(string $value, string $label): string
    {
        if ($value === '' || mb_strlen($value) > 4096 || preg_match('/[\r\n]/', $value) === 1) {
            throw new InvalidArgumentException("Invalid {$label}.");
        }

        return $value;
    }

    private function idempotencyKey(string $key): string
    {
        if (mb_strlen($key) < 8
            || mb_strlen($key) > 255
            || preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]*$/D', $key) !== 1) {
            throw new InvalidArgumentException('Invalid idempotency key.');
        }

        return $key;
    }
}
