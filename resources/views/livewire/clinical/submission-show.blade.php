<div class="page-stack page-narrow">
    <a class="back-link" href="{{ route('clinical.submissions.index', $historyQuery) }}#submission-{{ $submissionId }}" wire:navigate><span aria-hidden="true">←</span> Submission history</a>

    @if ($loadingFailed)
        <x-clinical.empty-state title="Submission status is unavailable" :description="$message" tone="error" :heading-level="1">
            <x-slot:actions><x-clinical.button wire:click="retry" wire:loading.attr="disabled" wire:target="retry">Try again</x-clinical.button></x-slot:actions>
        </x-clinical.empty-state>
    @else
        @php($status = $submission['status'] ?? null)
        <x-clinical.page-heading title="Family review status">
            <x-slot:context><x-clinical.submission-status :status="$status" /></x-slot:context>
            <x-slot:description><x-clinical.submitted-at :value="$submission['submitted_at'] ?? null" prefix="Submitted " /></x-slot:description>
        </x-clinical.page-heading>

        <section class="review-explainer">
            @if ($status === 'pending')
                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg><div><strong>Waiting for the profile manager</strong><p>The records are secure and have not changed the pet’s approved care history. The family can review each record before adding it.</p></div>
            @elseif ($status === 'approved')
                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9"/><path d="m8 12 3 3 5-6"/></svg><div><strong>Records approved</strong><p>The family accepted the records into the pet’s care history.</p></div>
            @elseif ($status === 'partially_approved')
                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9"/><path d="M8 12h8"/></svg><div><strong>Some records were approved</strong><p>The family chose which submitted records to add to the pet’s care history.</p></div>
            @elseif ($status === 'rejected')
                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9"/><path d="m9 9 6 6m0-6-6 6"/></svg><div><strong>Records were not added</strong><p>The family did not add these records to the pet’s approved care history.</p></div>
            @else
                <p>Try refreshing this page to check the family’s review.</p>
            @endif
        </section>

        <section class="plain-section">
            <x-clinical.section-heading
                eyebrow="Submitted records"
                :title="isset($submission['records_pending_review']) ? $submission['records_pending_review'].' '.str('record')->plural($submission['records_pending_review']) : 'Record totals'"
            />
            <div class="record-count-grid">
                @foreach (['visits' => 'Visits', 'weights' => 'Weights', 'vaccinations' => 'Vaccinations', 'medications' => 'Medications', 'conditions' => 'Conditions & allergies'] as $key => $label)
                    <div><strong>{{ data_get($submission, 'record_counts.'.$key, 0) }}</strong><span>{{ $label }}</span></div>
                @endforeach
            </div>
            <p class="privacy-copy">To keep shared information limited, this workspace shows the review status and record totals. The family reviews the detailed notes in their Zigpaw account.</p>
        </section>
    @endif
</div>
