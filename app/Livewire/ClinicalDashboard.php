<?php

namespace App\Livewire;

use App\Exceptions\PlatformApiException;
use App\Services\PlatformApiClient;
use App\Support\ClinicalPortalAccessTokenStore;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

class ClinicalDashboard extends Component
{
    /** @var array<string, mixed>|null */
    public ?array $identity = null;

    public string $state = 'signed_out';

    public ?string $message = null;

    /** @var list<array<string, mixed>> */
    #[Locked]
    public array $organizations = [];

    /** @var array<string, array{current_page: int, last_page: int, per_page: int, total: int}> */
    public array $pagination = [];

    public string $organizationSearch = '';

    /** @var array<string, mixed> */
    public array $overview = [];

    public function mount(PlatformApiClient $api, ClinicalPortalAccessTokenStore $tokens): void
    {
        $this->load($api, $tokens);
    }

    public function selectOrganization(string $organizationId, PlatformApiClient $api, ClinicalPortalAccessTokenStore $tokens): void
    {
        $organization = collect($this->organizations)->firstWhere('id', $organizationId);
        abort_unless(is_array($organization), 403);

        session()->put([
            'portal.organization_id' => $organizationId,
            'portal.organization_name' => (string) ($organization['name'] ?? 'Clinical workspace'),
        ]);

        $this->load($api, $tokens);
    }

    public function changeOrganization(): void
    {
        session()->forget(['portal.organization_id', 'portal.organization_name']);
        $this->redirectRoute('clinical.dashboard', navigate: true);
    }

    public function searchOrganizations(PlatformApiClient $api, ClinicalPortalAccessTokenStore $tokens): void
    {
        $this->validate(['organizationSearch' => ['nullable', 'string', 'max:80']]);
        $this->changePage('organizations', 1, $api, $tokens);
    }

    public function changePage(string $resource, int $page, PlatformApiClient $api, ClinicalPortalAccessTokenStore $tokens): void
    {
        abort_unless($resource === 'organizations', 404);
        try {
            $response = $api->clinicalOrganizations($tokens, max(1, $page), $this->organizationSearch);
            $this->organizations = $response['data'];
            $this->pagination['organizations'] = $response['meta'];
            $this->message = null;
        } catch (PlatformApiException $exception) {
            $this->message = $exception->getMessage();
        }
    }

    public function render(): View
    {
        return view('livewire.clinical-dashboard')->layout('components.layouts.clinical');
    }

    private function load(PlatformApiClient $api, ClinicalPortalAccessTokenStore $tokens): void
    {
        $this->identity = null;
        $this->overview = [];
        $this->message = null;
        $accessToken = $tokens->accessToken();

        if (! is_string($accessToken) || $accessToken === '') {
            $this->state = 'signed_out';

            return;
        }

        try {
            $response = $api->clinicalOrganizations($tokens);
            $this->organizations = $response['data'];
            $this->pagination['organizations'] = $response['meta'];
            $organizationId = session('portal.organization_id');

            if (is_string($organizationId) && $organizationId !== '' && ! collect($this->organizations)->contains('id', $organizationId)) {
                try {
                    $selected = $api->clinicalOrganization($tokens, $organizationId);
                    if (($selected['id'] ?? null) !== $organizationId) {
                        throw new PlatformApiException(502, 'The selected organisation could not be loaded. Please choose it again.');
                    }
                } catch (PlatformApiException $exception) {
                    if (! in_array($exception->status, [403, 404], true)) {
                        throw $exception;
                    }
                    $organizationId = null;
                    session()->forget(['portal.organization_id', 'portal.organization_name']);
                }
            }

            if ($response['meta']['total'] === 1) {
                $organizationId = (string) $this->organizations[0]['id'];
                session()->put('portal.organization_id', $organizationId);
            }

            if (! is_string($organizationId) || $organizationId === '') {
                $this->state = $this->organizations === [] ? 'forbidden' : 'choose_organization';

                return;
            }

            $this->identity = $api->clinicalIdentity($tokens, $organizationId);
            $this->overview = $api->clinicalDashboard($tokens, $organizationId);
            $organizationName = data_get($this->identity, 'organization.name');
            if (is_string($organizationName) && $organizationName !== '') {
                session()->put('portal.organization_name', $organizationName);
            }
            $this->state = 'ready';
        } catch (PlatformApiException $exception) {
            if ($exception->status === 401) {
                $tokens->forget();
                session()->forget(['portal.organization_id', 'portal.organization_name']);
                $this->state = 'signed_out';

                return;
            }

            $this->state = $exception->status === 403 ? 'forbidden' : 'unavailable';
            $this->message = $exception->getMessage();
        }
    }
}
