<?php

namespace Tests\Feature;

use App\Livewire\BusinessWorkspace;
use App\Support\PortalAccessTokenStore;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BusinessMutationHydrationRetryTest extends TestCase
{
    #[DataProvider('hydrationFailureStatuses')]
    public function test_successful_mutation_keeps_its_key_when_hydration_fails_and_reuses_it_after_session_regeneration(int $hydrationStatus): void
    {
        app(PortalAccessTokenStore::class)->put([
            'access_token' => 'manager-access-token-1',
            'refresh_token' => 'manager-refresh-token',
            'expires_in' => 900,
        ]);

        $keys = [];
        $offeringReads = 0;
        Http::fake(function (Request $request) use (&$keys, &$offeringReads, $hydrationStatus) {
            if ($request->url() === 'https://api.zigpaw.test/v1/business/organizations') {
                return Http::response(['data' => [[
                    'id' => 'organization-1',
                    'name' => 'Laidley Veterinary Surgery',
                    'role' => 'manager',
                ]]]);
            }

            if ($request->url() === 'https://api.zigpaw.test/v1/business/me') {
                return Http::response(['data' => [
                    'organization' => ['id' => 'organization-1', 'name' => 'Laidley Veterinary Surgery'],
                    'membership' => [
                        'id' => 'membership-1',
                        'role' => 'manager',
                        'capabilities' => ['portal.view', 'providers.manage'],
                    ],
                    'features' => ['provider_claims' => false, 'booking_requests' => false],
                ]]);
            }

            if ($request->method() === 'POST' && $request->url() === 'https://api.zigpaw.test/v1/business/offerings') {
                $keys[] = $request->header('Idempotency-Key')[0] ?? null;

                return Http::response(['data' => ['id' => 'offering-1']], count($keys) === 1 ? 201 : 200);
            }

            if ($request->method() === 'GET' && str_contains($request->url(), '/offerings')) {
                $offeringReads++;
                if ($offeringReads === 1) {
                    return Http::response(['message' => 'The workspace session has ended.'], $hydrationStatus);
                }

                return Http::response(self::page([]));
            }

            return Http::response(self::page([]));
        });

        Livewire::test(BusinessWorkspace::class)
            ->set('offeringProviderLinkId', 'provider-link-1')
            ->set('offeringName', 'Wellness consultation')
            ->call('createOffering');

        $this->assertCount(1, $keys);
        $this->assertNotNull($keys[0]);
        $this->assertCount(1, (array) session('portal.pending_mutations'));

        // OAuth callback legitimately regenerates the session ID. Retry context
        // must survive that migration without disabling session fixation defense.
        session()->regenerate();
        app(PortalAccessTokenStore::class)->put([
            'access_token' => 'manager-access-token-2',
            'refresh_token' => 'manager-refresh-token',
            'expires_in' => 900,
        ]);

        // Resolve a changed payload first: it must receive a new intent while
        // the original committed operation remains awaiting confirmation.
        Livewire::test(BusinessWorkspace::class)
            ->set('offeringProviderLinkId', 'provider-link-1')
            ->set('offeringName', 'Different consultation')
            ->call('createOffering')
            ->assertSee('Service saved as a draft. Review it before making it available to customers.');

        $this->assertCount(2, $keys);
        $this->assertNotSame($keys[0], $keys[1]);
        $this->assertCount(1, (array) session('portal.pending_mutations'));

        Livewire::test(BusinessWorkspace::class)
            ->set('offeringProviderLinkId', 'provider-link-1')
            ->set('offeringName', 'Wellness consultation')
            ->call('createOffering')
            ->assertSee('Service saved as a draft. Review it before making it available to customers.');

        $this->assertCount(3, $keys);
        $this->assertSame($keys[0], $keys[2]);
        $this->assertNull(session('portal.pending_mutations'));
    }

    public static function hydrationFailureStatuses(): array
    {
        return [[401], [403], [404], [422]];
    }

    public function test_pending_key_is_not_reused_after_membership_changes(): void
    {
        app(PortalAccessTokenStore::class)->put([
            'access_token' => 'manager-access-token-1',
            'refresh_token' => 'manager-refresh-token',
            'expires_in' => 900,
        ]);

        $keys = [];
        $meCalls = 0;
        $offeringReads = 0;
        Http::fake(function (Request $request) use (&$keys, &$meCalls, &$offeringReads) {
            if ($request->url() === 'https://api.zigpaw.test/v1/business/organizations') {
                return Http::response(['data' => [['id' => 'organization-1', 'name' => 'Laidley Veterinary Surgery', 'role' => 'manager']]]);
            }

            if ($request->url() === 'https://api.zigpaw.test/v1/business/me') {
                $meCalls++;

                return Http::response(['data' => [
                    'organization' => ['id' => 'organization-1', 'name' => 'Laidley Veterinary Surgery'],
                    'membership' => [
                        'id' => $meCalls === 1 ? 'membership-1' : 'membership-2',
                        'role' => 'manager',
                        'capabilities' => ['portal.view', 'providers.manage'],
                    ],
                    'features' => ['provider_claims' => false, 'booking_requests' => false],
                ]]);
            }

            if ($request->method() === 'POST' && $request->url() === 'https://api.zigpaw.test/v1/business/offerings') {
                $keys[] = $request->header('Idempotency-Key')[0] ?? null;

                return Http::response(['data' => ['id' => 'offering-1']], 201);
            }

            if ($request->method() === 'GET' && str_contains($request->url(), '/offerings')) {
                $offeringReads++;
                if ($offeringReads === 1) {
                    return Http::response(['message' => 'The workspace session has ended.'], 401);
                }

                return Http::response(self::page([]));
            }

            return Http::response(self::page([]));
        });

        Livewire::test(BusinessWorkspace::class)
            ->set('offeringProviderLinkId', 'provider-link-1')
            ->set('offeringName', 'Wellness consultation')
            ->call('createOffering');

        $this->assertCount(1, $keys);
        $this->assertCount(1, (array) session('portal.pending_mutations'));

        session()->regenerate();
        app(PortalAccessTokenStore::class)->put([
            'access_token' => 'manager-access-token-2',
            'refresh_token' => 'manager-refresh-token',
            'expires_in' => 900,
        ]);

        Livewire::test(BusinessWorkspace::class)
            ->set('offeringProviderLinkId', 'provider-link-1')
            ->set('offeringName', 'Wellness consultation')
            ->call('createOffering')
            ->assertSee('Service saved as a draft. Review it before making it available to customers.');

        $this->assertCount(2, $keys);
        $this->assertNotSame($keys[0], $keys[1]);
        $this->assertCount(1, (array) session('portal.pending_mutations'));
    }

    /** @param list<array<string, mixed>> $data @return array<string, mixed> */
    private static function page(array $data): array
    {
        return [
            'data' => $data,
            'links' => ['first' => null, 'last' => null, 'prev' => null, 'next' => null],
            'meta' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 25, 'total' => count($data)],
        ];
    }
}
