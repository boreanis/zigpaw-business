<?php

namespace Tests\Feature;

use App\Livewire\BusinessWorkspace;
use App\Livewire\Clinical\CareSubmission;
use App\Livewire\Clinical\PatientIndex;
use App\Livewire\ClinicalDashboard;
use App\Services\PlatformApiClient;
use App\Support\ClinicalPortalAccessTokenStore;
use App\Support\PortalAccessTokenStore;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ClinicalWorkspaceTest extends TestCase
{
    public function test_clinical_organization_selector_reaches_later_pages_and_recovers_the_selected_record(): void
    {
        $this->signIn();
        session()->forget(['clinical_portal.organization_id', 'clinical_portal.organization_name']);
        Http::fake(function (Request $request) {
            $path = parse_url($request->url(), PHP_URL_PATH);
            if ($path === '/v1/business/clinical/organizations') {
                $id = (int) ($request['page'] ?? 1) === 2 ? 'organization-27' : 'organization-1';

                return Http::response([
                    'data' => [['id' => $id, 'name' => 'Synthetic clinic '.$id, 'role' => 'owner']],
                    'meta' => ['current_page' => (int) ($request['page'] ?? 1), 'last_page' => 2, 'per_page' => 25, 'total' => 27],
                    'links' => $this->pageLinks(),
                ]);
            }
            if ($path === '/v1/business/clinical/organizations/organization-27') {
                return Http::response(['data' => ['id' => 'organization-27', 'name' => 'Synthetic selected clinic', 'role' => 'owner']]);
            }
            if ($path === '/v1/business/clinical/me') {
                return Http::response(['data' => ['organization' => ['id' => 'organization-27', 'name' => 'Synthetic selected clinic']]]);
            }

            return Http::response(['data' => []]);
        });

        Livewire::test(ClinicalDashboard::class)
            ->assertSet('state', 'choose_organization')
            ->call('changePage', 'organizations', 2)
            ->assertSee('Synthetic clinic organization-27')
            ->call('selectOrganization', 'organization-27')
            ->assertSet('state', 'ready')
            ->assertSet('identity.organization.id', 'organization-27')
            ->assertRedirect(route('clinical.dashboard'));
        $this->get('/clinical')->assertOk()->assertSee('Synthetic selected clinic');
        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/organizations/organization-27')
            && $request->hasHeader('Authorization', 'Bearer clinical-access-token'));
        $this->assertNull(app(PortalAccessTokenStore::class)->accessToken());
    }

    public function test_clinical_dashboard_contains_every_conditional_morph_marker_inside_its_persistent_root(): void
    {
        $this->signIn();
        session()->forget(['clinical_portal.organization_id', 'clinical_portal.organization_name']);
        Http::fake(function (Request $request) {
            $path = parse_url($request->url(), PHP_URL_PATH);
            if ($path === '/v1/business/clinical/organizations') {
                $items = collect(range(1, 27))->map(fn (int $number): array => [
                    'id' => 'organization-'.$number, 'name' => 'Synthetic clinic '.str_pad((string) $number, 2, '0', STR_PAD_LEFT), 'role' => 'owner',
                ])->filter(fn (array $item): bool => str_contains(strtolower($item['name']), strtolower((string) ($request['search'] ?? ''))))->values();
                $page = max(1, (int) ($request['page'] ?? 1));

                return Http::response(['data' => $items->slice(($page - 1) * 25, 25)->values()->all(),
                    'meta' => ['current_page' => $page, 'last_page' => max(1, (int) ceil($items->count() / 25)), 'per_page' => 25, 'total' => $items->count()],
                    'links' => $this->pageLinks()]);
            }
            if ($path === '/v1/business/clinical/organizations/organization-27') {
                return Http::response(['data' => ['id' => 'organization-27', 'name' => 'Synthetic clinic 27', 'role' => 'owner']]);
            }
            if ($path === '/v1/business/clinical/me') {
                return Http::response(['data' => ['organization' => ['id' => 'organization-27', 'name' => 'Synthetic clinic 27']]]);
            }

            return Http::response(['data' => []]);
        });
        $component = Livewire::test(ClinicalDashboard::class)->assertSet('state', 'choose_organization')
            ->assertSee('Page 1 of 2')->assertSet('pagination.organizations.total', 27);
        $id = $component->id();
        $this->assertClinicalMorphRoot($component, $id, initial: true);
        $component->call('changePage', 'organizations', 2)->assertSet('pagination.organizations.current_page', 2)
            ->assertSee('Synthetic clinic 26')->assertSee('Synthetic clinic 27')->assertDontSee('Synthetic clinic 01');
        $this->assertClinicalMorphRoot($component, $id);
        $component->set('organizationSearch', 'No matching QA organisation')->call('searchOrganizations')
            ->assertSet('organizations', [])->assertSet('pagination.organizations.current_page', 1)
            ->assertSee('No matching organisations.');
        $this->assertClinicalMorphRoot($component, $id);
        $component->set('organizationSearch', 'Synthetic clinic 27')->call('searchOrganizations')
            ->assertSet('pagination.organizations.total', 1)->assertSee('Synthetic clinic 27')->assertDontSee('Synthetic clinic 01');
        $this->assertClinicalMorphRoot($component, $id);
        $component->call('selectOrganization', 'organization-27')->assertSet('state', 'ready')
            ->assertSet('identity.organization.id', 'organization-27')->assertRedirect(route('clinical.dashboard'));
        $effects = $component->__get('effects');
        self::assertIsArray($effects);
        self::assertArrayNotHasKey('html', $effects, 'A full redirect rebuilds the layout instead of morphing the chooser.');
        $this->get('/clinical')->assertOk()->assertSee('Clinical overview')->assertSee('class="clinical-nav"', false);
        self::assertSame('organization-27', session('clinical_portal.organization_id'));
        self::assertNull(app(PortalAccessTokenStore::class)->accessToken());
        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/organizations/organization-27')
            && $request->hasHeader('Authorization', 'Bearer clinical-access-token'));
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'search=No+matching+QA+organisation'));
    }

    public function test_clinical_selection_redirects_to_a_fresh_authorized_layout_without_changing_management_context(): void
    {
        $this->signIn();
        session()->forget(['clinical_portal.organization_id', 'clinical_portal.organization_name']);
        session()->put(['portal.organization_id' => 'management-a', 'portal.organization_name' => 'Management choice A']);
        app(PortalAccessTokenStore::class)->put([
            'access_token' => 'business-access-token', 'refresh_token' => 'business-refresh-token', 'expires_in' => 900,
        ]);
        $this->fakeOverlappingOrganizations();
        $this->get('/clinical')->assertOk()->assertSee('Choose your workspace')
            ->assertDontSee('class="clinical-nav"', false)->assertDontSee('class="organization-chip"', false);
        $component = Livewire::test(ClinicalDashboard::class)->assertSet('state', 'choose_organization');
        $component->call('selectOrganization', 'organization-b')
            ->assertSet('state', 'ready')->assertRedirect(route('clinical.dashboard'));
        self::assertFalse($component->effects['redirectUsingNavigate'] ?? false, 'A normal full request must rebuild the outer clinical layout.');
        $this->get('/clinical')->assertOk()->assertSee('Clinical overview')
            ->assertSee('class="clinical-nav"', false)->assertSee('class="organization-chip"', false)
            ->assertSee('Synthetic organization-b')->assertDontSee('Management choice A');
        self::assertSame('organization-b', session('clinical_portal.organization_id'));
        self::assertSame('management-a', session('portal.organization_id'));
        self::assertSame('Management choice A', session('portal.organization_name'));
        self::assertSame('clinical-access-token', app(ClinicalPortalAccessTokenStore::class)->accessToken());
        self::assertSame('business-access-token', app(PortalAccessTokenStore::class)->accessToken());
        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/v1/business/clinical/me')
            && $request->hasHeader('X-Zigpaw-Organization-ID', 'organization-b')
            && $request->hasHeader('Authorization', 'Bearer clinical-access-token'));
    }

    /** @return iterable<string, array{int, string}> */
    public static function unavailableClinicalSelections(): iterable
    {
        yield 'expired clinical authority' => [401, 'signed_out'];
        yield 'forbidden clinical authority' => [403, 'forbidden'];
        yield 'unavailable clinical API' => [503, 'unavailable'];
    }

    #[DataProvider('unavailableClinicalSelections')]
    public function test_unavailable_clinical_selection_does_not_redirect_as_a_ready_workspace(int $status, string $state): void
    {
        $this->signIn();
        session()->forget(['clinical_portal.organization_id', 'clinical_portal.organization_name']);
        session()->put(['portal.organization_id' => 'management-a', 'portal.organization_name' => 'Management choice A']);
        app(PortalAccessTokenStore::class)->put([
            'access_token' => 'business-access-token', 'refresh_token' => 'business-refresh-token', 'expires_in' => 900,
        ]);
        Http::fake(function (Request $request) use ($status) {
            $path = parse_url($request->url(), PHP_URL_PATH);
            if ($path === '/v1/business/clinical/organizations') {
                return Http::response(['data' => [
                    ['id' => 'organization-a', 'name' => 'Synthetic organization A', 'role' => 'owner'],
                    ['id' => 'organization-b', 'name' => 'Synthetic organization B', 'role' => 'owner'],
                ], 'meta' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 25, 'total' => 2], 'links' => $this->pageLinks()]);
            }
            if ($path === '/v1/business/clinical/me') {
                return Http::response(['message' => 'Synthetic clinical authority unavailable.'], $status);
            }

            return Http::response(['data' => []]);
        });
        Livewire::test(ClinicalDashboard::class)->assertSet('state', 'choose_organization')
            ->call('selectOrganization', 'organization-b')->assertSet('state', $state)->assertNoRedirect();
        self::assertSame('management-a', session('portal.organization_id'));
        self::assertSame('Management choice A', session('portal.organization_name'));
        self::assertSame('business-access-token', app(PortalAccessTokenStore::class)->accessToken());
        self::assertSame($status === 401 ? null : 'clinical-access-token', app(ClinicalPortalAccessTokenStore::class)->accessToken());
        if ($status === 401) {
            self::assertNull(session('clinical_portal.organization_id'));
        }
        Http::assertNotSent(fn (Request $request): bool => str_ends_with($request->url(), '/v1/business/clinical/dashboard'));
    }

    private function assertClinicalMorphRoot(Testable $component, string $expectedId, bool $initial = false): void
    {
        $html = $initial ? $component->html() : ($component->effects['html'] ?? null);
        self::assertIsString($html, 'A real rendered Livewire HTML effect is required.');
        $document = new \DOMDocument;
        @$document->loadHTML('<div id="clinical-morph-fragment">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        $fragment = $document->getElementById('clinical-morph-fragment');
        self::assertInstanceOf(\DOMElement::class, $fragment);
        $roots = (new \DOMXPath($document))->query('.//*[@*[name()="wire:id"]]', $fragment);
        self::assertNotFalse($roots);
        self::assertSame(1, $roots->length, 'The rendered fragment must contain exactly one component root.');
        $root = $roots->item(0);
        self::assertInstanceOf(\DOMElement::class, $root);
        self::assertSame($expectedId, $root->getAttribute('wire:id'));
        $rootParent = $root->parentNode;
        self::assertInstanceOf(\DOMElement::class, $rootParent);
        $markersOutsideRoot = 0;
        foreach ($rootParent->childNodes as $node) {
            if ($node instanceof \DOMComment && preg_match('/BLOCK|FRAGMENT/i', $node->textContent) === 1) {
                $markersOutsideRoot++;
            }
        }
        self::assertSame(0, $markersOutsideRoot, 'Conditional morph markers must stay inside the persistent component root.');
        $snapshot = $component->__get('snapshot');
        self::assertIsArray($snapshot);
        self::assertSame($expectedId, data_get($snapshot, 'memo.id'));
    }

    public function test_overlapping_multi_organization_memberships_keep_management_and_clinical_choices_independent(): void
    {
        $this->signIn();
        session()->forget(['clinical_portal.organization_id', 'clinical_portal.organization_name']);
        session()->put(['portal.organization_id' => 'organization-a', 'portal.organization_name' => 'Management choice A']);
        app(PortalAccessTokenStore::class)->put(['access_token' => 'business-access-token', 'refresh_token' => 'business-refresh-token', 'expires_in' => 900]);
        $this->fakeOverlappingOrganizations();
        $clinical = Livewire::test(ClinicalDashboard::class)->assertSet('state', 'choose_organization');
        self::assertSame('organization-a', session('portal.organization_id'));
        self::assertNull(session('clinical_portal.organization_id'));
        $clinical->call('selectOrganization', 'organization-b')->assertSet('state', 'ready')->assertSet('identity.organization.id', 'organization-b');
        self::assertSame('organization-a', session('portal.organization_id'));
        self::assertSame('Management choice A', session('portal.organization_name'));
        self::assertSame('organization-b', session('clinical_portal.organization_id'));
        Livewire::test(BusinessWorkspace::class)->assertStatus(200)->assertSet('state', 'ready')->assertSet('organizationId', 'organization-a')
            ->call('selectOrganization', 'organization-b')->assertRedirect(route('dashboard', ['section' => 'overview']));
        self::assertSame('organization-b', session('portal.organization_id'));
        $clinical->call('changeOrganization')->assertRedirect(route('clinical.dashboard'));
        self::assertNull(session('clinical_portal.organization_id'));
        self::assertSame('organization-b', session('portal.organization_id'));
        Livewire::test(ClinicalDashboard::class)->assertSet('state', 'choose_organization')
            ->call('selectOrganization', 'organization-a')->assertSet('state', 'ready');
        self::assertSame('organization-a', session('clinical_portal.organization_id'));
        Livewire::test(BusinessWorkspace::class)->assertSet('organizationId', 'organization-b');
        self::assertSame('business-access-token', app(PortalAccessTokenStore::class)->accessToken());
        self::assertSame('clinical-access-token', app(ClinicalPortalAccessTokenStore::class)->accessToken());
    }

    public function test_clinical_unauthorized_then_relogin_requires_its_own_choice_without_erasing_management(): void
    {
        $this->signIn();
        app(PortalAccessTokenStore::class)->put(['access_token' => 'business-access-token', 'refresh_token' => 'business-refresh-token', 'expires_in' => 900]);
        session()->put(['portal.organization_id' => 'organization-b', 'portal.organization_name' => 'Management choice B']);
        Http::fake(['https://api.zigpaw.test/*' => Http::response([], 401)]);
        Livewire::test(PatientIndex::class)->assertRedirect(route('clinical.dashboard'));
        self::assertSame('organization-b', session('portal.organization_id'));
        self::assertSame('Management choice B', session('portal.organization_name'));
        self::assertNull(session('clinical_portal.organization_id'));
        self::assertNull(session('clinical_portal.organization_name'));
        self::assertNull(app(ClinicalPortalAccessTokenStore::class)->accessToken());
        self::assertSame('business-access-token', app(PortalAccessTokenStore::class)->accessToken());
        app(ClinicalPortalAccessTokenStore::class)->put(['access_token' => 'new-clinical-token', 'refresh_token' => 'new-clinical-refresh', 'expires_in' => 900]);
        Http::swap(new Factory);
        Http::preventStrayRequests();
        $this->fakeOverlappingOrganizations();
        Livewire::test(ClinicalDashboard::class)->assertSet('state', 'choose_organization');
        self::assertSame('organization-b', session('portal.organization_id'));
        self::assertNull(session('clinical_portal.organization_id'));
    }

    private function fakeOverlappingOrganizations(): void
    {
        Http::fake(function (Request $request) {
            $path = parse_url($request->url(), PHP_URL_PATH);
            if (str_ends_with($path, '/organizations')) {
                return Http::response(['data' => [
                    ['id' => 'organization-a', 'name' => 'Synthetic organization A', 'role' => 'owner'],
                    ['id' => 'organization-b', 'name' => 'Synthetic organization B', 'role' => 'owner'],
                ], 'meta' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 25, 'total' => 2], 'links' => $this->pageLinks()]);
            }
            if (str_ends_with($path, '/me')) {
                $id = $request->header('X-Zigpaw-Organization-ID')[0];

                return Http::response(['data' => ['organization' => ['id' => $id, 'name' => 'Synthetic '.$id, 'organization_type' => 'veterinary'],
                    'features' => [], 'membership' => ['id' => 'membership-'.$id, 'role' => 'owner', 'capabilities' => ['portal.view']]]]);
            }

            return Http::response(['data' => []]);
        });
    }

    private const GRANT_ID = '019fe05f-3d0e-7079-86e3-e8ab5aa380b1';

    private const MEDIA_ID = '019fe05f-3d0e-7079-86e3-e8ab5aa380b2';

    private const SUBMISSION_ID = '019fef67-1853-732a-82cf-e76f955e4d32';

    public function test_clinical_workspace_has_its_own_signed_out_entry_point(): void
    {
        $this->get('/clinical')
            ->assertOk()
            ->assertSee('Zigpaw Business clinical workspace')
            ->assertSee('Sign in securely');

        $this->get('/clinical/patients')
            ->assertRedirect(route('clinical.dashboard'))
            ->assertSessionHas('error', 'Your secure clinical session has ended. Please sign in again.');
    }

    public function test_management_session_context_does_not_render_clinical_workspace_navigation(): void
    {
        app(PortalAccessTokenStore::class)->put([
            'access_token' => 'business-access-token',
            'refresh_token' => 'business-refresh-token',
            'expires_in' => 900,
        ]);
        session()->put([
            'portal.organization_id' => 'management-organization',
            'portal.organization_name' => 'Management organization',
        ]);

        $this->get('/clinical')
            ->assertOk()
            ->assertSee('Sign in securely')
            ->assertDontSee('class="clinical-nav"', false)
            ->assertDontSee('class="organization-chip"', false)
            ->assertDontSee('Management organization');

        $this->assertSame('business-access-token', app(PortalAccessTokenStore::class)->accessToken());
        $this->assertNull(app(ClinicalPortalAccessTokenStore::class)->accessToken());
    }

    /** @return iterable<string, array{bool, bool}> */
    public static function independentClinicalLayoutContexts(): iterable
    {
        yield 'management selected, clinical chooser' => [true, false];
        yield 'clinical selected without management' => [false, true];
        yield 'different management and clinical choices' => [true, true];
    }

    #[DataProvider('independentClinicalLayoutContexts')]
    public function test_clinical_layout_uses_only_its_current_audience_selection(bool $managementSelected, bool $clinicalSelected): void
    {
        $this->signIn();
        session()->forget(['portal.organization_id', 'portal.organization_name', 'clinical_portal.organization_id', 'clinical_portal.organization_name']);
        if ($managementSelected) {
            session()->put(['portal.organization_id' => 'management-organization', 'portal.organization_name' => 'Synthetic Management Choice']);
            app(PortalAccessTokenStore::class)->put([
                'access_token' => 'business-access-token', 'refresh_token' => 'business-refresh-token', 'expires_in' => 900,
            ]);
        }
        if ($clinicalSelected) {
            session()->put(['clinical_portal.organization_id' => 'organization-1', 'clinical_portal.organization_name' => 'Synthetic Clinical Choice']);
        }
        Http::fake(function (Request $request) {
            $path = parse_url($request->url(), PHP_URL_PATH);
            if ($path === '/v1/business/clinical/organizations') {
                return Http::response(['data' => [
                    ['id' => 'organization-1', 'name' => 'Synthetic Clinical Choice', 'role' => 'owner'],
                    ['id' => 'organization-2', 'name' => 'Synthetic Other Clinic', 'role' => 'owner'],
                ], 'meta' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 25, 'total' => 2], 'links' => $this->pageLinks()]);
            }
            if ($path === '/v1/business/clinical/me') {
                return Http::response(['data' => ['organization' => ['id' => 'organization-1', 'name' => 'Synthetic Clinical Choice']]]);
            }

            return Http::response(['data' => []]);
        });

        $response = $this->get('/clinical')->assertOk()->assertDontSee('Synthetic Management Choice');
        if ($clinicalSelected) {
            $response->assertSee('Clinical overview')->assertSee('class="clinical-nav"', false)->assertSee('class="organization-chip"', false);
            $document = new \DOMDocument;
            $content = $response->getContent();
            self::assertIsString($content);
            @$document->loadHTML($content);
            $chips = (new \DOMXPath($document))->query('//span[contains(concat(" ", normalize-space(@class), " "), " organization-chip ")]');
            self::assertNotFalse($chips);
            self::assertSame(1, $chips->length);
            $chip = $chips->item(0);
            self::assertInstanceOf(\DOMElement::class, $chip);
            self::assertSame('Synthetic Clinical Choice', trim($chip->textContent));
        } else {
            $response->assertSee('Choose your workspace')->assertDontSee('class="clinical-nav"', false)->assertDontSee('class="organization-chip"', false);
        }
        self::assertSame($managementSelected ? 'management-organization' : null, session('portal.organization_id'));
        self::assertSame($clinicalSelected ? 'organization-1' : null, session('clinical_portal.organization_id'));
        self::assertSame('clinical-access-token', app(ClinicalPortalAccessTokenStore::class)->accessToken());
        self::assertSame($managementSelected ? 'business-access-token' : null, app(PortalAccessTokenStore::class)->accessToken());
        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/v1/business/clinical/organizations')
            && $request->hasHeader('Authorization', 'Bearer clinical-access-token'));
        Http::assertNotSent(fn (Request $request): bool => ! str_contains($request->url(), '/v1/business/clinical/'));
    }

    public function test_clinical_api_calls_use_the_canonical_audience_and_separate_credentials(): void
    {
        app(ClinicalPortalAccessTokenStore::class)->put([
            'access_token' => 'clinical-access-token',
            'refresh_token' => 'clinical-refresh-token',
            'expires_in' => 900,
        ]);

        Http::fake([
            'https://api.zigpaw.test/v1/business/clinical/organizations' => Http::response([
                'data' => [['id' => 'organization-1', 'name' => 'Riverbank Clinic']],
            ]),
        ]);

        $organizations = app(PlatformApiClient::class)->clinicalOrganizations(
            app(ClinicalPortalAccessTokenStore::class),
        );

        $this->assertSame('organization-1', $organizations['data'][0]['id']);
        $this->assertNull(app(PortalAccessTokenStore::class)->accessToken());
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.zigpaw.test/v1/business/clinical/organizations'
            && $request->hasHeader('Authorization', 'Bearer clinical-access-token')
            && $request->hasHeader('X-Request-ID')
            && ! $request->hasHeader('X-Zigpaw-Organization-ID'));
    }

    public function test_patient_index_lists_only_platform_returned_grants(): void
    {
        $this->signIn();
        Http::fake([
            'https://api.zigpaw.test/v1/business/clinical/provider-grants*' => Http::response([
                'data' => [[
                    'id' => self::GRANT_ID,
                    'purpose' => 'vet_visit',
                    'status' => 'active',
                    'capabilities' => ['read_profile' => true, 'read_care' => true, 'submit_care' => true, 'read_media' => true],
                    'pet' => ['id' => 'pet-1', 'name' => 'Patchy', 'species' => 'Dog', 'breed' => 'Cattle Dog'],
                    'location' => ['id' => 'location-1', 'name' => 'Riverbank Clinic'],
                    'expires_at' => '2026-09-01T00:00:00+10:00',
                ]],
                'links' => $this->pageLinks(),
                'meta' => $this->pageMeta(),
            ]),
        ]);

        $this->get('/clinical/patients')
            ->assertOk()
            ->assertSee('Patchy')
            ->assertSee('Dog · Cattle Dog')
            ->assertSee('Riverbank Clinic')
            ->assertSee('Access expires 2026-09-01')
            ->assertDontSee('Ends 2026-09-01');

        Http::assertSent(fn (Request $request): bool => $request->hasHeader('X-Zigpaw-Organization-ID', 'organization-1')
            && $request['status'] === 'active');
    }

    /** @param array<string, bool> $capabilities */
    #[DataProvider('profilePreviewCapabilities')]
    public function test_missing_patient_taxonomy_does_not_imply_profile_permission_denial(array $capabilities, bool $profileAllowed): void
    {
        $this->signIn();
        $grant = [
            'id' => self::GRANT_ID,
            'purpose' => 'vet_visit',
            'status' => 'active',
            'capabilities' => $capabilities,
            'pet' => ['id' => 'pet-1', 'name' => 'Patchy', 'species' => null, 'breed' => null],
            'expires_at' => null,
        ];
        Http::fake([
            'https://api.zigpaw.test/v1/business/clinical/provider-grants/'.self::GRANT_ID => Http::response(['data' => $grant]),
            'https://api.zigpaw.test/v1/business/clinical/provider-grants*' => Http::response([
                'data' => [$grant],
                'links' => $this->pageLinks(),
                'meta' => $this->pageMeta(),
            ]),
        ]);

        $this->get('/clinical/patients')
            ->assertOk()
            ->assertSee('Patchy')
            ->assertSee('No species or breed details available.')
            ->assertSee('Access has no set expiry')
            ->assertDontSee('No set end date')
            ->assertDontSee('Profile details are not included in this grant.');

        $response = $this->get('/clinical/patients/'.self::GRANT_ID)
            ->assertOk()
            ->assertSee('No species or breed details available.')
            ->assertDontSee('Profile details are not included in this grant.')
            ->assertSee('Care history is not included')
            ->assertSee('Media access is not included in this grant')
            ->assertDontSee('Submit care records');

        if ($profileAllowed) {
            $response->assertSee('<i>Profile</i>', false);
        } else {
            $response->assertDontSee('<i>Profile</i>', false);
        }

        Http::assertSentCount(2);
    }

    /** @return array<string, array{array<string, bool>, bool}> */
    public static function profilePreviewCapabilities(): array
    {
        return [
            'profile allowed with missing taxonomy' => [['read_profile' => true], true],
            'profile explicitly denied' => [['read_profile' => false], false],
            'profile capability absent' => [[], false],
        ];
    }

    public function test_patient_filters_normalize_hostile_url_values_before_the_platform_request(): void
    {
        $this->signIn();
        Http::fake([
            'https://api.zigpaw.test/v1/business/clinical/provider-grants*' => Http::response([
                'data' => [],
                'links' => $this->pageLinks(),
                'meta' => $this->pageMeta(),
            ]),
        ]);

        $this->get('/clinical/patients?status=not-a-status&page=-8&search='.str_repeat('x', 100))
            ->assertOk()
            ->assertSee('No matching active patient');

        Http::assertSent(fn (Request $request): bool => $request['status'] === 'active'
            && (int) $request['page'] === 1
            && mb_strlen((string) $request['search']) === 80);
    }

    public function test_patient_name_search_is_not_sent_for_inactive_grants(): void
    {
        $this->signIn();
        Http::fake([
            'https://api.zigpaw.test/v1/business/clinical/provider-grants*' => Http::response([
                'data' => [],
                'links' => $this->pageLinks(),
                'meta' => $this->pageMeta(),
            ]),
        ]);

        $this->get('/clinical/patients?status=expired&search=Patchy')
            ->assertOk()
            ->assertSee('Name search is available for active grants.')
            ->assertSee('disabled', false);

        Http::assertSent(fn (Request $request): bool => $request['status'] === 'expired'
            && ! isset($request['search']));
    }

    public function test_patient_page_respects_capabilities_and_uses_the_local_media_proxy(): void
    {
        $this->signIn();
        Http::fake([
            'https://api.zigpaw.test/v1/business/clinical/provider-grants/'.self::GRANT_ID => Http::response(['data' => [
                'id' => self::GRANT_ID,
                'purpose' => 'vet_visit',
                'status' => 'active',
                'capabilities' => ['read_profile' => true, 'read_care' => true, 'submit_care' => true, 'read_media' => true],
                'pet' => ['id' => 'pet-1', 'name' => 'Patchy', 'species' => 'Dog', 'breed' => 'Cattle Dog'],
                'location' => ['id' => 'location-1', 'name' => 'Riverbank Clinic'],
                'expires_at' => null,
            ]]),
            'https://api.zigpaw.test/v1/business/clinical/provider-grants/'.self::GRANT_ID.'/care-context' => Http::response(['data' => [
                'pet' => ['id' => 'pet-1', 'name' => 'Patchy'],
                'weights' => [['id' => 'weight-1', 'recorded_date' => '2026-08-01', 'weight' => 22.4, 'unit' => 'kg']],
                'vaccinations' => [],
                'visits' => [],
                'medications' => [],
                'conditions' => [],
            ]]),
            'https://api.zigpaw.test/v1/business/clinical/provider-grants/'.self::GRANT_ID.'/media' => Http::response(['data' => [[
                'id' => self::MEDIA_ID,
                'kind' => 'profile_photo',
                'label' => 'Profile photo',
                'mime_type' => 'image/jpeg',
                'url' => 'https://api.zigpaw.test/v1/business/clinical/provider-grants/'.self::GRANT_ID.'/media/'.self::MEDIA_ID,
            ]]]),
        ]);

        $response = $this->get('/clinical/patients/'.self::GRANT_ID)
            ->assertOk()
            ->assertSee('Patchy')
            ->assertSee('22.4 kg')
            ->assertSee(route('clinical.patients.media.show', ['grantId' => self::GRANT_ID, 'mediaId' => self::MEDIA_ID]), false);

        $this->assertStringNotContainsString('api.zigpaw.test/v1/business/clinical/provider-grants', $response->getContent());
    }

    public function test_patient_page_renders_a_bounded_styled_document_icon_for_shared_files(): void
    {
        $this->signIn();
        Http::fake([
            'https://api.zigpaw.test/v1/business/clinical/provider-grants/'.self::GRANT_ID => Http::response(['data' => [
                'id' => self::GRANT_ID,
                'purpose' => 'vet_visit',
                'status' => 'active',
                'capabilities' => ['read_profile' => true, 'read_care' => false, 'read_media' => true],
                'pet' => ['id' => 'pet-1', 'name' => 'Patchy'],
                'location' => ['id' => 'location-1', 'name' => 'Riverbank Clinic'],
                'expires_at' => null,
            ]]),
            'https://api.zigpaw.test/v1/business/clinical/provider-grants/'.self::GRANT_ID.'/media' => Http::response(['data' => [[
                'id' => self::MEDIA_ID,
                'kind' => 'clinical_document',
                'label' => 'DHPP certificate',
                'mime_type' => 'application/pdf',
            ]]]),
        ]);

        $this->get('/clinical/patients/'.self::GRANT_ID)
            ->assertOk()
            ->assertSee('document-tile', false)
            ->assertSee('DHPP certificate')
            ->assertSee('pdf');

        $css = (string) file_get_contents(resource_path('css/business.css'));
        $this->assertStringContainsString(
            '.clinical-workspace .document-tile > svg { width: 32px; height: 32px; flex: 0 0 32px; stroke: var(--blue);',
            $css,
        );
    }

    public function test_patient_page_does_not_fetch_or_offer_capabilities_the_family_did_not_share(): void
    {
        $this->signIn();
        Http::fake([
            'https://api.zigpaw.test/v1/business/clinical/provider-grants/'.self::GRANT_ID => Http::response(['data' => [
                'id' => self::GRANT_ID,
                'purpose' => 'view_only',
                'status' => 'active',
                'capabilities' => ['read_profile' => true, 'read_care' => false, 'submit_care' => false, 'read_media' => false],
                'pet' => ['id' => 'pet-1', 'name' => 'Patchy'],
                'location' => ['id' => 'location-1', 'name' => 'Riverbank Clinic'],
                'expires_at' => null,
            ]]),
        ]);

        $this->get('/clinical/patients/'.self::GRANT_ID)
            ->assertOk()
            ->assertSee('Care history is not included')
            ->assertSee('Media access is not included in this grant')
            ->assertDontSee('Submit care records');

        Http::assertSentCount(1);
    }

    public function test_care_submission_uses_the_grant_and_redirects_to_family_review_status(): void
    {
        $this->signIn();
        Http::fake([
            'https://api.zigpaw.test/v1/business/clinical/provider-grants/'.self::GRANT_ID => Http::response(['data' => [
                'id' => self::GRANT_ID,
                'status' => 'active',
                'capabilities' => ['submit_care' => true],
                'pet' => ['id' => 'pet-1', 'name' => 'Patchy'],
                'location' => ['id' => 'location-1', 'name' => 'Riverbank Clinic'],
            ]]),
            'https://api.zigpaw.test/v1/business/clinical/me' => Http::response(['data' => [
                'id' => 'clinician-1',
                'name' => 'Dr Rivera',
                'email' => 'rivera@example.test',
                'membership' => ['id' => 'membership-1'],
                'organization' => ['id' => 'organization-1', 'name' => 'Riverbank Clinic'],
            ]]),
            'https://api.zigpaw.test/v1/business/clinical/dashboard' => Http::response(['data' => [
                'grants' => ['active' => 1],
                'submissions' => ['pending' => 0],
                'locations' => [['id' => 'location-1', 'name' => 'Riverbank Clinic']],
            ]]),
            'https://api.zigpaw.test/v1/business/clinical/provider-grants/'.self::GRANT_ID.'/care-submissions' => Http::response(['data' => [
                'id' => self::SUBMISSION_ID,
                'status' => 'pending',
            ]], 201),
        ]);

        Livewire::test(CareSubmission::class, ['grantId' => self::GRANT_ID])
            ->assertSee('Enter the product or vaccine name exactly as it appears')
            ->assertDontSee('clinical API')
            ->set('visitDate', now()->toDateString())
            ->set('visitType', 'checkup')
            ->set('diagnosis', 'Routine examination; no new concerns.')
            ->call('addCondition')
            ->set('conditions.0.condition_name', 'Seasonal dermatitis')
            ->set('conditions.0.severity', 'moderate')
            ->call('submit')
            ->assertHasNoErrors()
            ->assertRedirect(route('clinical.submissions.show', ['submissionId' => self::SUBMISSION_ID]));

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && $request->url() === 'https://api.zigpaw.test/v1/business/clinical/provider-grants/'.self::GRANT_ID.'/care-submissions'
            && $request->hasHeader('Idempotency-Key')
            && $request['vet_name'] === 'Dr Rivera'
            && $request['clinic_name'] === 'Riverbank Clinic'
            && $request['diagnosis'] === 'Routine examination; no new concerns.'
            && data_get($request->data(), 'conditions.0.severity') === 'moderate');
    }

    public function test_media_proxy_preserves_safe_content_headers_and_disables_storage(): void
    {
        $this->signIn();
        Http::fake([
            'https://api.zigpaw.test/v1/business/clinical/provider-grants/'.self::GRANT_ID.'/media/'.self::MEDIA_ID => Http::response(
                'image-content',
                200,
                [
                    'Content-Type' => 'image/jpeg',
                    'Content-Disposition' => 'inline; filename="patchy.jpg"',
                    'X-Upstream-Secret' => 'must-not-leak',
                ],
            ),
        ]);

        $response = $this->get(route('clinical.patients.media.show', ['grantId' => self::GRANT_ID, 'mediaId' => self::MEDIA_ID]))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg')
            ->assertHeader('Content-Disposition', 'attachment; filename="clinical-media-'.self::MEDIA_ID.'"')
            ->assertHeaderMissing('X-Upstream-Secret');

        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('image-content', $response->streamedContent());
    }

    public function test_submission_history_and_review_status_render_the_bounded_platform_summary(): void
    {
        $this->signIn();
        $submission = [
            'id' => self::SUBMISSION_ID,
            'pet_id' => 'pet-1',
            'status' => 'pending',
            'submitted_at' => '2026-08-16T11:30:00+10:00',
            'record_counts' => ['visits' => 1, 'weights' => 1, 'vaccinations' => 2, 'medications' => 0, 'conditions' => 0],
            'records_pending_review' => 4,
        ];
        Http::fake([
            'https://api.zigpaw.test/v1/business/clinical/submissions/'.self::SUBMISSION_ID => Http::response(['data' => $submission]),
            'https://api.zigpaw.test/v1/business/clinical/submissions?*' => Http::response([
                'data' => [$submission],
                'links' => $this->pageLinks(),
                'meta' => $this->pageMeta(),
            ]),
        ]);

        $this->get('/clinical/submissions')
            ->assertOk()
            ->assertSee('1 visits')
            ->assertSee('2 vaccinations');

        $this->get('/clinical/submissions/'.self::SUBMISSION_ID)
            ->assertOk()
            ->assertSee('Waiting for the profile manager')
            ->assertSee('4 records')
            ->assertDontSee('clinical API');
    }

    public function test_submission_filters_normalize_hostile_url_values_before_the_platform_request(): void
    {
        $this->signIn();
        Http::fake([
            'https://api.zigpaw.test/v1/business/clinical/submissions*' => Http::response([
                'data' => [],
                'links' => $this->pageLinks(),
                'meta' => $this->pageMeta(),
            ]),
        ]);

        $this->get('/clinical/submissions?status=unknown&page=-19')
            ->assertOk()
            ->assertSee('No care submissions yet');

        Http::assertSent(fn (Request $request): bool => (int) $request['page'] === 1
            && ! isset($request['status']));
    }

    public function test_an_upstream_unauthorized_response_ends_only_the_local_clinical_session(): void
    {
        $this->signIn();
        app(PortalAccessTokenStore::class)->put([
            'access_token' => 'business-access-token',
            'refresh_token' => 'business-refresh-token',
            'expires_in' => 900,
        ]);
        Http::fake([
            'https://api.zigpaw.test/v1/business/clinical/provider-grants*' => Http::response([
                'message' => 'Unauthenticated.',
            ], 401),
        ]);

        Livewire::test(PatientIndex::class)
            ->assertRedirect(route('clinical.dashboard'));

        $this->assertNull(app(ClinicalPortalAccessTokenStore::class)->accessToken());
        $this->assertSame('business-access-token', app(PortalAccessTokenStore::class)->accessToken());
        $this->assertNull(session('clinical_portal.organization_id'));
    }

    private function signIn(): void
    {
        app(ClinicalPortalAccessTokenStore::class)->put([
            'access_token' => 'clinical-access-token',
            'refresh_token' => 'clinical-refresh-token',
            'expires_in' => 900,
        ]);

        session()->put([
            'clinical_portal.organization_id' => 'organization-1',
            'clinical_portal.organization_name' => 'Riverbank Clinic',
        ]);
    }

    /** @return array{first: null, last: null, prev: null, next: null} */
    private function pageLinks(): array
    {
        return ['first' => null, 'last' => null, 'prev' => null, 'next' => null];
    }

    /** @return array{current_page: int, last_page: int, per_page: int, total: int} */
    private function pageMeta(): array
    {
        return ['current_page' => 1, 'last_page' => 1, 'per_page' => 25, 'total' => 1];
    }
}
