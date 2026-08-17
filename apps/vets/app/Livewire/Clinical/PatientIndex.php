<?php

namespace App\Livewire\Clinical;

use App\Exceptions\PlatformApiException;
use App\Livewire\Concerns\UsesPortalWorkspace;
use App\Services\PlatformApiClient;
use App\Support\PortalAccessTokenStore;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;

class PatientIndex extends Component
{
    use UsesPortalWorkspace;

    /** @var list<string> */
    private const GRANT_STATUSES = ['active', 'expired', 'revoked', 'all'];

    /** @var list<array<string, mixed>> */
    public array $grants = [];

    /** @var array<string, mixed> */
    public array $meta = [];

    #[Url]
    public string $status = 'active';

    #[Url]
    public string $search = '';

    #[Url]
    public int $page = 1;

    public ?string $message = null;

    public bool $loadingFailed = false;

    public function mount(PlatformApiClient $api, PortalAccessTokenStore $tokens): void
    {
        $this->normalizeFilters();
        $this->loadPatients($api, $tokens);
    }

    public function applyFilters(PlatformApiClient $api, PortalAccessTokenStore $tokens): void
    {
        $this->validate([
            'status' => ['required', 'in:active,expired,revoked,all'],
            'search' => ['nullable', 'string', 'max:80'],
        ]);

        $this->normalizeFilters();
        $this->page = 1;
        $this->loadPatients($api, $tokens);
    }

    public function clearSearch(PlatformApiClient $api, PortalAccessTokenStore $tokens): void
    {
        $this->search = '';
        $this->page = 1;
        $this->loadPatients($api, $tokens);
    }

    public function previousPage(PlatformApiClient $api, PortalAccessTokenStore $tokens): void
    {
        $this->page = max(1, $this->page - 1);
        $this->loadPatients($api, $tokens);
    }

    public function nextPage(PlatformApiClient $api, PortalAccessTokenStore $tokens): void
    {
        $lastPage = max(1, (int) ($this->meta['last_page'] ?? 1));
        $this->page = min($lastPage, $this->page + 1);
        $this->loadPatients($api, $tokens);
    }

    public function retry(PlatformApiClient $api, PortalAccessTokenStore $tokens): void
    {
        $this->loadPatients($api, $tokens);
    }

    public function render(): View
    {
        return view('livewire.clinical.patient-index')
            ->layout('components.layouts.portal', ['title' => 'Patients · Zigpaw clinical']);
    }

    private function loadPatients(PlatformApiClient $api, PortalAccessTokenStore $tokens): void
    {
        $this->normalizeFilters();

        $credentials = $this->portalCredentials($tokens);
        if ($credentials === null) {
            return;
        }

        [$accessToken, $organizationId] = $credentials;
        $this->loadingFailed = false;
        $this->message = null;

        try {
            $response = $api->providerGrants($accessToken, $organizationId, [
                'status' => $this->status,
                'search' => trim($this->search),
                'page' => $this->page,
                'per_page' => 24,
            ]);
            $this->grants = $response['data'];
            $this->meta = $response['meta'];
        } catch (PlatformApiException $exception) {
            $this->clearPortalSessionIfUnauthorized($exception->status, $tokens);
            $this->grants = [];
            $this->meta = [];
            $this->loadingFailed = true;
            $this->message = $exception->getMessage();
        }
    }

    private function normalizeFilters(): void
    {
        if (! in_array($this->status, self::GRANT_STATUSES, true)) {
            $this->status = 'active';
        }

        $this->page = max(1, min(100_000, $this->page));
        $this->search = mb_substr(trim($this->search), 0, 80);

        if ($this->status !== 'active') {
            $this->search = '';
        }
    }
}
