<?php

namespace App\Livewire\Clinical;

use App\Exceptions\PlatformApiException;
use App\Livewire\Concerns\UsesPortalWorkspace;
use App\Services\PlatformApiClient;
use App\Support\ClinicalPortalAccessTokenStore;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

class SubmissionShow extends Component
{
    use UsesPortalWorkspace;

    #[Locked]
    public string $submissionId;

    /** @var array<string, mixed> */
    public array $submission = [];

    public ?string $message = null;

    public bool $loadingFailed = false;

    public function mount(string $submissionId, PlatformApiClient $api, ClinicalPortalAccessTokenStore $tokens): void
    {
        $this->submissionId = $submissionId;
        $this->loadSubmission($api, $tokens);
    }

    public function retry(PlatformApiClient $api, ClinicalPortalAccessTokenStore $tokens): void
    {
        $this->loadSubmission($api, $tokens);
    }

    public function render(): View
    {
        return view('livewire.clinical.submission-show')
            ->layout('components.layouts.clinical', ['title' => 'Submission status · Zigpaw clinical']);
    }

    private function loadSubmission(PlatformApiClient $api, ClinicalPortalAccessTokenStore $tokens): void
    {
        $credentials = $this->portalCredentials($tokens);
        if ($credentials === null) {
            return;
        }

        [, $organizationId] = $credentials;
        $this->loadingFailed = false;
        $this->message = null;

        try {
            $this->submission = $api->clinicalSubmission($tokens, $organizationId, $this->submissionId);
        } catch (PlatformApiException $exception) {
            $this->clearPortalSessionIfUnauthorized($exception->status, $tokens);
            $this->submission = [];
            $this->loadingFailed = true;
            $this->message = match ($exception->status) {
                404 => 'This submission is no longer available to this clinical organisation.',
                default => $exception->getMessage(),
            };
        }
    }
}
