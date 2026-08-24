<div class="page-stack">
    <header class="page-heading">
        <div><p class="eyebrow">Clinical provenance</p><h1>Care submissions</h1><p>Track records this organisation sent for a family to review.</p></div>
    </header>

    <x-clinical.segmented-filter
        :options="['all' => 'All', 'pending' => 'Pending', 'approved' => 'Approved', 'partially_approved' => 'Partly approved', 'rejected' => 'Not added']"
        :selected="$status"
        label="Filter submission status"
    />

    @if ($loadingFailed)
        <x-clinical.empty-state title="Submission history could not be loaded" :description="$message" tone="error">
            <x-slot:actions><x-clinical.button wire:click="retry" wire:loading.attr="disabled" wire:target="retry">Try again</x-clinical.button></x-slot:actions>
        </x-clinical.empty-state>
    @elseif ($submissions === [])
        <x-clinical.empty-state
            :title="$status === 'all' ? 'No care submissions yet' : 'No '.str($status)->replace('_', ' ').' submissions'"
            description="Submit records from an active patient grant when a family has allowed clinical contributions."
        >
            <x-slot:icon><svg viewBox="0 0 24 24" fill="none"><path d="M7 3h7l4 4v14H7z"/><path d="M14 3v5h5M10 13h5m-5 4h5"/></svg></x-slot:icon>
            <x-slot:actions><x-clinical.button variant="primary" :href="route('clinical.patients.index')" wire:navigate>View patients</x-clinical.button></x-slot:actions>
        </x-clinical.empty-state>
    @else
        <section class="submission-list" aria-label="Care submissions">
            @foreach ($submissions as $submission)
                @php
                    $submissionStatus = (string) ($submission['status'] ?? 'pending');
                    $statusTone = match ($submissionStatus) { 'approved' => 'good', 'partially_approved' => 'blue', 'rejected' => 'danger', 'pending' => 'warn', default => 'neutral' };
                    $counts = collect($submission['record_counts'] ?? [])->filter(fn ($count) => (int) $count > 0)->map(fn ($count, $type) => $count.' '.str((string) $type)->replace('_', ' '));
                @endphp
                <a href="{{ route('clinical.submissions.show', ['submissionId' => $submission['id']]) }}" wire:navigate>
                    <div class="submission-icon"><svg aria-hidden="true" viewBox="0 0 24 24" fill="none"><path d="M7 3h7l4 4v14H7z"/><path d="M14 3v5h5M10 13h5m-5 4h5"/></svg></div>
                    <div class="submission-copy"><div><strong>Care submission</strong><x-clinical.status :tone="$statusTone">{{ str($submissionStatus)->replace('_', ' ')->headline() }}</x-clinical.status></div><p>{{ $counts->isNotEmpty() ? $counts->join(' · ') : 'No record counts returned' }}</p></div>
                    <time datetime="{{ $submission['submitted_at'] ?? '' }}">{{ str((string) ($submission['submitted_at'] ?? ''))->before('T') }}</time>
                    <svg class="row-arrow" aria-hidden="true" viewBox="0 0 24 24" fill="none"><path d="m9 5 7 7-7 7"/></svg>
                </a>
            @endforeach
        </section>

        <x-clinical.pagination :current="$meta['current_page'] ?? 1" :last="$meta['last_page'] ?? 1" label="Submission pages" />
    @endif

    <div class="loading-line" wire:loading role="status">Updating submission history…</div>
</div>
