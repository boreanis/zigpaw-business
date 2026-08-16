<?php

namespace App\Services;

use App\Exceptions\PlatformApiException;
use App\Support\RequestCorrelation;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

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
    public function updateBusinessProfile(string $accessToken, string $organizationId, array $payload): array
    {
        return $this->mutate('PATCH', '/v1/business/me', $accessToken, $organizationId, $payload);
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
    public function submitProviderClaim(string $accessToken, string $organizationId, array $payload): array
    {
        return $this->mutate('POST', '/v1/business/provider-claims', $accessToken, $organizationId, $payload);
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

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    public function createOffering(string $accessToken, string $organizationId, array $payload): array
    {
        return $this->mutate('POST', '/v1/business/offerings', $accessToken, $organizationId, $payload);
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    public function updateManagedProvider(
        string $accessToken,
        string $organizationId,
        string $providerLinkId,
        array $payload,
    ): array {
        return $this->mutate('PATCH', "/v1/business/providers/{$providerLinkId}", $accessToken, $organizationId, $payload);
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    public function updateBookingProfile(string $accessToken, string $organizationId, array $payload): array
    {
        return $this->mutate('PUT', '/v1/business/booking-profiles', $accessToken, $organizationId, $payload);
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    public function updateOffering(
        string $accessToken,
        string $organizationId,
        string $offeringId,
        array $payload,
    ): array {
        return $this->mutate('PATCH', "/v1/business/offerings/{$offeringId}", $accessToken, $organizationId, $payload);
    }

    public function deleteOffering(string $accessToken, string $organizationId, string $offeringId): void
    {
        $this->mutateWithoutContent('DELETE', "/v1/business/offerings/{$offeringId}", $accessToken, $organizationId);
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    public function respondToBooking(string $accessToken, string $organizationId, string $bookingId, array $payload): array
    {
        return $this->mutate('POST', "/v1/business/bookings/{$bookingId}/response", $accessToken, $organizationId, $payload);
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    public function applyForProgram(string $accessToken, string $organizationId, array $payload): array
    {
        return $this->mutate('POST', '/v1/business/programs', $accessToken, $organizationId, $payload);
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    public function inviteTeamMember(string $accessToken, string $organizationId, array $payload): array
    {
        return $this->mutate('POST', '/v1/business/team/invitations', $accessToken, $organizationId, $payload);
    }

    /** @return array<string, mixed> */
    public function resendTeamInvitation(
        string $accessToken,
        string $organizationId,
        string $membershipId,
    ): array {
        return $this->mutate('POST', "/v1/business/team/{$membershipId}/resend", $accessToken, $organizationId, []);
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    public function updateTeamMember(
        string $accessToken,
        string $organizationId,
        string $membershipId,
        array $payload,
    ): array {
        return $this->mutate('PATCH', "/v1/business/team/{$membershipId}", $accessToken, $organizationId, $payload);
    }

    /** @return array<string, mixed> */
    public function revokeTeamMember(
        string $accessToken,
        string $organizationId,
        string $membershipId,
    ): array {
        return $this->mutate('DELETE', "/v1/business/team/{$membershipId}", $accessToken, $organizationId, []);
    }

    /** @return array<string, mixed> */
    private function get(string $path, string $accessToken, ?string $organizationId = null): array
    {
        return $this->data($this->getResponse($path, $accessToken, $organizationId));
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
    ): array {
        try {
            $response = $this->request($accessToken, $organizationId)
                ->withHeader('Idempotency-Key', (string) Str::uuid())
                ->send($method, $path, ['json' => $payload]);
        } catch (ConnectionException) {
            throw new PlatformApiException(0, 'Zigpaw is unavailable right now. Please try again shortly.');
        }

        return $this->data($response);
    }

    private function mutateWithoutContent(
        string $method,
        string $path,
        string $accessToken,
        string $organizationId,
    ): void {
        try {
            $response = $this->request($accessToken, $organizationId)
                ->withHeader('Idempotency-Key', (string) Str::uuid())
                ->send($method, $path);
        } catch (ConnectionException) {
            throw new PlatformApiException(0, 'Zigpaw is unavailable right now. Please try again shortly.');
        }

        if (! $response->successful()) {
            $this->data($response);
        }
    }

    /** @return array<string, mixed> */
    private function data(Response $response): array
    {
        if (! $response->successful()) {
            $validationMessage = collect((array) $response->json('errors'))
                ->flatten()
                ->first(fn (mixed $message): bool => is_string($message) && $message !== '');

            throw new PlatformApiException(
                $response->status(),
                (string) ($validationMessage
                    ?: $response->json('detail')
                    ?: $response->json('message')
                    ?: 'Zigpaw could not complete that request.'),
            );
        }

        $data = $response->json('data');
        if (! is_array($data)) {
            throw new PlatformApiException(502, 'Zigpaw returned an unexpected response.');
        }

        return $data;
    }

    private function getResponse(string $path, string $accessToken, ?string $organizationId = null): Response
    {
        try {
            return $this->request($accessToken, $organizationId)->get($path);
        } catch (ConnectionException) {
            throw new PlatformApiException(0, 'Zigpaw is unavailable right now. Please try again shortly.');
        }
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    private function request(string $accessToken, ?string $organizationId): PendingRequest
    {
        $request = Http::baseUrl((string) config('platform.api_url'))
            ->acceptJson()
            ->withToken($accessToken)
            ->withHeader('X-Request-ID', RequestCorrelation::id())
            ->connectTimeout(3)
            ->timeout(8);

        return $organizationId ? $request->withHeader('X-Zigpaw-Organization-ID', $organizationId) : $request;
    }
}
