<div class="page-stack">
    <header class="page-heading">
        <div><p class="eyebrow">Clinical provenance</p><h1>Care submissions</h1><p>Track records this organisation sent for a family to review.</p></div>
    </header>

    <form class="segmented-filter" wire:submit="filter" aria-label="Filter submission status">
        @foreach (['all' => 'All', 'pending' => 'Pending', 'approved' => 'Approved', 'partially_approved' => 'Partly approved', 'rejected' => 'Not added'] as $value => $label)
            <button type="submit" wire:click="$set('status', '{{ $value }}')" @class(['active' => $status === $value])>{{ $label }}</button>
        @endforeach
    </form>

    @if ($loadingFailed)
        <section class="empty-state" role="alert"><h2>Submission history could not be loaded</h2><p>{{ $message }}</p><button class="secondary-button" type="button" wire:click="retry">Try again</button></section>
    @elseif ($submissions === [])
        <section class="empty-state">
            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none"><path d="M7 3h7l4 4v14H7z"/><path d="M14 3v5h5M10 13h5m-5 4h5"/></svg>
            <h2>{{ $status === 'all' ? 'No care submissions yet' : 'No '.$status.' submissions' }}</h2>
            <p>Submit records from an active patient grant when a family has allowed clinical contributions.</p>
            <a class="primary-button" href="{{ route('patients.index') }}" wire:navigate>View patients</a>
        </section>
    @else
        <section class="submission-list" aria-label="Care submissions">
            @foreach ($submissions as $submission)
                @php
                    $submissionStatus = (string) ($submission['status'] ?? 'pending');
                    $statusClass = match ($submissionStatus) { 'approved' => 'status-green', 'partially_approved' => 'status-blue', 'rejected' => 'status-red', 'pending' => 'status-amber', default => 'status-neutral' };
                    $counts = collect($submission['record_counts'] ?? [])->filter(fn ($count) => (int) $count > 0)->map(fn ($count, $type) => $count.' '.str((string) $type)->replace('_', ' '));
                @endphp
                <a href="{{ route('submissions.show', ['submissionId' => $submission['id']]) }}" wire:navigate>
                    <div class="submission-icon"><svg aria-hidden="true" viewBox="0 0 24 24" fill="none"><path d="M7 3h7l4 4v14H7z"/><path d="M14 3v5h5M10 13h5m-5 4h5"/></svg></div>
                    <div class="submission-copy"><div><strong>Care submission</strong><span class="status-badge {{ $statusClass }}">{{ str($submissionStatus)->replace('_', ' ')->headline() }}</span></div><p>{{ $counts->isNotEmpty() ? $counts->join(' · ') : 'No record counts returned' }}</p></div>
                    <time datetime="{{ $submission['submitted_at'] ?? '' }}">{{ str((string) ($submission['submitted_at'] ?? ''))->before('T') }}</time>
                    <svg class="row-arrow" aria-hidden="true" viewBox="0 0 24 24" fill="none"><path d="m9 5 7 7-7 7"/></svg>
                </a>
            @endforeach
        </section>

        @if ((int) ($meta['last_page'] ?? 1) > 1)
            <nav class="pagination" aria-label="Submission pages"><button class="secondary-button" type="button" wire:click="previousPage" @disabled((int) ($meta['current_page'] ?? 1) <= 1)>Previous</button><span>Page {{ $meta['current_page'] ?? 1 }} of {{ $meta['last_page'] ?? 1 }}</span><button class="secondary-button" type="button" wire:click="nextPage" @disabled((int) ($meta['current_page'] ?? 1) >= (int) ($meta['last_page'] ?? 1))>Next</button></nav>
        @endif
    @endif

    <div class="loading-line" wire:loading role="status">Updating submission history…</div>
</div>
