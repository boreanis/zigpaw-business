<div class="page-stack">
    <x-clinical.page-heading title="Care submissions" description="Track records your team sent for family review." />

    <form class="filter-bar submission-filter" wire:submit="filter">
        <x-clinical.filter-select label="Filter submission status" wire:model="status">
            @foreach (\App\Support\ClinicalSubmissionHistory::STATUSES as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
            @endforeach
        </x-clinical.filter-select>
        <x-clinical.button type="submit" wire:loading.attr="disabled" wire:target="filter">Apply filters</x-clinical.button>
    </form>

    @if ($loadingFailed)
        <x-clinical.empty-state title="Submission history could not be loaded" :description="$message" tone="error">
            <x-slot:actions><x-clinical.button wire:click="retry" wire:loading.attr="disabled" wire:target="retry">Try again</x-clinical.button></x-slot:actions>
        </x-clinical.empty-state>
    @elseif ($submissions === [])
        <x-clinical.empty-state
            :title="$status === 'all' ? 'No care submissions yet' : 'No submissions match this filter'"
            :description="$status === 'all' ? 'Submit records from a patient profile when the family has allowed it.' : 'Choose another status to see more submissions.'"
        >
            <x-slot:icon><svg viewBox="0 0 24 24" fill="none"><path d="M7 3h7l4 4v14H7z"/><path d="M14 3v5h5M10 13h5m-5 4h5"/></svg></x-slot:icon>
            @if ($status === 'all')
                <x-slot:actions><x-clinical.button variant="primary" :href="route('clinical.patients.index')" wire:navigate>View patients</x-clinical.button></x-slot:actions>
            @endif
        </x-clinical.empty-state>
    @else
        <section class="submission-list" aria-label="Care submissions">
            @foreach ($submissions as $submission)
                @php
                    $counts = collect($submission['record_counts'] ?? [])->filter(fn ($count) => (int) $count > 0)->map(fn ($count, $type) => $count.' '.str((string) $type)->replace('_', ' '));
                @endphp
                <a id="submission-{{ $submission['id'] }}" href="{{ route('clinical.submissions.show', ['submissionId' => $submission['id']] + $historyQuery) }}" wire:navigate>
                    <div class="submission-icon"><svg aria-hidden="true" viewBox="0 0 24 24" fill="none"><path d="M7 3h7l4 4v14H7z"/><path d="M14 3v5h5M10 13h5m-5 4h5"/></svg></div>
                    <div class="submission-copy">
                        <div><strong>Care submission</strong><x-clinical.submission-status :status="$submission['status'] ?? null" /></div>
                        @if ($counts->isNotEmpty())<p>{{ $counts->join(' · ') }}</p>@endif
                    </div>
                    <x-clinical.submitted-at :value="$submission['submitted_at'] ?? null" />
                    <svg class="row-arrow" aria-hidden="true" viewBox="0 0 24 24" fill="none"><path d="m9 5 7 7-7 7"/></svg>
                </a>
            @endforeach
        </section>

        <x-clinical.pagination :current="$meta['current_page'] ?? 1" :last="$meta['last_page'] ?? 1" label="Submission pages" />
    @endif

    <div class="loading-line" wire:loading role="status">Updating submission history…</div>
</div>
