<?php

namespace App\Livewire;

use App\Exceptions\PlatformApiException;
use App\Services\PlatformApiClient;
use App\Support\PortalAccessTokenStore;
use Illuminate\View\View;
use Livewire\Component;

class VetDashboard extends Component
{
    /** @var array<string, mixed>|null */
    public ?array $identity = null;

    public string $state = 'signed_out';

    public ?string $message = null;

    /** @var list<array<string, mixed>> */
    public array $organizations = [];

    /** @var array<string, mixed> */
    public array $overview = [];

    public function mount(PlatformApiClient $api, PortalAccessTokenStore $tokens): void
    {
        $accessToken = $tokens->accessToken();
        if (! $accessToken) {
            session()->forget(['portal.organization_id', 'portal.organization_name']);

            return;
        }

        try {
            $this->organizations = $api->organizations($accessToken);
            $organizationId = session('portal.organization_id');

            if (! collect($this->organizations)->contains('id', $organizationId)) {
                session()->forget('portal.organization_id');
                $organizationId = null;
            }

            if (count($this->organizations) === 1) {
                $organizationId = (string) $this->organizations[0]['id'];
                session()->put('portal.organization_id', $organizationId);
            }

            if (! is_string($organizationId) || $organizationId === '') {
                $this->state = $this->organizations === [] ? 'forbidden' : 'choose_organization';

                return;
            }

            $this->identity = $api->identity($accessToken, $organizationId);
            $this->overview = $api->dashboard($accessToken, $organizationId);
            $organizationName = data_get($this->identity, 'organization.name');
            if (is_string($organizationName) && $organizationName !== '') {
                session()->put('portal.organization_name', $organizationName);
            }
            $this->state = 'ready';
        } catch (PlatformApiException $exception) {
            if ($exception->status === 401) {
                $tokens->forget();
                session()->forget(['portal.organization_id', 'portal.organization_name']);

                return;
            }

            $this->state = $exception->status === 403 ? 'forbidden' : 'unavailable';
            $this->message = $exception->getMessage();
        }
    }

    public function selectOrganization(string $organizationId): void
    {
        $organization = collect($this->organizations)->firstWhere('id', $organizationId);
        abort_unless(is_array($organization), 403);
        session()->put('portal.organization_id', $organizationId);
        session()->put('portal.organization_name', (string) ($organization['name'] ?? 'Clinical workspace'));
        $this->redirectRoute('dashboard', navigate: true);
    }

    public function changeOrganization(): void
    {
        session()->forget(['portal.organization_id', 'portal.organization_name']);
        $this->redirectRoute('dashboard', navigate: true);
    }

    public function render(): View
    {
        return view('livewire.vet-dashboard')->layout('components.layouts.portal');
    }
}
