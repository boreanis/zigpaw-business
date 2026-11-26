<?php

namespace App\Livewire\Clinical;

use App\Exceptions\PlatformApiException;
use App\Livewire\Concerns\UsesPortalWorkspace;
use App\Services\PlatformApiClient;
use App\Support\ClinicalPatientSearch;
use App\Support\ClinicalPortalAccessTokenStore;
use App\Support\ClinicalReturnDestination;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

class PatientIndex extends Component
{
    use UsesPortalWorkspace;

    /** @var list<array<string, mixed>> */
    public array $grants = [];

    /** @var array<string, mixed> */
    public array $meta = [];

    #[Url]
    public string $status = 'active';

    public string $search = '';

    #[Locked]
    public ?string $appliedSearch = null;

    #[Locked]
    public ?string $mountedOrganization = null;

    #[Url]
    public int $page = 1;

    public bool $searchExpired = false;

    public bool $searchNeedsInput = false;

    public ?string $message = null;

    public bool $loadingFailed = false;

    protected function clinicalReturnTarget(): array
    {
        return ['clinical.patients.index', $this->status];
    }

    public function mount(PlatformApiClient $api, ClinicalPortalAccessTokenStore $tokens): void
    {
        $this->normalizeFilters();
        $this->loadPatients($api, $tokens);
    }

    public function applyFilters(PlatformApiClient $api, ClinicalPortalAccessTokenStore $tokens): void
    {
        $name = $this->status === 'active' ? trim($this->search) : '';
        $this->search = '';
        $this->validate(['status' => ['required', Rule::in(ClinicalReturnDestination::PATIENT_STATUSES)]]);
        Validator::make(['search' => $name], ['search' => ['nullable', 'string', 'max:80']])->validate();

        $organizationId = $this->currentOrganization($tokens);
        if ($organizationId === null) {
            return;
        }

        $this->page = 1;
        try {
            $this->clearAppliedSearch($organizationId);
            $this->appliedSearch = $name === '' ? null : app(ClinicalPatientSearch::class)->apply($organizationId, $name);
        } catch (PlatformApiException $exception) {
            $this->clearPortalSessionIfUnauthorized($exception->status, $tokens);
            $this->showSearchStorageFailure($exception, needsInput: $name !== '');

            return;
        }
        $this->loadPatients($api, $tokens);
    }

    public function clearSearch(PlatformApiClient $api, ClinicalPortalAccessTokenStore $tokens): void
    {
        $organizationId = $this->currentOrganization($tokens);
        if ($organizationId === null) {
            return;
        }

        $this->page = 1;
        try {
            $this->clearAppliedSearch($organizationId);
        } catch (PlatformApiException $exception) {
            $this->showSearchStorageFailure($exception, needsInput: false);

            return;
        }
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
            ->layout('components.layouts.clinical', ['title' => 'Patients · Zigpaw Partners clinical']);
    }

    private function loadPatients(PlatformApiClient $api, ClinicalPortalAccessTokenStore $tokens): void
    {
        $this->normalizeFilters();

        $organizationId = $this->currentOrganization($tokens);
        if ($organizationId === null) {
            return;
        }

        $this->loadingFailed = false;
        $this->searchExpired = false;
        $this->searchNeedsInput = false;
        $this->message = null;

        try {
            $name = $this->appliedSearch === null
                ? null
                : app(ClinicalPatientSearch::class)->resolve($organizationId, $this->appliedSearch);
            if ($this->appliedSearch !== null && $name === null) {
                $this->grants = [];
                $this->meta = [];
                $this->searchExpired = true;

                return;
            }

            $fetchPage = fn (int $page): array => $name === null
                ? $api->clinicalProviderGrants($tokens, $organizationId, $page, 24, $this->status)
                : $api->searchClinicalProviderGrants($tokens, $organizationId, $name, $page, 24);
            $response = $fetchPage($this->page);
            $lastPage = max(1, $response['meta']['last_page']);
            if ($response['data'] === [] && $this->page > $lastPage) {
                $this->page = $lastPage;
                $response = $fetchPage($this->page);
            }
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
        if (! in_array($this->status, ClinicalReturnDestination::PATIENT_STATUSES, true)) {
            $this->status = 'active';
        }

        $this->page = max(1, min(100_000, $this->page));
        $this->search = '';
    }

    private function currentOrganization(ClinicalPortalAccessTokenStore $tokens): ?string
    {
        $credentials = $this->portalCredentials($tokens);
        if ($credentials === null) {
            $this->grants = [];
            $this->meta = [];
            $this->search = '';

            return null;
        }

        [, $organizationId] = $credentials;
        if ($this->mountedOrganization !== null && $this->mountedOrganization !== $organizationId) {
            try {
                $this->clearAppliedSearch($this->mountedOrganization);
            } catch (PlatformApiException) {
                // The revoked component handle stays isolated and the encrypted entry expires.
            }
            $this->grants = [];
            $this->meta = [];
            $this->search = '';
            $this->redirectRoute('clinical.patients.index', navigate: true);

            return null;
        }

        $this->mountedOrganization = $organizationId;

        return $organizationId;
    }

    private function clearAppliedSearch(string $organizationId): void
    {
        $previousSearch = $this->appliedSearch;
        $this->appliedSearch = null;
        if ($previousSearch !== null) {
            app(ClinicalPatientSearch::class)->clear($organizationId, $previousSearch);
        }
    }

    private function showSearchStorageFailure(PlatformApiException $exception, bool $needsInput): void
    {
        $this->grants = [];
        $this->meta = [];
        $this->search = '';
        $this->searchExpired = false;
        $this->searchNeedsInput = $needsInput;
        $this->loadingFailed = ! $needsInput;
        $this->message = $exception->getMessage();
    }
}
