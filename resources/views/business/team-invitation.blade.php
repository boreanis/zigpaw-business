<x-layouts.portal title="Review team invitation · Zigpaw Business">
    <main id="main-content" class="access-shell">
        <section class="access-card">
            <p class="eyebrow">Business access</p>
            <h1>Review team invitation</h1>
            @if($errors->has('invitation'))<p class="inline-alert" role="alert">{{ $errors->first('invitation') }}</p>@endif
            @if($message)<p class="inline-alert" role="alert">{{ $message }}</p>@endif
            @if(in_array($state, ['ready', 'pending'], true) && is_array($invitation))
                <dl class="form-grid"><div><dt>Workspace</dt><dd>{{ $invitation['organization_name'] }}</dd></div><div><dt>Role</dt><dd>{{ str($invitation['expected_role'])->headline() }}</dd></div></dl>
                <p class="section-note">Invitation expires {{ \Carbon\CarbonImmutable::parse($invitation['invitation_expires_at'])->utc()->format('j M Y, H:i') }} UTC.</p>
                @if($state === 'pending')<p class="section-note" role="status">Your earlier acceptance is unconfirmed. Retry this same reviewed request, or check your workspace access before starting another one.</p>@endif
                <form method="post" action="{{ route('team.invitations.accept', ['membership' => $membership]) }}" class="form-grid">
                    @csrf
                    <input type="hidden" name="idempotency_key" value="{{ $invitation['key'] }}">
                    <label class="schedule-toggle"><input id="invitation-confirmation" type="checkbox" name="confirmed_action" value="1" required><span>I confirm joining {{ $invitation['organization_name'] }} as {{ str($invitation['expected_role'])->headline() }}.</span></label>
                    <x-business.button variant="primary" type="submit">{{ $state === 'pending' ? 'Retry same acceptance' : 'Accept team invitation' }}</x-business.button>
                </form>
            @elseif($state === 'signed_out')
                <p>Sign in with the verified account that received this invitation. You will review the workspace and role before accepting.</p>
                <x-business.button variant="primary" :href="route('auth.login', ['invitation' => $membership])">Sign in to review invitation</x-business.button>
            @elseif($state === 'unavailable')
                <x-business.button variant="primary" :href="route('team.invitations.show', ['membership' => $membership])">Reload invitation</x-business.button>
            @elseif($state === 'expired')
                <p class="section-note" role="status">This invitation session expired or is unavailable. Reopen the latest invitation email.</p>
            @endif
            <x-business.button :href="route('dashboard')">Back to Business workspace</x-business.button>
        </section>
    </main>
</x-layouts.portal>
