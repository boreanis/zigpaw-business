<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class ClinicalUiComponentsTest extends TestCase
{
    public function test_clinical_fields_have_stable_label_hint_and_validation_relationships(): void
    {
        $html = Blade::render(<<<'BLADE'
            <x-clinical.field label="Clinic email" name="clinicEmail" type="email" hint="Used for accountability." required />
            <x-clinical.select label="Visit type" name="visitType" required><option>Check-up</option></x-clinical.select>
            <x-clinical.textarea label="Assessment" name="assessment" hint="Share clinical facts only." />
        BLADE);

        $this->assertStringContainsString('id="clinical-clinicemail"', $html);
        $this->assertStringContainsString('for="clinical-clinicemail"', $html);
        $this->assertStringContainsString('aria-describedby="clinical-clinicemail-hint"', $html);
        $this->assertStringContainsString('aria-required="true"', $html);
        $this->assertStringContainsString('aria-invalid="false"', $html);
        $this->assertStringContainsString('for="clinical-visittype"', $html);
        $this->assertStringContainsString('for="clinical-assessment"', $html);
    }

    public function test_clinical_feedback_actions_status_and_pagination_share_components(): void
    {
        $html = Blade::render(<<<'BLADE'
            <x-clinical.empty-state title="No patients" description="Shared patients appear here." tone="error">
                <x-slot:actions><x-clinical.button wire:click="retry">Try again</x-clinical.button></x-slot:actions>
            </x-clinical.empty-state>
            <x-clinical.status tone="warn">Pending</x-clinical.status>
            <x-clinical.pagination :current="2" :last="4" label="Patient pages" />
        BLADE);

        $this->assertStringContainsString('empty-state-error', $html);
        $this->assertStringContainsString('role="alert"', $html);
        $this->assertStringContainsString('secondary-button', $html);
        $this->assertStringContainsString('status-amber', $html);
        $this->assertStringContainsString('aria-label="Patient pages"', $html);
        $this->assertStringContainsString('Page 2 of 4', $html);
        $this->assertStringContainsString('wire:click="previousPage"', $html);
        $this->assertStringContainsString('wire:click="nextPage"', $html);
    }

    public function test_clinical_workflows_use_shared_controls_and_feedback_surfaces(): void
    {
        $sources = collect([
            resource_path('views/livewire/clinical-dashboard.blade.php'),
            ...glob(resource_path('views/livewire/clinical/*.blade.php')),
        ])->mapWithKeys(fn (string $path): array => [$path => file_get_contents($path)]);

        foreach ($sources as $path => $source) {
            $this->assertIsString($source, $path);
            $this->assertStringNotContainsString('<section class="empty-state', $source, $path);
            $this->assertStringNotContainsString('<button', $source, $path);
            $this->assertDoesNotMatchRegularExpression('/<(?:a|button)\b[^>]*class="(?:primary-button|secondary-button|text-button|remove-link)"/', $source, $path);
            $this->assertDoesNotMatchRegularExpression('/<span\b[^>]*class="status-badge/', $source, $path);
        }

        $combined = $sources->join("\n");
        $this->assertStringContainsString('<x-clinical.button', $combined);
        $this->assertStringContainsString('<x-clinical.status', $combined);
        $this->assertStringContainsString('<x-clinical.empty-state', $combined);
        $this->assertStringContainsString('<x-clinical.pagination', $combined);
        $this->assertStringContainsString('<x-clinical.alert', $combined);
    }

    public function test_segmented_filter_reports_selection_to_assistive_technology(): void
    {
        $html = Blade::render(<<<'BLADE'
            <x-clinical.segmented-filter :options="['all' => 'All', 'pending' => 'Pending']" selected="pending" label="Filter submissions" />
        BLADE);

        $this->assertStringContainsString('aria-label="Filter submissions"', $html);
        $this->assertStringContainsString('aria-pressed="true"', $html);
        $this->assertStringContainsString('wire:submit="filter"', $html);
    }

    public function test_clinical_button_centralizes_workspace_and_appearance_actions(): void
    {
        $html = Blade::render(<<<'BLADE'
            <x-clinical.button variant="organization" wire:click="selectOrganization">Choose workspace</x-clinical.button>
            <x-clinical.button variant="theme" data-theme-toggle>System</x-clinical.button>
        BLADE);

        $this->assertStringContainsString('class="organization-option"', $html);
        $this->assertStringContainsString('class="theme-control"', $html);
        $this->assertStringContainsString('wire:loading.attr="disabled"', $html);
    }
}
