<?php

namespace App\Livewire\Concerns;

use App\Support\ClinicalPortalAccessTokenStore;

trait UsesPortalWorkspace
{
    public string $organizationName = '';

    /** @return array{0: string, 1: string}|null */
    protected function portalCredentials(ClinicalPortalAccessTokenStore $tokens): ?array
    {
        $accessToken = $tokens->accessToken();
        $organizationId = session('portal.organization_id');

        if (! is_string($accessToken) || $accessToken === '' || ! is_string($organizationId) || $organizationId === '') {
            $this->redirectRoute('clinical.dashboard', navigate: true);

            return null;
        }

        $organizationName = session('portal.organization_name');
        $this->organizationName = is_string($organizationName) && $organizationName !== ''
            ? $organizationName
            : 'Clinical workspace';

        return [$accessToken, $organizationId];
    }

    protected function clearPortalSessionIfUnauthorized(int $status, ClinicalPortalAccessTokenStore $tokens): void
    {
        if ($status === 401) {
            $tokens->forget();
            session()->forget(['portal.organization_id', 'portal.organization_name']);
            session()->flash('error', 'Your secure session has ended. Please sign in again.');
            $this->redirectRoute('clinical.dashboard', navigate: true);
        }
    }
}
