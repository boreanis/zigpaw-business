<?php

namespace App\Livewire\Clinical;

use App\Exceptions\PlatformApiException;
use App\Livewire\Concerns\UsesPortalWorkspace;
use App\Services\PlatformApiClient;
use App\Support\ClinicalPortalAccessTokenStore;
use App\Support\ClinicalSubmissionHistory;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;

class SubmissionIndex extends Component
{
    use UsesPortalWorkspace;

    /** @var list<array<string, mixed>> */
    public array $submissions = [];

    /** @var array<string, mixed> */
    public array $meta = [];

    #[Url]
    public string $status = 'all';

    #[Url]
    public int $page = 1;

    public ?string $message = null;

    public bool $loadingFailed = false;

    public function mount(PlatformApiClient $api, ClinicalPortalAccessTokenStore $tokens): void
    {
        $this->normalizeFilters();
        $this->loadSubmissions($api, $tokens);
    }

    public function filter(PlatformApiClient $api, ClinicalPortalAccessTokenStore $tokens): void
    {
        $this->validate(['status' => ['required', Rule::in(array_keys(ClinicalSubmissionHistory::STATUSES))]]);
        $this->page = 1;
        $this->loadSubmissions($api, $tokens);
    }

    public function previousPage(PlatformApiClient $api, ClinicalPortalAccessTokenStore $tokens): void
    {
        $this->page = max(1, $this->page - 1);
        $this->loadSubmissions($api, $tokens);
    }

    public function nextPage(PlatformApiClient $api, ClinicalPortalAccessTokenStore $tokens): void
    {
        $this->page = min(max(1, (int) ($this->meta['last_page'] ?? 1)), $this->page + 1);
        $this->loadSubmissions($api, $tokens);
    }

    public function retry(PlatformApiClient $api, ClinicalPortalAccessTokenStore $tokens): void
    {
        $this->loadSubmissions($api, $tokens);
    }

    public function render(): View
    {
        return view('livewire.clinical.submission-index', ['historyQuery' => ClinicalSubmissionHistory::query($this->status, $this->page)])
            ->layout('components.layouts.clinical', ['title' => 'Care submissions · Zigpaw Partners clinical']);
    }

    private function loadSubmissions(PlatformApiClient $api, ClinicalPortalAccessTokenStore $tokens): void
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
            $fetchPage = fn (int $page): array => $api->clinicalSubmissions(
                $tokens,
                $organizationId,
                $page,
                25,
                $this->status === 'all' ? null : $this->status,
            );
            $response = $fetchPage($this->page);
            $lastPage = $response['meta']['last_page'];
            if ($this->page > $lastPage) {
                $this->page = $lastPage;
                $response = $fetchPage($lastPage);
            }
            $this->submissions = $response['data'];
            $this->meta = $response['meta'];
        } catch (PlatformApiException $exception) {
            $this->clearPortalSessionIfUnauthorized($exception->status, $tokens);
            $this->submissions = [];
            $this->meta = [];
            $this->loadingFailed = true;
            $this->message = $exception->getMessage();
        }
    }

    /** @return array{string, array{status?: string, page?: int}} */
    protected function clinicalReturnTarget(): array
    {
        return ['clinical.submissions.index', ClinicalSubmissionHistory::query($this->status, $this->page)];
    }

    private function normalizeFilters(): void
    {
        $query = ClinicalSubmissionHistory::query($this->status, $this->page);
        $this->status = $query['status'] ?? 'all';
        $this->page = $query['page'] ?? 1;
    }
}
