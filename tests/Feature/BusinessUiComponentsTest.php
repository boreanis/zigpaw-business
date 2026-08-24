<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class BusinessUiComponentsTest extends TestCase
{
    public function test_modal_is_closed_by_default_and_exposes_the_accessible_overlay_contract(): void
    {
        $html = Blade::render(<<<'BLADE'
            <x-business.modal id="review-modal" title="Fallback heading">
                <x-slot:heading><p class="eyebrow">Review</p><h2>Review submission</h2></x-slot:heading>
                <x-slot:body><form id="review-form"><input data-overlay-initial-focus></form></x-slot:body>
                <x-slot:footer>Changes are reviewed before publishing.</x-slot:footer>
                <x-slot:actions><button type="submit" form="review-form">Save</button></x-slot:actions>
            </x-business.modal>
        BLADE);

        $this->assertStringContainsString('id="review-modal"', $html);
        $this->assertStringContainsString('data-overlay-kind="modal"', $html);
        $this->assertStringContainsString('data-overlay-open-state="false"', $html);
        $this->assertStringContainsString('aria-hidden="true"', $html);
        $this->assertMatchesRegularExpression('/\shidden inert\s*>/', $html);
        $this->assertStringContainsString('role="dialog"', $html);
        $this->assertStringContainsString('aria-modal="true"', $html);
        $this->assertStringContainsString('aria-labelledby="review-modal-heading"', $html);
        $this->assertStringContainsString('data-overlay-panel', $html);
        $this->assertStringContainsString('data-overlay-close', $html);
        $this->assertStringContainsString('data-overlay-initial-focus', $html);
        $this->assertStringContainsString('data-overlay-scroll-body', $html);
        $this->assertStringContainsString('data-overlay-scroll-cue', $html);
        $this->assertStringContainsString('class="overlay-footer"', $html);
        $this->assertStringContainsString('class="overlay-actions"', $html);
        $this->assertSame(1, substr_count($html, 'Review submission'));

        $modal = file_get_contents(resource_path('views/components/business/modal.blade.php'));
        $drawer = file_get_contents(resource_path('views/components/business/drawer.blade.php'));
        $this->assertIsString($modal);
        $this->assertIsString($drawer);
        $this->assertStringContainsString('<x-business.overlay-close', $modal);
        $this->assertStringContainsString('<x-business.overlay-close', $drawer);
    }

    public function test_open_drawer_uses_the_same_named_slot_and_interaction_hooks(): void
    {
        $html = Blade::render(<<<'BLADE'
            <x-business.drawer id="location-drawer" :open="true" title="Edit location" eyebrow="Location" close-action="cancelEdit">
                <x-slot:body><form id="location-form"><input data-overlay-initial-focus></form></x-slot:body>
                <x-slot:actions><button type="submit" form="location-form">Save location</button></x-slot:actions>
            </x-business.drawer>
        BLADE);

        $this->assertStringContainsString('data-overlay-kind="drawer"', $html);
        $this->assertStringContainsString('data-overlay-open-state="true"', $html);
        $this->assertStringContainsString('aria-hidden="false"', $html);
        $this->assertStringNotContainsString('hidden inert', $html);
        $this->assertStringContainsString('wire:click="cancelEdit"', $html);
        $this->assertStringContainsString('form="location-form"', $html);
        $this->assertStringContainsString('data-overlay-scroll-cue', $html);
    }

    public function test_toast_region_has_live_feedback_and_dismissal_hooks(): void
    {
        $html = Blade::render(<<<'BLADE'
            <x-business.toast-region>
                <x-business.toast tone="error" :timeout="9000" dismiss-action="dismissNotice">Could not save.</x-business.toast>
            </x-business.toast-region>
        BLADE);

        $this->assertStringContainsString('data-toast-region', $html);
        $this->assertStringContainsString('aria-live="polite"', $html);
        $this->assertStringContainsString('data-toast-timeout="9000"', $html);
        $this->assertStringContainsString('role="alert"', $html);
        $this->assertStringContainsString('data-toast-close', $html);
        $this->assertStringContainsString('wire:click="dismissNotice"', $html);
    }

    public function test_business_interaction_entry_contains_keyboard_focus_scroll_and_backdrop_controls(): void
    {
        $script = file_get_contents(resource_path('js/business.js'));

        $this->assertIsString($script);
        $this->assertStringContainsString("event.key === 'Escape'", $script);
        $this->assertStringContainsString("event.key !== 'Tab'", $script);
        $this->assertStringContainsString('data-overlay-initial-focus', $script);
        $this->assertStringContainsString("document.body.style.overflow = 'hidden'", $script);
        $this->assertStringContainsString('overlay.dataset.overlayBackdropClose', $script);
        $this->assertStringContainsString('restoreTarget.focus', $script);
        $this->assertStringContainsString("this.sidebar.setAttribute('inert', '')", $script);
        $this->assertStringContainsString("this.sidebar.removeAttribute('inert')", $script);
        $this->assertStringContainsString('body.scrollHeight - body.clientHeight - body.scrollTop', $script);
        $this->assertStringContainsString('body?.scrollBy', $script);
        $this->assertStringContainsString("window.addEventListener('business:toast'", $script);
    }

    public function test_business_styles_keep_controls_and_headings_at_regular_or_light_weights(): void
    {
        $css = file_get_contents(resource_path('css/business.css'));

        $this->assertIsString($css);
        $this->assertDoesNotMatchRegularExpression('/font-weight:\s*(?:600|700|800|900|bold)\b/', $css);
        $this->assertStringContainsString('overflow-y: auto; overscroll-behavior: contain;', $css);
        $this->assertStringContainsString('.has-overlay-footer .overlay-scroll-cue { bottom: calc(var(--overlay-footer-height, 70px) / 2 - 19px); }', $css);
    }

    public function test_explicit_theme_choice_overrides_the_system_wordmark_variant(): void
    {
        $css = file_get_contents(resource_path('css/business.css'));

        $this->assertIsString($css);
        $this->assertStringContainsString(':root[data-theme="light"] .brand-light { display: block !important; }', $css);
        $this->assertStringContainsString(':root[data-theme="light"] .brand-dark { display: none !important; }', $css);
        $this->assertStringContainsString(':root[data-theme="dark"] .brand-light { display: none !important; }', $css);
        $this->assertStringContainsString(':root[data-theme="dark"] .brand-dark { display: block !important; }', $css);
    }

    public function test_business_workflows_use_shared_form_action_status_and_empty_state_components(): void
    {
        $workspace = file_get_contents(resource_path('views/livewire/business-workspace.blade.php'));

        $this->assertIsString($workspace);
        $this->assertStringContainsString('<x-business.field', $workspace);
        $this->assertStringContainsString('<x-business.button', $workspace);
        $this->assertStringContainsString('<x-business.status', $workspace);
        $this->assertStringContainsString('<x-business.empty-state', $workspace);
        $this->assertDoesNotMatchRegularExpression('/<(?:label|button|span)\b[^>]*class="(?:field|button|status)(?:\s|-")/', $workspace);
        $this->assertStringNotContainsString('<button', $workspace, 'Workflow actions must use the shared business button primitive.');
        $this->assertStringNotContainsString('<div class="empty-state">', $workspace);
    }

    public function test_consequential_booking_and_commercial_actions_require_confirmation(): void
    {
        $workspace = file_get_contents(resource_path('views/livewire/business-workspace.blade.php'));

        $this->assertIsString($workspace);
        $this->assertMatchesRegularExpression('/wire:click="acceptBooking\([^>]+wire:confirm="Accept this appointment time\?/', $workspace);
        $this->assertMatchesRegularExpression('/wire:click="completeBooking\([^>]+wire:confirm="Mark this appointment complete\?/', $workspace);
        $this->assertMatchesRegularExpression('/wire:click="applyForReferralProgram" wire:confirm="Apply to the referral program\?/', $workspace);
        $this->assertMatchesRegularExpression('/wire:click="declineBooking\([^>]+wire:confirm="Decline this booking request\?/', $workspace);
    }

    public function test_loading_and_error_states_are_shared_accessible_business_primitives(): void
    {
        $html = Blade::render(<<<'BLADE'
            <x-business.error-state title="Connection issue" message="Try again shortly." />
            <x-business.loading-state label="Refreshing bookings" />
        BLADE);

        $this->assertStringContainsString('role="alert"', $html);
        $this->assertStringContainsString('Connection issue', $html);
        $this->assertStringContainsString('role="status"', $html);
        $this->assertStringContainsString('Refreshing bookings', $html);

        $shell = file_get_contents(resource_path('views/components/business/shell.blade.php'));
        $this->assertIsString($shell);
        $this->assertStringContainsString('<x-business.loading-state', $shell);
        $this->assertStringContainsString('<x-business.error-state', $shell);
    }

    public function test_business_button_supports_shared_link_actions_and_livewire_loading(): void
    {
        $html = Blade::render(<<<'BLADE'
            <x-business.button variant="primary" href="/auth/login">Sign in</x-business.button>
            <x-business.button wire:click="refresh">Refresh</x-business.button>
        BLADE);

        $this->assertStringContainsString('href="/auth/login"', $html);
        $this->assertStringContainsString('class="button button-primary"', $html);
        $this->assertStringContainsString('wire:loading.attr="disabled"', $html);
    }

    public function test_business_button_centralizes_workflow_specific_action_styles(): void
    {
        $html = Blade::render(<<<'BLADE'
            <x-business.button variant="action">Open booking</x-business.button>
            <x-business.button variant="claim">Choose listing</x-business.button>
            <x-business.button variant="window">Accept time</x-business.button>
            <x-business.button variant="organization">Choose workspace</x-business.button>
            <x-business.button variant="theme">System</x-business.button>
            <x-business.button variant="text">Edit details</x-business.button>
        BLADE);

        $this->assertStringContainsString('class="action-row"', $html);
        $this->assertStringContainsString('class="claim-result"', $html);
        $this->assertStringContainsString('class="booking-window"', $html);
        $this->assertStringContainsString('class="organization-option"', $html);
        $this->assertStringContainsString('class="theme-control"', $html);
        $this->assertStringContainsString('class="text-action"', $html);
    }

    public function test_signed_out_workspace_keeps_appearance_control_accessible(): void
    {
        $workspace = file_get_contents(resource_path('views/livewire/business-workspace.blade.php'));

        $this->assertIsString($workspace);
        $this->assertStringContainsString('class="access-header"', $workspace);
        $this->assertStringContainsString('data-theme-toggle', $workspace);
        $this->assertStringContainsString('aria-label="Change appearance"', $workspace);

        $layout = file_get_contents(resource_path('views/components/layouts/portal.blade.php'));
        $shell = file_get_contents(resource_path('views/components/business/shell.blade.php'));
        $this->assertIsString($layout);
        $this->assertIsString($shell);
        $this->assertStringContainsString('href="#main-content"', $layout);
        $this->assertStringContainsString('id="main-content"', $shell);
    }

    public function test_workspace_content_is_the_skip_link_target_and_panels_remain_content_driven(): void
    {
        $shell = file_get_contents(resource_path('views/components/business/shell.blade.php'));
        $css = file_get_contents(resource_path('css/business.css'));

        $this->assertIsString($shell);
        $this->assertIsString($css);
        $this->assertStringContainsString('<main id="main-content" class="workspace-main">', $shell);
        $this->assertStringNotContainsString('<main id="main-content" class="business-shell">', $shell);
        $this->assertStringNotContainsString('.workspace-section { max-width: 1380px; min-height:', $css);
        $this->assertStringContainsString('.data-table {', $css);
        $this->assertStringContainsString('overflow-x: auto;', $css);
    }
}
