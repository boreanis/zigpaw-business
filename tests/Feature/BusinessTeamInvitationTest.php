<?php

namespace Tests\Feature;

use App\Support\PortalAccessTokenStore;
use App\Support\RedactTeamInvitationSecrets;
use App\Support\TeamInvitationContinuation;
use Illuminate\Http\Client\Request;
use Illuminate\Log\Logger as IlluminateLogger;
use Illuminate\Support\Facades\Http;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Tests\TestCase;

class BusinessTeamInvitationTest extends TestCase
{
    private const MEMBERSHIP = '019f5a00-0000-7000-8000-000000000031';

    private const TOKEN = 'QQQQQQQQQQQQQQQQQQQQQQQQQQQQQQQQQQQQQQQQQQQQQQQQQQQQQQQQQQQQQQQQ';

    protected function setUp(): void
    {
        parent::setUp();
        config(['platform.oauth_client_id' => 'synthetic-business-client']);
    }

    public function test_emailed_token_is_scrubbed_before_sign_in_and_get_never_joins_an_organization(): void
    {
        Http::fake();
        $this->get($this->url().'?token='.self::TOKEN)->assertStatus(303)->assertRedirect($this->url())
            ->assertHeader('Referrer-Policy', 'no-referrer')->assertDontSee(self::TOKEN);
        $this->get($this->url())->assertOk()->assertSee('Sign in to review invitation')->assertDontSee(self::TOKEN)
            ->assertHeader('Referrer-Policy', 'no-referrer');
        Http::assertNothingSent();
    }

