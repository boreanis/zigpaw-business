@props(['grant'])

@if (($grant['status'] ?? null) === 'revoked')
    Access revoked
    @if (($grant['revoked_at'] ?? null) !== null)
        <x-clinical.access-deadline :value="$grant['revoked_at']" unavailable="Revocation time unavailable" />
    @endif
@elseif (($grant['status'] ?? null) === 'expired')
    Access expired
    @if (($grant['expires_at'] ?? null) !== null)
        <x-clinical.access-deadline :value="$grant['expires_at']" />
    @endif
@elseif (($grant['status'] ?? null) === 'active')
    @if (($grant['expires_at'] ?? null) !== null)
        Access expires <x-clinical.access-deadline :value="$grant['expires_at']" />
    @else
        Access has no set expiry
    @endif
@else
    Access timing unavailable
@endif
