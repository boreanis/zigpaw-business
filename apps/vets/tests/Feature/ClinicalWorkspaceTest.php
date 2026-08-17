<?php

namespace Tests\Feature;

use App\Livewire\Clinical\CareSubmission;
use App\Livewire\Clinical\PatientIndex;
use App\Support\PortalAccessTokenStore;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class ClinicalWorkspaceTest extends TestCase
{
    private const GRANT_ID = '019fe05f-3d0e-7079-86e3-e8ab5aa380b1';

    private const MEDIA_ID = 42;

    private const SUBMISSION_ID = '019fef67-1853-732a-82cf-e76f955e4d32';

    public function test_operational_routes_redirect_when_the_secure_portal_session_is_missing(): void
    {
        $this->get('/patients')
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('error', 'Your secure session has ended. Please sign in again.');
    }

    public function test_patient_index_lists_only_platform_returned_grants(): void
    {
        $this->signIn();
        Http::fake([
            'https://api.zigpaw.test/v1/vets/provider-grants*' => Http::response([
                'data' => [[
                    'id' => self::GRANT_ID,
                    'purpose' => 'vet_visit',
                    'status' => 'active',
                    'capabilities' => ['read_profile' => true, 'read_care' => true, 'submit_care' => true, 'read_media' => true],
                    'pet' => ['id' => 'pet-1', 'name' => 'Patchy', 'species' => 'Dog', 'breed' => 'Cattle Dog'],
                    'location' => ['id' => 'location-1', 'name' => 'Riverbank Clinic'],
                    'expires_at' => '2026-09-01T00:00:00+10:00',
                ]],
                'links' => ['next' => null, 'previous' => null],
                'meta' => ['current_page' => 1, 'last_page' => 1],
            ]),
        ]);

        $this->get('/patients')
            ->assertOk()
            ->assertSee('Patchy')
            ->assertSee('Dog · Cattle Dog')
            ->assertSee('Riverbank Clinic');

        Http::assertSent(fn (Request $request): bool => $request->hasHeader('X-Zigpaw-Organization-ID', 'organization-1')
            && $request['status'] === 'active');
    }

    public function test_patient_filters_normalize_hostile_url_values_before_the_platform_request(): void
    {
        $this->signIn();
        Http::fake([
            'https://api.zigpaw.test/v1/vets/provider-grants*' => Http::response([
                'data' => [],
                'links' => ['next' => null, 'previous' => null],
                'meta' => ['current_page' => 1, 'last_page' => 1],
            ]),
        ]);

        $this->get('/patients?status=not-a-status&page=-8&search='.str_repeat('x', 100))
            ->assertOk()
            ->assertSee('No matching active patient');

        Http::assertSent(fn (Request $request): bool => $request['status'] === 'active'
            && $request['page'] === 1
            && mb_strlen((string) $request['search']) === 80);
    }

    public function test_patient_name_search_is_not_sent_for_inactive_grants(): void
    {
        $this->signIn();
        Http::fake([
            'https://api.zigpaw.test/v1/vets/provider-grants*' => Http::response([
                'data' => [],
                'links' => ['next' => null, 'previous' => null],
                'meta' => ['current_page' => 1, 'last_page' => 1],
            ]),
        ]);

        $this->get('/patients?status=expired&search=Patchy')
            ->assertOk()
            ->assertSee('Name search is available for active grants.')
            ->assertSee('disabled', false);

        Http::assertSent(fn (Request $request): bool => $request['status'] === 'expired'
            && (string) ($request['search'] ?? '') === '');
    }

    public function test_patient_page_respects_capabilities_and_uses_the_local_media_proxy(): void
    {
        $this->signIn();
        Http::fake([
            'https://api.zigpaw.test/v1/vets/provider-grants/'.self::GRANT_ID => Http::response(['data' => [
                'id' => self::GRANT_ID,
                'purpose' => 'vet_visit',
                'status' => 'active',
                'capabilities' => ['read_profile' => true, 'read_care' => true, 'submit_care' => true, 'read_media' => true],
                'pet' => ['id' => 'pet-1', 'name' => 'Patchy', 'species' => 'Dog', 'breed' => 'Cattle Dog'],
                'location' => ['id' => 'location-1', 'name' => 'Riverbank Clinic'],
                'expires_at' => null,
            ]]),
            'https://api.zigpaw.test/v1/vets/provider-grants/'.self::GRANT_ID.'/care-context' => Http::response(['data' => [
                'pet' => ['id' => 'pet-1', 'name' => 'Patchy'],
                'weights' => [['id' => 'weight-1', 'recorded_date' => '2026-08-01', 'weight' => 22.4, 'unit' => 'kg']],
                'vaccinations' => [], 'visits' => [], 'medications' => [], 'conditions' => [],
            ]]),
            'https://api.zigpaw.test/v1/vets/provider-grants/'.self::GRANT_ID.'/media' => Http::response(['data' => [[
                'id' => self::MEDIA_ID, 'kind' => 'profile_photo', 'label' => 'Profile photo', 'mime_type' => 'image/jpeg',
                'url' => 'https://api.zigpaw.test/v1/vets/provider-grants/'.self::GRANT_ID.'/media/'.self::MEDIA_ID,
            ]]]),
        ]);

        $response = $this->get('/patients/'.self::GRANT_ID)
            ->assertOk()
            ->assertSee('Patchy')
            ->assertSee('22.4 kg')
            ->assertSee(route('patients.media.show', ['grantId' => self::GRANT_ID, 'mediaId' => self::MEDIA_ID]), false);

        $this->assertStringNotContainsString('api.zigpaw.test/v1/vets/provider-grants', $response->getContent());
    }

    public function test_patient_page_does_not_fetch_or_offer_capabilities_the_family_did_not_share(): void
    {
        $this->signIn();
        Http::fake([
            'https://api.zigpaw.test/v1/vets/provider-grants/'.self::GRANT_ID => Http::response(['data' => [
                'id' => self::GRANT_ID,
                'purpose' => 'view_only',
                'status' => 'active',
                'capabilities' => ['read_profile' => true, 'read_care' => false, 'submit_care' => false, 'read_media' => false],
                'pet' => ['id' => 'pet-1', 'name' => 'Patchy'],
                'location' => ['id' => 'location-1', 'name' => 'Riverbank Clinic'],
                'expires_at' => null,
            ]]),
        ]);

        $this->get('/patients/'.self::GRANT_ID)
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
            'https://api.zigpaw.test/v1/vets/provider-grants/'.self::GRANT_ID => Http::response(['data' => [
                'id' => self::GRANT_ID,
                'status' => 'active',
                'capabilities' => ['submit_care' => true],
                'pet' => ['id' => 'pet-1', 'name' => 'Patchy'],
                'location' => ['id' => 'location-1', 'name' => 'Riverbank Clinic'],
            ]]),
            'https://api.zigpaw.test/v1/vets/me' => Http::response(['data' => [
                'id' => 'clinician-1', 'name' => 'Dr Rivera', 'email' => 'rivera@example.test',
                'organization' => ['id' => 'organization-1', 'name' => 'Riverbank Clinic'],
            ]]),
            'https://api.zigpaw.test/v1/vets/dashboard' => Http::response(['data' => [
                'grants' => ['active' => 1], 'submissions' => ['pending' => 0],
                'locations' => [['id' => 'location-1', 'name' => 'Riverbank Clinic']],
            ]]),
            'https://api.zigpaw.test/v1/vets/provider-grants/'.self::GRANT_ID.'/care-submissions' => Http::response(['data' => [
                'id' => self::SUBMISSION_ID, 'status' => 'pending',
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
            ->assertRedirect(route('submissions.show', ['submissionId' => self::SUBMISSION_ID]));

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && $request->hasHeader('Idempotency-Key')
            && $request['vet_name'] === 'Dr Rivera'
            && $request['clinic_name'] === 'Riverbank Clinic'
            && $request['diagnosis'] === 'Routine examination; no new concerns.'
            && data_get($request->data(), 'conditions.0.severity') === 'moderate');
    }

    public function test_media_proxy_preserves_content_type_and_disables_storage(): void
    {
        $this->signIn();
        Http::fake([
            'https://api.zigpaw.test/v1/vets/provider-grants/'.self::GRANT_ID.'/media/'.self::MEDIA_ID => Http::response(
                'image-content',
                200,
                ['Content-Type' => 'image/jpeg', 'Content-Disposition' => 'inline; filename="patchy.jpg"'],
            ),
        ]);

        $response = $this->get(route('patients.media.show', ['grantId' => self::GRANT_ID, 'mediaId' => self::MEDIA_ID]))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg')
            ->assertSee('image-content');

        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
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
            'https://api.zigpaw.test/v1/vets/submissions?*' => Http::response([
                'data' => [$submission],
                'links' => ['next' => null, 'previous' => null],
                'meta' => ['current_page' => 1, 'last_page' => 1],
            ]),
            'https://api.zigpaw.test/v1/vets/submissions/'.self::SUBMISSION_ID => Http::response(['data' => $submission]),
        ]);

        $this->get('/submissions')
            ->assertOk()
            ->assertSee('1 visits')
            ->assertSee('2 vaccinations');

        $this->get('/submissions/'.self::SUBMISSION_ID)
            ->assertOk()
            ->assertSee('Waiting for the profile manager')
            ->assertSee('4 records')
            ->assertDontSee('clinical API');
    }

    public function test_submission_filters_normalize_hostile_url_values_before_the_platform_request(): void
    {
        $this->signIn();
        Http::fake([
            'https://api.zigpaw.test/v1/vets/submissions*' => Http::response([
                'data' => [],
                'links' => ['next' => null, 'previous' => null],
                'meta' => ['current_page' => 1, 'last_page' => 1],
            ]),
        ]);

        $this->get('/submissions?status=unknown&page=-19')
            ->assertOk()
            ->assertSee('No care submissions yet');

        Http::assertSent(fn (Request $request): bool => $request['page'] === 1
            && ! isset($request['status']));
    }

    public function test_an_upstream_unauthorized_response_ends_the_local_clinical_session(): void
    {
        $this->signIn();
        Http::fake([
            'https://api.zigpaw.test/v1/vets/provider-grants*' => Http::response([
                'message' => 'Unauthenticated.',
            ], 401),
        ]);

        Livewire::test(PatientIndex::class)
            ->assertRedirect(route('dashboard'));

        $this->assertNull(app(PortalAccessTokenStore::class)->accessToken());
        $this->assertNull(session('portal.organization_id'));
    }

    private function signIn(): void
    {
        app(PortalAccessTokenStore::class)->put([
            'access_token' => 'portal-access-token',
            'refresh_token' => 'portal-refresh-token',
            'expires_in' => 900,
        ]);

        session()->put([
            'portal.organization_id' => 'organization-1',
            'portal.organization_name' => 'Riverbank Clinic',
        ]);
    }
}