    public function test_named_confirmation_uses_private_preview_and_explicit_post_sends_only_the_reviewed_role(): void
    {
        $this->fakeApi();
        $this->signIn();
        $this->capture();
        $response = $this->get($this->url())->assertOk()->assertSee('Synthetic Veterinary Team')->assertSee('Operator')
            ->assertSee('Invitation expires')->assertDontSee(self::TOKEN)->assertDontSee('synthetic-access-token');
        $key = $response->viewData('invitation')['key'];
        $this->assertArrayNotHasKey('token', $response->viewData('invitation'));
        $this->withSession(['clinical_portal.organization_id' => 'independent-clinical-workspace'])
            ->post($this->url(), ['confirmed_action' => '1', 'idempotency_key' => $key, 'token' => 'forged', 'expected_role' => 'owner'])
            ->assertRedirect(route('dashboard'))->assertSessionHas('status', 'Team invitation accepted. Choose your workspace to continue.')
            ->assertSessionHas('clinical_portal.organization_id', 'independent-clinical-workspace');
        $this->assertNull(app(TeamInvitationContinuation::class)->entry(self::MEMBERSHIP));
        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/preview')
            && $request->hasHeader('X-Zigpaw-Team-Invitation-Token', self::TOKEN)
            && ! $request->hasHeader('X-Zigpaw-Organization-ID') && ! str_contains($request->url(), self::TOKEN));
        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/accept')
            && $request->data() === ['token' => self::TOKEN, 'expected_role' => 'operator']
            && $request->hasHeader('Idempotency-Key') && ! $request->hasHeader('X-Zigpaw-Organization-ID'));
    }

    public function test_missing_confirmation_or_forged_key_does_not_send_acceptance(): void
    {
        $this->fakeApi();
        $this->signIn();
        $this->capture();
        $this->get($this->url())->assertOk();
        $this->post($this->url(), ['idempotency_key' => 'forged'])->assertSessionHasErrors('invitation');
        $this->post($this->url(), ['confirmed_action' => '1', 'idempotency_key' => 'forged'])->assertSessionHasErrors('invitation');
        Http::assertNotSent(fn (Request $request): bool => str_ends_with($request->url(), '/accept'));
    }

    public function test_uncertain_acceptance_retries_exact_payload_and_key_without_previewing_an_already_consumed_invitation(): void
    {
        $keys = [];
        $previews = 0;
        $this->fakeApi(function (Request $request) use (&$keys) {
            $keys[] = $request->header('Idempotency-Key')[0];
            $this->assertSame(['token' => self::TOKEN, 'expected_role' => 'operator'], $request->data());

            return count($keys) === 1 ? Http::response([], 503) : Http::response(['data' => $this->accepted()]);
        }, function () use (&$previews) {
            $previews++;

            return $previews <= 2 ? Http::response(['data' => $this->preview()]) : Http::response([], 422);
        });
        $this->signIn();
        $this->capture();
        $key = $this->get($this->url())->assertOk()->viewData('invitation')['key'];
        $payload = ['confirmed_action' => '1', 'idempotency_key' => $key];
        $this->post($this->url(), $payload)->assertSessionHasErrors('invitation');
        $this->get($this->url())->assertOk()->assertSee('Retry same acceptance')->assertSee('Synthetic Veterinary Team')->assertDontSee(self::TOKEN);
        $this->post($this->url(), $payload)->assertRedirect(route('dashboard'));
        $this->assertSame([$key, $key], $keys);
        $this->assertSame(2, $previews);
    }

    public function test_changed_role_requires_a_new_named_review_and_does_not_silently_rebind_the_original_form(): void
    {
        $role = 'operator';
        // Capture by reference so the synthetic source can change between GET and POST.
        $this->fakeApi(preview: function () use (&$role) {
            return Http::response(['data' => $this->preview($role)]);
        });
        $this->signIn();
        $this->capture();
        $old = $this->get($this->url())->assertOk()->viewData('invitation')['key'];
        $role = 'manager';
        $this->post($this->url(), ['confirmed_action' => '1', 'idempotency_key' => $old])->assertSessionHasErrors('invitation');
        Http::assertNotSent(fn (Request $request): bool => str_ends_with($request->url(), '/accept'));
        $review = $this->get($this->url())->assertOk()->assertSee('Manager');
        $new = $review->viewData('invitation')['key'];
        $this->assertNotSame($old, $new);
        $this->post($this->url(), ['confirmed_action' => '1', 'idempotency_key' => $old])->assertSessionHasErrors('invitation');
        Http::assertNotSent(fn (Request $request): bool => str_ends_with($request->url(), '/accept'));
    }

    public function test_successful_reauthentication_cannot_reuse_a_form_even_when_the_token_store_preserves_its_handle(): void
    {
        $this->fakeApi();
        $this->signIn();
        $this->capture();
        $key = $this->get($this->url())->assertOk()->viewData('invitation')['key'];
        $oldHandle = session('platform.oauth.token_handle');
        $oldContext = session('business.team_invitation_auth_context');
        $this->get(route('auth.login'))->assertRedirect();
        $state = session('platform.oauth.state');
        $this->get(route('auth.callback', ['state' => $state, 'code' => 'synthetic-new-account-code']))->assertRedirect(route('dashboard'));
        $this->assertSame($oldHandle, session('platform.oauth.token_handle'));
        $this->assertNotSame($oldContext, session('business.team_invitation_auth_context'));
        $this->withCookie((string) config('session.cookie'), session()->getId());
        $this->post($this->url(), ['confirmed_action' => '1', 'idempotency_key' => $key])->assertSessionHasErrors('invitation');
        Http::assertNotSent(fn (Request $request): bool => str_ends_with($request->url(), '/accept'));
    }

    public function test_locked_role_rejection_requires_fresh_named_review_without_rebinding_the_failed_payload(): void
    {
        $role = 'operator';
        $keys = [];
        $this->fakeApi(accept: function (Request $request) use (&$role, &$keys) {
            $keys[] = $request->header('Idempotency-Key')[0];
            $this->assertSame('operator', $request->data()['expected_role']);
            $role = 'manager';

            return Http::response(['message' => 'The invitation role changed.'], 422);
        }, preview: function () use (&$role) {
            return Http::response(['data' => $this->preview($role)]);
        });
        $this->signIn();
        $this->capture();
        $old = $this->get($this->url())->assertOk()->viewData('invitation')['key'];
        $this->post($this->url(), ['confirmed_action' => '1', 'idempotency_key' => $old])->assertSessionHasErrors('invitation');
        $review = $this->get($this->url())->assertOk()->assertSee('Manager')->assertDontSee('Retry same acceptance');
        $this->assertNotSame($old, $review->viewData('invitation')['key']);
        $this->post($this->url(), ['confirmed_action' => '1', 'idempotency_key' => $old])->assertSessionHasErrors('invitation');
        $this->assertSame([$old], $keys);
    }

    public function test_malformed_acceptance_keeps_the_same_uncertain_request_instead_of_claiming_a_join(): void
    {
        $keys = [];
        $this->fakeApi(accept: function (Request $request) use (&$keys) {
            $keys[] = $request->header('Idempotency-Key')[0];

            return count($keys) === 1
                ? Http::response(['data' => [...$this->accepted(), 'is_current_user' => false]])
                : Http::response(['data' => $this->accepted()]);
        });
        $this->signIn();
        $this->capture();
        $key = $this->get($this->url())->assertOk()->viewData('invitation')['key'];
        $body = ['confirmed_action' => '1', 'idempotency_key' => $key];
        $this->post($this->url(), $body)->assertRedirect($this->url())->assertSessionHasErrors('invitation')->assertSessionMissing('status');
        $this->get($this->url())->assertOk()->assertSee('Retry same acceptance');
        $this->post($this->url(), $body)->assertRedirect(route('dashboard'));
        $this->assertSame([$key, $key], $keys);
    }

    public function test_malformed_preview_cannot_render_named_confirmation_or_send_acceptance(): void
    {
        $this->fakeApi(preview: fn () => Http::response(['data' => [...$this->preview(), 'id' => 'a-different-membership']]));
        $this->signIn();
        $this->capture();
        $this->get($this->url())->assertOk()->assertSee('The invitation details are temporarily unavailable.')
            ->assertDontSee('name="idempotency_key"', false)->assertDontSee(self::TOKEN);
        Http::assertNotSent(fn (Request $request): bool => str_ends_with($request->url(), '/accept'));
    }

    public function test_expired_or_missing_continuation_never_exposes_token_or_acceptance_controls(): void
    {
        Http::fake();
        $this->capture();
        $this->travel(31)->minutes();
        $this->get($this->url())->assertOk()->assertSee('Reopen the latest invitation email')->assertDontSee('name="idempotency_key"', false)->assertDontSee(self::TOKEN);
        Http::assertNothingSent();
    }

    public function test_continuations_are_bounded_and_expired_secrets_are_removed_from_local_state(): void
    {
        $store = app(TeamInvitationContinuation::class);
        foreach (['019f5a00-0000-7000-8000-000000000031', '019f5a00-0000-7000-8000-000000000032', '019f5a00-0000-7000-8000-000000000033'] as $id) {
            $store->capture($id, self::TOKEN);
        }
        try {
            $store->capture('019f5a00-0000-7000-8000-000000000034', self::TOKEN);
            $this->fail('A fourth simultaneous continuation must be refused.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('Finish an existing invitation', $exception->getMessage());
        }
        $this->assertCount(3, session(TeamInvitationContinuation::SESSION_KEY));
        $this->travel(31)->minutes();
        $this->assertNull($store->entry(self::MEMBERSHIP));
        $this->assertSame([], session(TeamInvitationContinuation::SESSION_KEY));
    }

    public function test_missing_native_csrf_proof_is_rejected_before_invitation_transport(): void
    {
        Http::fake();
        $this->app->instance('env', 'local');
        $this->withSession(['_token' => 'synthetic-csrf-value'])->withHeaders(['Origin' => 'https://untrusted.example', 'Sec-Fetch-Site' => 'cross-site'])
            ->post($this->url(), ['confirmed_action' => '1', 'idempotency_key' => 'forged'])->assertStatus(419);
        Http::assertNothingSent();
    }

    public function test_persistent_logger_scrubs_invitation_headers_urls_and_message_text_without_removing_operational_context(): void
    {
        $handler = new TestHandler;
        $logger = new Logger('team-invitation', [$handler]);
        (new RedactTeamInvitationSecrets)(new IlluminateLogger($logger));
        $logger->warning('X-Zigpaw-Team-Invitation-Token: '.self::TOKEN, [
            'headers' => ['X-Zigpaw-Team-Invitation-Token' => [self::TOKEN], 'X-Request-ID' => 'synthetic-request-1'],
            'url' => $this->url().'?token='.self::TOKEN, 'invitation_token_hash' => 'safe-hash',
        ]);
        $record = $handler->getRecords()[0];
        $this->assertStringNotContainsString(self::TOKEN, $record->message.json_encode($record->context, JSON_THROW_ON_ERROR));
        $this->assertSame('synthetic-request-1', $record->context['headers']['X-Request-ID']);
        $this->assertSame('safe-hash', $record->context['invitation_token_hash']);
        foreach (['single', 'daily', 'slack', 'papertrail', 'stderr', 'syslog', 'errorlog'] as $channel) {
            $this->assertContains(RedactTeamInvitationSecrets::class, config("logging.channels.{$channel}.tap", []));
        }
    }

    private function url(): string
    {
        return route('team.invitations.show', ['membership' => self::MEMBERSHIP]);
    }

    private function capture(): void
    {
        $this->get($this->url().'?token='.self::TOKEN)->assertStatus(303)->assertRedirect($this->url());
    }

    private function signIn(): void
    {
        app(PortalAccessTokenStore::class)->put(['access_token' => 'synthetic-access-token', 'refresh_token' => 'synthetic-refresh-token', 'expires_in' => 900]);
        $this->withCookie((string) config('session.cookie'), session()->getId());
    }

    private function fakeApi(?\Closure $accept = null, ?\Closure $preview = null): void
    {
        Http::fake(function (Request $request) use ($accept, $preview) {
            if (str_ends_with($request->url(), '/oauth/token')) {
                return Http::response(['access_token' => 'new-synthetic-access-token', 'refresh_token' => 'new-synthetic-refresh-token', 'expires_in' => 900]);
            }
            if (str_ends_with($request->url(), '/preview')) {
                return $preview ? $preview($request) : Http::response(['data' => $this->preview()]);
            }
            if (str_ends_with($request->url(), '/accept')) {
                return $accept ? $accept($request) : Http::response(['data' => $this->accepted()]);
            }

            return Http::response([], 500);
        });
    }

    private function preview(string $role = 'operator'): array
    {
        return ['id' => self::MEMBERSHIP, 'organization_name' => 'Synthetic Veterinary Team', 'role' => $role, 'status' => 'invited', 'expires_at' => now()->addDay()->toIso8601String()];
    }

    private function accepted(): array
    {
        return ['id' => self::MEMBERSHIP, 'role' => 'operator', 'status' => 'active', 'is_current_user' => true];
    }
}
