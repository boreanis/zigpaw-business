<?php

namespace App\Livewire\Clinical;

use App\Exceptions\PlatformApiException;
use App\Livewire\Concerns\UsesPortalWorkspace;
use App\Services\PlatformApiClient;
use App\Support\ClinicalPortalAccessTokenStore;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

class PatientShow extends Component
{
    use UsesPortalWorkspace;

    #[Locked]
    public string $grantId;

    /** @var array<string, mixed> */
    public array $grant = [];

    /** @var array<string, mixed> */
    public array $care = [];

    /** @var list<array<string, mixed>> */
    public array $media = [];

    public ?string $message = null;

    public bool $loadingFailed = false;

    public function mount(string $grantId, PlatformApiClient $api, ClinicalPortalAccessTokenStore $tokens): void
    {
        $this->grantId = $grantId;
        $this->loadPatient($api, $tokens);
    }

    public function retry(PlatformApiClient $api, ClinicalPortalAccessTokenStore $tokens): void
    {
        $this->loadPatient($api, $tokens);
    }

    public function render(): View
    {
        $petName = data_get($this->grant, 'pet.name');
        $title = is_string($petName) && $petName !== '' ? $petName.' · Zigpaw clinical' : 'Patient · Zigpaw clinical';

        return view('livewire.clinical.patient-show')
            ->layout('components.layouts.clinical', ['title' => $title]);
    }

    private function loadPatient(PlatformApiClient $api, ClinicalPortalAccessTokenStore $tokens): void
    {
        $credentials = $this->portalCredentials($tokens);
        if ($credentials === null) {
            return;
        }

        [, $organizationId] = $credentials;
        $this->loadingFailed = false;
        $this->message = null;
        $this->care = [];
        $this->media = [];

        try {
            $this->grant = $api->clinicalProviderGrant($tokens, $organizationId, $this->grantId);
            $capabilities = is_array($this->grant['capabilities'] ?? null) ? $this->grant['capabilities'] : [];

            if (($capabilities['read_care'] ?? false) === true) {
                $this->care = $api->clinicalCareContext($tokens, $organizationId, $this->grantId);
            }

            if (($capabilities['read_media'] ?? false) === true) {
                $this->media = $api->clinicalProviderMedia($tokens, $organizationId, $this->grantId);
            }
        } catch (PlatformApiException $exception) {
            $this->clearPortalSessionIfUnauthorized($exception->status, $tokens);
            $this->grant = [];
            $this->care = [];
            $this->media = [];
            $this->loadingFailed = true;
            $this->message = match ($exception->status) {
                403 => 'This family has not granted this clinical team access to that information.',
                404 => 'This patient grant is no longer available to this clinical team.',
                default => $exception->getMessage(),
            };
        }
    }
}
