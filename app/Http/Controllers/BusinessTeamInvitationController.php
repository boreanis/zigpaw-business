<?php

namespace App\Http\Controllers;

use App\Exceptions\PlatformApiException;
use App\Services\PlatformApiClient;
use App\Support\PlatformConfiguration;
use App\Support\PortalAccessTokenStore;
use App\Support\TeamInvitationContinuation;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use RuntimeException;

final class BusinessTeamInvitationController
{
    public function show(Request $request, string $membership, TeamInvitationContinuation $continuations, PortalAccessTokenStore $tokens, PlatformApiClient $api): Response|RedirectResponse
    {
        abort_unless(PlatformConfiguration::isSafe(), 503);
        if (! hash_equals(rtrim((string) config('app.url'), '/'), $request->getSchemeAndHttpHost())) {
            return redirect()->away(rtrim((string) config('app.url'), '/').'/team/invitations/'.$membership, 303)->header('Referrer-Policy', 'no-referrer');
        }
        $state = 'expired';
        $message = null;
        $invitation = null;
        if ($request->query->has('token')) {
            try {
                $token = $request->query('token');
                if (! is_string($token)) {
                    throw new RuntimeException('This invitation link is not valid. Reopen the latest invitation email.');
                }
                $continuations->capture($membership, $token);
            } catch (RuntimeException $exception) {
                return $this->page($membership, 'unavailable', $exception->getMessage());
            }

            return redirect()->route('team.invitations.show', ['membership' => $membership], 303)->header('Referrer-Policy', 'no-referrer');
        }
        $entry = $continuations->entry($membership);
        if ($entry !== null) {
            $accessToken = $tokens->accessToken();
            $state = 'signed_out';
            if ($accessToken !== null) {
                try {
                    $actor = $this->actor($request);
                    if ($entry['attempted'] ?? false) {
                        $invitation = $continuations->operation($membership, $actor, (string) ($entry['key'] ?? ''));
                        $state = 'pending';
                    } else {
                        $preview = $this->preview($api, $accessToken, $membership, $entry['token']);
                        $invitation = $continuations->prepare($membership, $actor, $preview);
                        $state = 'ready';
                    }
                } catch (PlatformApiException $exception) {
                    if ($exception->status === 401) {
                        $tokens->forget();
                        $state = 'signed_out';
                    } else {
                        $state = 'unavailable';
                        $message = $this->failureMessage($exception);
                    }
                } catch (RuntimeException $exception) {
                    $state = 'unavailable';
                    $message = $exception->getMessage();
                }
            }
        }

        return $this->page($membership, $state, $message, $invitation);
    }

    public function accept(Request $request, string $membership, TeamInvitationContinuation $continuations, PortalAccessTokenStore $tokens, PlatformApiClient $api): RedirectResponse
    {
        // Validate only safe form controls; the invitation token never enters browser input or flashed data.
        $confirmed = in_array($request->input('confirmed_action'), ['1', 1, true, 'yes', 'on'], true);
        $key = $request->input('idempotency_key');
        if (! $confirmed || ! is_string($key)) {
            return $this->back($membership, 'Review the named workspace and role, then explicitly confirm this invitation.');
        }
        $accessToken = $tokens->accessToken();
        if ($accessToken === null) {
            return $this->back($membership, 'Sign in before accepting this invitation.');
        }
        try {
            $entry = $continuations->operation($membership, $this->actor($request), $key);
            if (! ($entry['attempted'] ?? false)) {
                $preview = $this->preview($api, $accessToken, $membership, $entry['token']);
                if (! $continuations->previewMatches($entry, $preview)) {
                    return $this->back($membership, 'This invitation changed. Review its current workspace and role before accepting.');
                }
            }
            $continuations->markAttempted($membership);
            $result = $api->acceptTeamInvitation($accessToken, $membership, $entry['token'], $entry['expected_role'], $key);
            if (($result['id'] ?? null) !== $membership || ($result['status'] ?? null) !== 'active'
                || ($result['is_current_user'] ?? null) !== true || ($result['role'] ?? null) !== $entry['expected_role']) {
                throw new PlatformApiException(502, 'The invitation acceptance could not be confirmed.');
            }
            $continuations->forget($membership);
        } catch (PlatformApiException $exception) {
            if ($exception->status === 401) {
                $tokens->forget();
            }
            if ($exception->status === 422 && $continuations->entry($membership) !== null) {
                $continuations->resetRejected($membership);
            }

            return $this->back($membership, $this->failureMessage($exception, acceptance: true));
        } catch (RuntimeException $exception) {
            return $this->back($membership, $exception->getMessage());
        }

        return redirect()->route('dashboard')->with('status', 'Team invitation accepted. Choose your workspace to continue.')->header('Referrer-Policy', 'no-referrer');
    }

    /** @return array<string, mixed> */
    private function preview(PlatformApiClient $api, string $accessToken, string $membership, #[\SensitiveParameter] string $token): array
    {
        $preview = $api->previewTeamInvitation($accessToken, $membership, $token);
        if (($preview['id'] ?? null) !== $membership || ($preview['status'] ?? null) !== 'invited'
            || ! is_string($preview['organization_name'] ?? null) || trim($preview['organization_name']) === '' || strlen($preview['organization_name']) > 255
            || ! is_string($preview['role'] ?? null) || trim($preview['role']) === '' || strlen($preview['role']) > 80
            || ! is_string($preview['expires_at'] ?? null)) {
            throw new PlatformApiException(502, 'The invitation details could not be confirmed.');
        }
        try {
            if (! CarbonImmutable::parse($preview['expires_at'])->isFuture()) {
                throw new RuntimeException('This invitation expired. Ask for a new invitation.');
            }
        } catch (\InvalidArgumentException) {
            throw new PlatformApiException(502, 'The invitation expiry could not be confirmed.');
        }

        return array_intersect_key($preview, array_flip(['id', 'organization_name', 'role', 'status', 'expires_at']));
    }

    private function actor(Request $request): string
    {
        $handle = $request->session()->get('platform.oauth.token_handle');

        return is_string($handle) ? $handle : '';
    }

    private function failureMessage(PlatformApiException $exception, bool $acceptance = false): string
    {
        return match ($exception->status) {
            401 => 'Your Business sign-in ended. Sign in again to review this invitation.',
            403 => 'This account cannot use this invitation or Business access is not available. Check the intended account and current access.',
            404, 422 => 'This invitation is unavailable or its role changed. Check the intended account and review the latest invitation before accepting.',
            default => $acceptance
                ? 'The acceptance outcome could not be confirmed. Retry only the same reviewed request or check your workspace access before starting another one.'
                : 'The invitation details are temporarily unavailable. Reload this invitation to review its current workspace and role.',
        };
    }

    /** @param array<string, mixed>|null $invitation */
    private function page(string $membership, string $state, ?string $message = null, ?array $invitation = null): Response
    {
        $invitation = is_array($invitation) ? array_intersect_key($invitation, array_flip(['organization_name', 'expected_role', 'key', 'invitation_expires_at'])) : null;

        return response()->view('business.team-invitation', compact('membership', 'state', 'message', 'invitation'))->header('Referrer-Policy', 'no-referrer');
    }

    private function back(string $membership, string $message): RedirectResponse
    {
        return redirect()->route('team.invitations.show', ['membership' => $membership])->withErrors(['invitation' => $message])->header('Referrer-Policy', 'no-referrer');
    }
}
