<?php

namespace Tests\Feature;

use App\Livewire\Clinical\CareSubmission;
use App\Support\ClinicalPortalAccessTokenStore;
use App\Support\ClinicalSubmissionRetry;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ClinicalSubmissionRetryTest extends TestCase
{
    private const GRANT_ID = '019f5a00-0000-7000-8000-000000000001';

    private const SUBMISSION_ID = '019f5a00-0000-7000-8000-000000000002';

    /** @var list<array{key: string, payload: array<string, mixed>}> */
    private array $writes = [];

    /** @var array<string, mixed> */
    private array $identity = [
        'id' => 'synthetic-clinician',
        'name' => 'Synthetic clinician',
        'email' => 'clinician@example.test',
        'membership' => ['id' => 'synthetic-membership'],
        'organization' => ['id' => 'synthetic-organization', 'name' => 'Synthetic clinic'],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        app(ClinicalPortalAccessTokenStore::class)->put(['access_token' => 'synthetic-clinical', 'refresh_token' => 'synthetic-refresh', 'expires_in' => 900]);
        session()->put('portal.organization_id', 'synthetic-organization');
    }

    public function test_platform_validation_correction_starts_a_new_operation(): void
    {
        $this->fakeClinical(fn (int $attempt) => $attempt === 1
            ? Http::response(['message' => 'Check the care records.', 'errors' => ['heart_rate' => ['The heart rate must be at least 1.']]], 422)
            : $this->confirmedSubmission());

        Livewire::test(CareSubmission::class, ['grantId' => self::GRANT_ID])
            ->set('heartRate', '0')
            ->call('submit')
            ->assertHasErrors('heartRate')
            ->assertSet('hasPendingSubmission', false)
            ->set('heartRate', '80')
            ->call('submit')
            ->assertHasNoErrors()
            ->assertRedirect(route('clinical.submissions.show', ['submissionId' => self::SUBMISSION_ID]));

        self::assertCount(2, $this->writes);
        self::assertNotSame($this->writes[0]['key'], $this->writes[1]['key']);
        self::assertNull(session(ClinicalSubmissionRetry::SESSION_KEY));
    }

    #[DataProvider('uncertainResponses')]
    public function test_uncertain_submission_retries_the_same_key_after_reload_and_session_regeneration(int $status, ?string $errorCode): void
    {
        $this->fakeClinical(fn (int $attempt) => $attempt === 1
            ? Http::response(['message' => 'Synthetic unconfirmed outcome.', 'error' => ['code' => $errorCode]], $status)
            : $this->confirmedSubmission());

        $this->submissionForm()->call('submit')
            ->assertSet('hasPendingSubmission', true)
            ->assertHasErrors('submission')
            ->assertDontSee('These records were already submitted.')
            ->assertSee('Check submission history')
            ->assertSee('wire:confirm=', false);
        self::assertCount(1, $this->writes);
        $saved = json_encode(session(ClinicalSubmissionRetry::SESSION_KEY), JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString('Synthetic private clinical narrative', $saved);
        self::assertStringNotContainsString('clinician@example.test', $saved);

        session()->regenerate();
        $this->submissionForm()
            ->assertSet('hasPendingSubmission', true)
            ->call('submit')
            ->assertHasNoErrors()
            ->assertRedirect(route('clinical.submissions.show', ['submissionId' => self::SUBMISSION_ID]));

        self::assertCount(2, $this->writes);
        self::assertSame($this->writes[0]['key'], $this->writes[1]['key']);
        self::assertSame($this->writes[0]['payload'], $this->writes[1]['payload']);
        self::assertNull(session(ClinicalSubmissionRetry::SESSION_KEY));
    }

    public static function uncertainResponses(): array
    {
        return [[503, null], [409, 'IDEMPOTENCY_ATTENTION_REQUIRED'], [409, 'IDEMPOTENCY_CONFLICT'], [409, null]];
    }

    public function test_changed_records_are_blocked_until_an_explicit_new_submission_is_started(): void
    {
        $this->fakeClinical(fn (int $attempt) => $attempt === 1
            ? Http::response([], 503)
            : $this->confirmedSubmission());
        $form = $this->submissionForm()
            ->call('submit')
            ->set('diagnosis', 'Changed clinical narrative')
            ->call('submit')
            ->assertHasErrors('submission')
            ->assertSet('diagnosis', 'Changed clinical narrative');

        self::assertCount(1, $this->writes, 'Changed content must not silently send a second clinical write.');
        $form->call('startNewSubmission')
            ->assertSet('hasPendingSubmission', false)
            ->assertSet('diagnosis', 'Changed clinical narrative');
        self::assertCount(1, $this->writes, 'Starting an intent must not submit any records.');
        $form->call('submit')->assertHasNoErrors();

        self::assertCount(2, $this->writes);
        self::assertNotSame($this->writes[0]['key'], $this->writes[1]['key']);
    }

    public function test_malformed_success_does_not_forget_the_pending_operation(): void
    {
        $this->fakeClinical(fn (int $attempt) => $attempt === 1
            ? Http::response(['data' => ['status' => 'pending']], 201)
            : $this->confirmedSubmission());
        $form = $this->submissionForm()->call('submit')
            ->assertHasErrors('submission')
            ->assertSet('hasPendingSubmission', true);
        $form->call('submit')->assertHasNoErrors();
        self::assertCount(2, $this->writes);
        self::assertSame($this->writes[0]['key'], $this->writes[1]['key']);
    }

    #[DataProvider('identityChanges')]
    public function test_an_old_component_cannot_write_or_clear_pending_state_after_its_context_changes(string $changedField): void
    {
        $this->fakeClinical(fn () => Http::response([], 503));
        $form = $this->submissionForm()->call('submit');
        $pending = session(ClinicalSubmissionRetry::SESSION_KEY);
        if ($changedField === 'organization') {
            session()->put('portal.organization_id', 'different-organization');
            $this->identity['organization']['id'] = 'different-organization';
        } elseif ($changedField === 'membership') {
            $this->identity['membership']['id'] = 'different-membership';
        } else {
            $this->identity['id'] = 'different-clinician';
        }

        $form->call('submit')->assertHasErrors('submission');
        $form->call('startNewSubmission')->assertHasErrors('submission');
        self::assertCount(1, $this->writes);
        self::assertSame($pending, session(ClinicalSubmissionRetry::SESSION_KEY));
    }

    public static function identityChanges(): array
    {
        return [['organization'], ['membership'], ['principal']];
    }

    public function test_a_new_principal_cannot_reuse_the_previous_principals_key(): void
    {
        $this->fakeClinical(fn () => Http::response([], 503));
        $this->submissionForm()->call('submit');
        $this->identity['id'] = 'different-clinician';
        $this->identity['membership']['id'] = 'different-membership';
        $this->submissionForm()->call('submit');

        self::assertCount(2, $this->writes);
        self::assertNotSame($this->writes[0]['key'], $this->writes[1]['key']);
    }

    private function submissionForm(): Testable
    {
        return Livewire::test(CareSubmission::class, ['grantId' => self::GRANT_ID])
            ->set('diagnosis', 'Synthetic private clinical narrative');
    }

    private function fakeClinical(callable $writeResponse): void
    {
        Http::fake(function (Request $request) use ($writeResponse) {
            if ($request->method() === 'POST') {
                self::assertNotNull(session(ClinicalSubmissionRetry::SESSION_KEY), 'Retry identity must be saved before the outbound write.');
                $this->writes[] = ['key' => $request->header('Idempotency-Key')[0], 'payload' => $request->data()];

                return $writeResponse(count($this->writes));
            }
            if (str_ends_with($request->url(), '/provider-grants/'.self::GRANT_ID)) {
                return Http::response(['data' => ['id' => self::GRANT_ID, 'capabilities' => ['submit_care' => true], 'pet' => ['name' => 'Synthetic pet']]]);
            }
            if (str_ends_with($request->url(), '/me')) {
                return Http::response(['data' => $this->identity]);
            }

            return Http::response(['data' => ['locations' => []]]);
        });
    }

    private function confirmedSubmission(): PromiseInterface
    {
        return Http::response(['data' => ['id' => self::SUBMISSION_ID, 'status' => 'pending']], 201);
    }
}
