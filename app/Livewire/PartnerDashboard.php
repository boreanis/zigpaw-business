<?php

namespace App\Livewire;

use App\Exceptions\PlatformApiException;
use App\Services\PlatformApiClient;
use App\Support\PortalAccessTokenStore;
use Illuminate\View\View;
use Livewire\Component;

class PartnerDashboard extends Component
{
    /** @var array<string, mixed>|null */
    public ?array $partner = null;

    public string $state = 'signed_out';

    public ?string $message = null;

    public function mount(PlatformApiClient $api, PortalAccessTokenStore $tokens): void
    {
        $accessToken = $tokens->accessToken();
        if (! $accessToken) {
            return;
        }

        try {
            $this->partner = $api->get('/v1/partners/me', $accessToken, session('portal.organization_id'));
            $this->state = 'ready';
        } catch (PlatformApiException $exception) {
            if ($exception->status === 401) {
                $tokens->forget();

                return;
            }

            $this->state = $exception->status === 403 ? 'forbidden' : 'unavailable';
            $this->message = $exception->getMessage();
        }
    }

    public function render(): View
    {
        return view('livewire.partner-dashboard')->layout('components.layouts.portal');
    }
}
