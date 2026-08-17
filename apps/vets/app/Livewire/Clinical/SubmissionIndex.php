<?php

namespace App\Livewire\Clinical;

use App\Exceptions\PlatformApiException;
use App\Livewire\Concerns\UsesPortalWorkspace;
use App\Services\PlatformApiClient;
use App\Support\PortalAccessTokenStore;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;

class SubmissionIndex extends Component
{
    use UsesPortalWorkspace;

    /** @var list<string> */
    private const SUBMISSION_STATUSES = ['all', 'pending', 'approved', 'partially_approved', 'rejected'];

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

    public function mount(PlatformApiClient $api, PortalAccessTokenStore $tokens): void
    {
        $this->normalizeFilters();
        $this->loadSubmissions($api, $tokens);
    }

    public function filter(PlatformApiClient $api, PortalAccessTokenStore $tokens): void
    {
        $this->validate(['status' => ['required', 'in:all,pending,approved,partially_approved,rejected']]);
        $this->page = 1;
        $this->loadSubmissions($api, $tokens);
    }

    public function previousPage(PlatformApiClient $api, PortalAccessTokenStore $tokens): void
    {
        $this->page = max(1, $this->page - 1);
        $this->loadSubmissions($api, $tokens);
    }

    public function nextPage(PlatformApiClient $api, PortalAccessTokenStore $tokens): void
    {
        $this->page = min(max(1, (int) ($this->meta['last_page'] ?? 1)), $this->page + 1);
        $this->loadSubmissions($api, $tokens);
    }

    public function retry(PlatformApiClient $api, PortalAccessTokenStore $tokens): void
    {
        $this->loadSubmissions($api, $tokens);
    }

    public function render(): View
    {
        return view('livewire.clinical.submission-index')
            ->layout('components.layouts.portal', ['title' => 'Care submissions · Zigpaw clinical']);
    }

    private function loadSubmissions(PlatformApiClient $api, PortalAccessTokenStore $tokens): void
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
            $query = ['page' => $this->page, 'per_page' => 25];
            if ($this->status !== 'all') {
                $query['status'] = $this->status;
            }

            $response = $api->submissions($accessToken, $organizationId, $query);
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

    private function normalizeFilters(): void
    {
        if (! in_array($this->status, self::SUBMISSION_STATUSES, true)) {
            $this->status = 'all';
        }

        $this->page = max(1, min(100_000, $this->page));
    }
}
