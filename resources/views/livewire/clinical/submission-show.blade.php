<div class="page-stack page-narrow">
    <a class="back-link" href="{{ route('clinical.submissions.index') }}" wire:navigate><span aria-hidden="true">←</span> Submission history</a>

    @if ($loadingFailed)
        <x-clinical.empty-state title="Submission status is unavailable" :description="$message" tone="error" :heading-level="1">
            <x-slot:actions><x-clinical.button wire:click="retry" wire:loading.attr="disabled" wire:target="retry">Try again</x-clinical.button></x-slot:actions>
        </x-clinical.empty-state>
    @else
        @php
            $status = (string) ($submission['status'] ?? 'pending');
            $statusTone = match ($status) { 'approved' => 'good', 'partially_approved' => 'blue', 'rejected' => 'danger', 'pending' => 'warn', default => 'neutral' };
        @endphp
        <header class="page-heading submission-heading">
            <div><div class="heading-with-status"><p class="eyebrow">Care submission</p><x-clinical.status :tone="$statusTone">{{ str($status)->replace('_', ' ')->headline() }}</x-clinical.status></div><h1>Family review status</h1><p>Submitted {{ str((string) ($submission['submitted_at'] ?? ''))->replace('T', ' · ')->before('+') }}</p></div>
        </header>

        <section class="review-explainer">
            @if ($status === 'pending')
                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg><div><strong>Waiting for the profile manager</strong><p>The records are secure and have not changed the pet’s approved care history. The family can review each record before adding it.</p></div>
            @elseif ($status === 'approved')
                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9"/><path d="m8 12 3 3 5-6"/></svg><div><strong>Records approved</strong><p>The family accepted the records into the pet’s care history.</p></div>
            @elseif ($status === 'partially_approved')
                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9"/><path d="M8 12h8"/></svg><div><strong>Some records were approved</strong><p>The family chose which submitted records to add to the pet’s care history.</p></div>
            @else
                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9"/><path d="m9 9 6 6m0-6-6 6"/></svg><div><strong>Records were not added</strong><p>The family did not add these records to the pet’s approved care history.</p></div>
            @endif
        </section>

        <section class="plain-section">
            <div class="section-heading-inline"><div><p class="eyebrow">Submitted record set</p><h2>{{ $submission['records_pending_review'] ?? 0 }} records</h2></div><span>Provenance preserved</span></div>
            <div class="record-count-grid">
                @foreach (['visits' => 'Visits', 'weights' => 'Weights', 'vaccinations' => 'Vaccinations', 'medications' => 'Medications', 'conditions' => 'Conditions & allergies'] as $key => $label)
                    <div><strong>{{ data_get($submission, 'record_counts.'.$key, 0) }}</strong><span>{{ $label }}</span></div>
                @endforeach
            </div>
            <p class="privacy-copy">To keep shared information limited, this workspace shows the review status and record totals. The family reviews the detailed notes in their Zigpaw account.</p>
        </section>
    @endif
</div>
