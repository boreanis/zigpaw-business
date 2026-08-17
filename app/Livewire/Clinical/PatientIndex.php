<?php

namespace App\Livewire\Clinical;

use App\Exceptions\PlatformApiException;
use App\Livewire\Concerns\UsesPortalWorkspace;
use App\Services\PlatformApiClient;
use App\Support\ClinicalPortalAccessTokenStore;
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

    public function mount(PlatformApiClient $api, ClinicalPortalAccessTokenStore $tokens): void
    {
        $this->normalizeFilters();
        $this->loadPatients($api, $tokens);
    }

    public function applyFilters(PlatformApiClient $api, ClinicalPortalAccessTokenStore $tokens): void
    {
        $this->validate([
            'status' => ['required', 'in:active,expired,revoked,all'],
            'search' => ['nullable', 'string', 'max:80'],
        ]);

        $this->normalizeFilters();
        $this->page = 1;
        $this->loadPatients($api, $tokens);
    }

    public function clearSearch(PlatformApiClient $api, ClinicalPortalAccessTokenStore $tokens): void
    {
        $this->search = '';
        $this->page = 1;
        $this->loadPatients($api, $tokens);
    }

    public function previousPage(PlatformApiClient $api, ClinicalPortalAccessTokenStore $tokens): void
    {
        $this->page = max(1, $this->page - 1);
        $this->loadPatients($api, $tokens);
    }

    public function nextPage(PlatformApiClient $api, ClinicalPortalAccessTokenStore $tokens): void
    {
        $lastPage = max(1, (int) ($this->meta['last_page'] ?? 1));
        $this->page = min($lastPage, $this->page + 1);
        $this->loadPatients($api, $tokens);
    }

    public function retry(PlatformApiClient $api, ClinicalPortalAccessTokenStore $tokens): void
    {
        $this->loadPatients($api, $tokens);
    }

    public function render(): View
    {
        return view('livewire.clinical.patient-index')
            ->layout('components.layouts.clinical', ['title' => 'Patients · Zigpaw clinical']);
    }

    private function loadPatients(PlatformApiClient $api, ClinicalPortalAccessTokenStore $tokens): void
    {
        $this->normalizeFilters();

        $credentials = $this->portalCredentials($tokens);
        if ($credentials === null) {
            return;
        }

        [, $organizationId] = $credentials;
        $this->loadingFailed = false;
        $this->message = null;

        try {
            $response = $api->clinicalProviderGrants(
                $tokens,
                $organizationId,
                $this->page,
                24,
                $this->status,
                trim($this->search),
            );
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
