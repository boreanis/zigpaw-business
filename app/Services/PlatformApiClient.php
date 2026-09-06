<?php

namespace App\Services;

use App\Exceptions\PlatformApiException;
use App\Support\ClinicalPortalAccessTokenStore;
use App\Support\Generated\BusinessApiOperations;
use App\Support\PlatformConfiguration;
use App\Support\RequestCorrelation;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * @phpstan-type PageMeta array{current_page: int, last_page: int, per_page: int, total: int}
 * @phpstan-type PageLinks array{first: ?string, last: ?string, prev: ?string, next: ?string}
 * @phpstan-type PageEnvelope array{data: list<array<string, mixed>>, meta: PageMeta, links: PageLinks}
 */
class PlatformApiClient
{
    /** @return list<array<string, mixed>> */
    public function organizations(string $accessToken): array
    {
        return array_values($this->get('/v1/business/organizations', $accessToken));
    }

    /** @return array<string, mixed> */
    public function identity(string $accessToken, string $organizationId): array
    {
        return $this->get('/v1/business/me', $accessToken, $organizationId);
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    public function updateBusinessProfile(string $accessToken, string $organizationId, array $payload, ?string $idempotencyKey = null): array
    {
        return $this->mutate('PATCH', '/v1/business/me', $accessToken, $organizationId, $payload, $idempotencyKey);
    }

    /** @return PageEnvelope */
    public function providers(string $accessToken, string $organizationId, int $page = 1, int $perPage = 25): array
    {
        return $this->getPage('/v1/business/providers', $accessToken, $organizationId, $page, $perPage);
    }

    /** @return PageEnvelope */
    public function providerClaims(string $accessToken, string $organizationId, int $page = 1, int $perPage = 25): array
    {
        return $this->getPage('/v1/business/provider-claims', $accessToken, $organizationId, $page, $perPage);
    }

    /** @return PageEnvelope */
    public function claimableProviders(
        string $accessToken,
        string $organizationId,
        string $query,
        int $page = 1,
        int $perPage = 10,
    ): array {
        return $this->getPage(
            '/v1/business/provider-claims/discovery',
            $accessToken,
            $organizationId,
            $page,
            $perPage,
            ['q' => $query],
        );
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    public function submitProviderClaim(string $accessToken, string $organizationId, array $payload, ?string $idempotencyKey = null): array
    {
        return $this->mutate('POST', '/v1/business/provider-claims', $accessToken, $organizationId, $payload, $idempotencyKey);
    }

    /** @return PageEnvelope */
    public function offerings(string $accessToken, string $organizationId, int $page = 1, int $perPage = 25): array
    {
        return $this->getPage('/v1/business/offerings', $accessToken, $organizationId, $page, $perPage);
    }

    /** @return PageEnvelope */
    public function bookingProfiles(string $accessToken, string $organizationId, int $page = 1, int $perPage = 25): array
    {
        return $this->getPage('/v1/business/booking-profiles', $accessToken, $organizationId, $page, $perPage);
    }

    /** @return PageEnvelope */
    public function bookings(string $accessToken, string $organizationId, int $page = 1, int $perPage = 25): array
    {
        return $this->getPage('/v1/business/bookings', $accessToken, $organizationId, $page, $perPage);
    }

    /** @return array<string, mixed> */
    public function bookingPetContext(string $accessToken, string $organizationId, string $bookingId): array
    {
        $bookingId = $this->pathSegment($bookingId, 'booking');

        return $this->get("/v1/business/bookings/{$bookingId}/pet-context", $accessToken, $organizationId);
    }

    /** @return array<string, mixed> */
    public function financials(string $accessToken, string $organizationId): array
    {
        return $this->get('/v1/business/financials', $accessToken, $organizationId);
    }

    /** @return PageEnvelope */
    public function commissions(string $accessToken, string $organizationId, int $page = 1, int $perPage = 25): array
    {
        return $this->getPage('/v1/business/financials/commissions', $accessToken, $organizationId, $page, $perPage);
    }

    /** @return PageEnvelope */
    public function agreements(string $accessToken, string $organizationId, int $page = 1, int $perPage = 25): array
    {
        return $this->getPage('/v1/business/financials/agreements', $accessToken, $organizationId, $page, $perPage);
    }

    /** @return PageEnvelope */
    public function programs(string $accessToken, string $organizationId, int $page = 1, int $perPage = 25): array
    {
        return $this->getPage('/v1/business/programs', $accessToken, $organizationId, $page, $perPage);
    }

    /** @return PageEnvelope */
    public function team(string $accessToken, string $organizationId, int $page = 1, int $perPage = 25): array
    {
        return $this->getPage('/v1/business/team', $accessToken, $organizationId, $page, $perPage);
    }

    /** @return list<array<string, mixed>> */
    public function clinicalOrganizations(ClinicalPortalAccessTokenStore $tokens): array
    {
        return array_values($this->get('/v1/business/clinical/organizations', $this->clinicalToken($tokens)));
    }

    /** @return array<string, mixed> */
    public function clinicalIdentity(ClinicalPortalAccessTokenStore $tokens, string $organizationId): array
    {
        return $this->get('/v1/business/clinical/me', $this->clinicalToken($tokens), $organizationId);
    }

    /** @return array<string, mixed> */
    public function clinicalDashboard(ClinicalPortalAccessTokenStore $tokens, string $organizationId): array
    {
        return $this->get('/v1/business/clinical/dashboard', $this->clinicalToken($tokens), $organizationId);
    }

    /** @return PageEnvelope */
    public function clinicalProviderGrants(
        ClinicalPortalAccessTokenStore $tokens,
        string $organizationId,
        int $page = 1,
        int $perPage = 25,
        ?string $status = null,
        ?string $search = null,
    ): array {
        $parameters = array_filter([
            'status' => $status,
            'search' => $search === null ? null : trim($search),
        ], static fn (mixed $value): bool => $value !== null && $value !== '');

        return $this->getPage('/v1/business/clinical/provider-grants', $this->clinicalToken($tokens), $organizationId, $page, $perPage, $parameters);
    }

    /** @return array<string, mixed> */
    public function clinicalProviderGrant(ClinicalPortalAccessTokenStore $tokens, string $organizationId, string $grantId): array
    {
        $grantId = $this->uuid($grantId, 'provider grant');

        return $this->get("/v1/business/clinical/provider-grants/{$grantId}", $this->clinicalToken($tokens), $organizationId);
    }

    /** @return array<string, mixed> */
    public function clinicalCareContext(ClinicalPortalAccessTokenStore $tokens, string $organizationId, string $grantId): array
    {
        $grantId = $this->uuid($grantId, 'provider grant');

        return $this->get("/v1/business/clinical/provider-grants/{$grantId}/care-context", $this->clinicalToken($tokens), $organizationId);
    }

    /** @return list<array<string, mixed>> */
    public function clinicalProviderMedia(ClinicalPortalAccessTokenStore $tokens, string $organizationId, string $grantId): array
    {
        $grantId = $this->uuid($grantId, 'provider grant');

        return array_values($this->get("/v1/business/clinical/provider-grants/{$grantId}/media", $this->clinicalToken($tokens), $organizationId));
    }

    public function clinicalProviderMediaDownload(
        ClinicalPortalAccessTokenStore $tokens,
        string $organizationId,
        string $grantId,
        int|string $mediaId,
    ): Response {
        $grantId = $this->uuid($grantId, 'provider grant');
        $mediaId = $this->positiveInteger($mediaId, 'media');

        return $this->getResponse(
            "/v1/business/clinical/provider-grants/{$grantId}/media/{$mediaId}",
            $this->clinicalToken($tokens),
            $organizationId,
        );
    }

    /** @return PageEnvelope */
    public function clinicalSubmissions(
        ClinicalPortalAccessTokenStore $tokens,
        string $organizationId,
        int $page = 1,
        int $perPage = 25,
        ?string $status = null,
    ): array {
        return $this->getPage(
            '/v1/business/clinical/submissions',
            $this->clinicalToken($tokens),
            $organizationId,
            $page,
            $perPage,
            $status === null ? [] : ['status' => $status],
        );
    }

    /** @return array<string, mixed> */
    public function clinicalSubmission(ClinicalPortalAccessTokenStore $tokens, string $organizationId, string $submissionId): array
    {
        $submissionId = $this->uuid($submissionId, 'submission');

        return $this->get("/v1/business/clinical/submissions/{$submissionId}", $this->clinicalToken($tokens), $organizationId);
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    public function createClinicalCareSubmission(
        ClinicalPortalAccessTokenStore $tokens,
        string $organizationId,
        string $grantId,
        array $payload,
        string $idempotencyKey,
    ): array {
        $grantId = $this->uuid($grantId, 'provider grant');

        return $this->mutateWithKey(
            'POST',
            "/v1/business/clinical/provider-grants/{$grantId}/care-submissions",
            $this->clinicalToken($tokens),
            $organizationId,
            $payload,
            $idempotencyKey,
        );
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    public function createOffering(string $accessToken, string $organizationId, array $payload, ?string $idempotencyKey = null): array
    {
        return $this->mutate('POST', '/v1/business/offerings', $accessToken, $organizationId, $payload, $idempotencyKey);
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    public function updateManagedProvider(
        string $accessToken,
        string $organizationId,
        string $providerLinkId,
        array $payload,
        ?string $idempotencyKey = null,
    ): array {
        $providerLinkId = $this->pathSegment($providerLinkId, 'provider link');

        return $this->mutate('PATCH', "/v1/business/providers/{$providerLinkId}", $accessToken, $organizationId, $payload, $idempotencyKey);
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    public function updateBookingProfile(string $accessToken, string $organizationId, array $payload, ?string $idempotencyKey = null): array
    {
        return $this->mutate('PUT', '/v1/business/booking-profiles', $accessToken, $organizationId, $payload, $idempotencyKey);
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    public function updateOffering(
        string $accessToken,
        string $organizationId,
        string $offeringId,
        array $payload,
        ?string $idempotencyKey = null,
    ): array {
        $offeringId = $this->pathSegment($offeringId, 'offering');

        return $this->mutate('PATCH', "/v1/business/offerings/{$offeringId}", $accessToken, $organizationId, $payload, $idempotencyKey);
    }

    public function deleteOffering(string $accessToken, string $organizationId, string $offeringId, ?string $idempotencyKey = null): void
    {
        $offeringId = $this->pathSegment($offeringId, 'offering');

        $this->mutateWithoutContent('DELETE', "/v1/business/offerings/{$offeringId}", $accessToken, $organizationId, $idempotencyKey);
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    public function respondToBooking(string $accessToken, string $organizationId, string $bookingId, array $payload, ?string $idempotencyKey = null): array
    {
        $bookingId = $this->pathSegment($bookingId, 'booking');

        return $this->mutate('POST', "/v1/business/bookings/{$bookingId}/response", $accessToken, $organizationId, $payload, $idempotencyKey);
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    public function applyForProgram(string $accessToken, string $organizationId, array $payload, ?string $idempotencyKey = null): array
    {
        return $this->mutate('POST', '/v1/business/programs', $accessToken, $organizationId, $payload, $idempotencyKey);
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    public function inviteTeamMember(string $accessToken, string $organizationId, array $payload, ?string $idempotencyKey = null): array
    {
        return $this->mutate('POST', '/v1/business/team/invitations', $accessToken, $organizationId, $payload, $idempotencyKey);
    }

    /** @return array<string, mixed> */
    public function resendTeamInvitation(
        string $accessToken,
        string $organizationId,
        string $membershipId,
        ?string $idempotencyKey = null,
    ): array {
        $membershipId = $this->pathSegment($membershipId, 'membership');

        return $this->mutate('POST', "/v1/business/team/invitations/{$membershipId}/resend", $accessToken, $organizationId, [], $idempotencyKey);
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    public function updateTeamMember(
        string $accessToken,
        string $organizationId,
        string $membershipId,
        array $payload,
        ?string $idempotencyKey = null,
    ): array {
        $membershipId = $this->pathSegment($membershipId, 'membership');

        return $this->mutate('PATCH', "/v1/business/team/memberships/{$membershipId}", $accessToken, $organizationId, $payload, $idempotencyKey);
    }

    /** @return array<string, mixed> */
    public function revokeTeamMember(
        string $accessToken,
        string $organizationId,
        string $membershipId,
        ?string $idempotencyKey = null,
    ): array {
        $membershipId = $this->pathSegment($membershipId, 'membership');

        return $this->mutate('DELETE', "/v1/business/team/memberships/{$membershipId}", $accessToken, $organizationId, [], $idempotencyKey);
    }

    /** @return array<string, mixed> */
    private function get(string $path, string $accessToken, ?string $organizationId = null): array
    {
        return $this->data($this->getResponse($path, $accessToken, $organizationId));
    }

    private function clinicalToken(ClinicalPortalAccessTokenStore $tokens): string
    {
        $token = $tokens->accessToken();

        if (! is_string($token) || $token === '') {
            throw new PlatformApiException(401, 'Your clinical workspace session has ended. Please sign in again.');
        }

        return $token;
    }

    /** @return PageEnvelope */
    private function getPage(
        string $path,
        string $accessToken,
        string $organizationId,
        int $page,
        int $perPage,
        array $parameters = [],
    ): array {
        $query = http_build_query([...$parameters, ...[
            'page' => max(1, $page),
            'per_page' => min(100, max(1, $perPage)),
        ]]);
        $response = $this->getResponse("{$path}?{$query}", $accessToken, $organizationId);
        $data = $this->data($response);
        $meta = $response->json('meta');
        $links = $response->json('links');

        return [
            'data' => array_values($data),
            'meta' => [
                'current_page' => max(1, (int) data_get($meta, 'current_page', 1)),
                'last_page' => max(1, (int) data_get($meta, 'last_page', 1)),
                'per_page' => max(1, (int) data_get($meta, 'per_page', count($data) ?: $perPage)),
                'total' => max(0, (int) data_get($meta, 'total', count($data))),
            ],
            'links' => [
                'first' => $this->nullableString(data_get($links, 'first')),
                'last' => $this->nullableString(data_get($links, 'last')),
                'prev' => $this->nullableString(data_get($links, 'prev')),
                'next' => $this->nullableString(data_get($links, 'next')),
            ],
        ];
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    private function mutate(
        string $method,
        string $path,
        string $accessToken,
        string $organizationId,
        array $payload,
        ?string $idempotencyKey = null,
    ): array {
        $this->assertAllowedRoute($method, $path);

        try {
            $response = $this->request($accessToken, $organizationId, $path)
                ->withHeader('Idempotency-Key', $idempotencyKey === null ? (string) Str::uuid() : $this->idempotencyKey($idempotencyKey))
                ->send($method, $path, ['json' => $payload]);
        } catch (ConnectionException) {
            throw new PlatformApiException(503, 'Zigpaw is unavailable right now. Please try again shortly.');
        }

        return $this->data($response);
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    private function mutateWithKey(
        string $method,
        string $path,
        string $accessToken,
        string $organizationId,
        array $payload,
        string $idempotencyKey,
    ): array {
        $this->assertAllowedRoute($method, $path);
        $idempotencyKey = $this->idempotencyKey($idempotencyKey);

        try {
            $response = $this->request($accessToken, $organizationId, $path)
                ->withHeader('Idempotency-Key', $idempotencyKey)
                ->send($method, $path, ['json' => $payload]);
        } catch (ConnectionException) {
            throw new PlatformApiException(503, 'Zigpaw is unavailable right now. Please try again shortly.');
        }

        return $this->data($response);
    }

    private function mutateWithoutContent(
        string $method,
        string $path,
        string $accessToken,
        string $organizationId,
        ?string $idempotencyKey = null,
    ): void {
        $this->assertAllowedRoute($method, $path);

        try {
            $response = $this->request($accessToken, $organizationId, $path)
                ->withHeader('Idempotency-Key', $idempotencyKey === null ? (string) Str::uuid() : $this->idempotencyKey($idempotencyKey))
                ->send($method, $path);
        } catch (ConnectionException) {
            throw new PlatformApiException(503, 'Zigpaw is unavailable right now. Please try again shortly.');
        }

        if (! $response->successful()) {
            $this->data($response);
        }
    }

    /** @return array<string, mixed> */
    private function data(Response $response): array
    {
        if (! $response->successful()) {
            $decoded = $this->safeJson($response);
            $errors = $this->validationErrors($decoded['errors'] ?? null);
            $validationMessage = collect($errors)
                ->flatten()
                ->first(fn (mixed $message): bool => is_string($message) && $message !== '');
            $message = is_string($decoded['message'] ?? null) && trim($decoded['message']) !== ''
                ? trim($decoded['message'])
                : (is_string($decoded['detail'] ?? null) && trim($decoded['detail']) !== ''
                    ? trim($decoded['detail'])
                    : ($validationMessage ?: 'Zigpaw could not complete that request.'));

            if ($response->serverError()) {
                $message = 'Zigpaw is temporarily unavailable. Please try again shortly.';
            }

            throw new PlatformApiException(
                $response->status(),
                (string) $message,
                $errors,
                RequestCorrelation::valid($response->header('X-Request-ID')),
                is_string(data_get($decoded, 'error.code')) && trim((string) data_get($decoded, 'error.code')) !== ''
                    ? trim((string) data_get($decoded, 'error.code'))
                    : (is_string($decoded['code'] ?? null) && trim($decoded['code']) !== '' ? trim($decoded['code']) : null),
            );
        }

        $decoded = $this->safeJson($response);
        $data = $decoded['data'] ?? null;
        if (! is_array($data)) {
            throw new PlatformApiException(
                502,
                'Zigpaw returned an unexpected response.',
                requestId: RequestCorrelation::valid($response->header('X-Request-ID')),
            );
        }

        return $data;
    }

    private function getResponse(string $path, string $accessToken, ?string $organizationId = null): Response
    {
        $this->assertAllowedRoute('GET', $path);

        try {
            return $this->request($accessToken, $organizationId, $path)->get($path);
        } catch (ConnectionException) {
            throw new PlatformApiException(503, 'Zigpaw is unavailable right now. Please try again shortly.');
        }
    }

    private function assertAllowedRoute(string $method, string $path): void
    {
        if (! BusinessApiOperations::allows($method, $path)) {
            throw new InvalidArgumentException('The requested platform API route is not allowed.');
        }

        $context = $this->contextForPath($path);

        if (! PlatformConfiguration::isSafe($context)) {
            throw new PlatformApiException(
                503,
                $context === PlatformConfiguration::CLINICAL
                    ? 'Zigpaw clinical is temporarily unavailable.'
                    : 'Zigpaw is temporarily unavailable.',
            );
        }
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    private function request(string $accessToken, ?string $organizationId, string $path): PendingRequest
    {
        $configPrefix = $this->contextForPath($path) === PlatformConfiguration::CLINICAL
            ? 'platform_clinical'
            : 'platform';
        $request = Http::baseUrl((string) config("{$configPrefix}.api_url"))
            ->acceptJson()
            ->withToken($this->headerValue($accessToken, 'access token'))
            ->withHeader('X-Request-ID', RequestCorrelation::id())
            ->connectTimeout(3)
            ->timeout(8);

        return $organizationId
            ? $request->withHeader('X-Zigpaw-Organization-ID', $this->pathSegment($organizationId, 'organization'))
            : $request;
    }

    private function contextForPath(string $path): string
    {
        return str_starts_with($path, '/v1/business/clinical/')
            ? PlatformConfiguration::CLINICAL
            : PlatformConfiguration::BUSINESS;
    }

    /** @return array<string, mixed> */
    private function safeJson(Response $response): array
    {
        try {
            $decoded = $response->json();
        } catch (\JsonException) {
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

    private function headerValue(string $value, string $label): string
    {
        if ($value === '' || mb_strlen($value) > 4096 || preg_match('/[\r\n]/', $value) === 1) {
            throw new InvalidArgumentException("Invalid {$label}.");
        }

        return $value;
    }

    /**
     * IDs originate in Livewire actions and therefore must be treated as
     * untrusted input before they become URL path segments. Keep this
     * deliberately stricter than header validation: API resource identifiers
     * are opaque, but they never need slashes, query delimiters, whitespace or
     * percent-encoding.
     */
    private function pathSegment(string $identifier, string $label): string
    {
        if (mb_strlen($identifier) > 255
            || preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]*$/D', $identifier) !== 1) {
            throw new InvalidArgumentException("Invalid {$label} identifier.");
        }

        return $identifier;
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
