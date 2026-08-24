<div class="page-stack form-page">
    <a class="back-link" href="{{ route('clinical.patients.show', ['grantId' => $grantId]) }}" wire:navigate><span aria-hidden="true">←</span> {{ data_get($grant, 'pet.name', 'Patient') }}</a>

    <header class="page-heading">
        <div><p class="eyebrow">Pending family review</p><h1>Submit care records for {{ data_get($grant, 'pet.name', 'this patient') }}</h1><p>Enter the visit once. Zigpaw separates each record so the profile manager can review it clearly.</p></div>
    </header>

    @if (! $canSubmit)
        <x-clinical.alert title="Submission is not available" :description="$message ?: 'This grant does not include permission to submit clinical records.'" tone="error" />
    @else
        <form class="clinical-form" wire:submit="submit" novalidate>
            @error('submission')<x-clinical.alert title="The submission could not be sent" :description="$message" tone="error" />@enderror

            <section class="form-section">
                <div class="form-section-heading"><div><p class="eyebrow">Visit context</p><h2>Clinician and practice</h2></div><span>Required</span></div>
                <div class="form-grid form-grid-two">
                    <x-clinical.field label="Veterinarian name" name="vetName" wire:model="vetName" required autocomplete="name" />
                    <x-clinical.field label="Clinic name" name="clinicName" wire:model="clinicName" required autocomplete="organization" />
                    <x-clinical.field label="Clinic email" name="clinicEmail" wire:model="clinicEmail" type="email" required autocomplete="email" hint="Used as the accountable contact on this submission." />
                    <x-clinical.field label="Clinic phone" name="clinicPhone" wire:model="clinicPhone" type="tel" autocomplete="tel" />
                </div>
                @if ($locations !== [])
                    <p class="context-note">Organisation locations: {{ collect($locations)->pluck('name')->filter()->join(', ') }}. The family’s grant remains tied to {{ data_get($grant, 'location.name', 'the organisation') }}.</p>
                @endif
            </section>

            <section class="form-section">
                <div class="form-section-heading"><div><p class="eyebrow">Visit</p><h2>What happened today</h2></div><span>Required fields marked *</span></div>
                <div class="form-grid form-grid-three">
                    <x-clinical.field label="Visit date" name="visitDate" wire:model="visitDate" type="date" required max="{{ now()->toDateString() }}" />
                    <x-clinical.select label="Visit type" name="visitType" wire:model="visitType" required>
                        <option value="checkup">Check-up</option><option value="vaccination">Vaccination</option><option value="health_issue">Health issue</option><option value="procedure">Procedure</option><option value="emergency">Emergency</option><option value="follow_up">Follow-up</option><option value="other">Other</option>
                    </x-clinical.select>
                    <x-clinical.select label="Prognosis" name="prognosis" wire:model="prognosis"><option value="">Not recorded</option><option value="excellent">Excellent</option><option value="good">Good</option><option value="fair">Fair</option><option value="guarded">Guarded</option><option value="poor">Poor</option></x-clinical.select>
                </div>
                <x-clinical.textarea label="Reason for visit" name="visitReason" wire:model="visitReason" rows="3" />
            </section>

            <section class="form-section">
                <div class="form-section-heading"><div><p class="eyebrow">Measurements</p><h2>Observations</h2></div><span>Optional</span></div>
                <div class="form-grid measurement-grid">
                    <div class="compound-field"><x-clinical.field label="Weight" name="weight" wire:model="weight" type="number" min="0" max="999" step="0.01" /><x-clinical.select label="Unit" name="weightUnit" wire:model="weightUnit"><option value="kg">kg</option><option value="lbs">lb</option></x-clinical.select></div>
                    <x-clinical.field label="Body condition" name="bodyConditionScore" wire:model="bodyConditionScore" type="number" min="1" max="9" hint="1–9 scale" />
                    <div class="compound-field"><x-clinical.field label="Temperature" name="temperature" wire:model="temperature" type="number" min="0" max="120" step="0.1" /><x-clinical.select label="Unit" name="temperatureUnit" wire:model="temperatureUnit"><option value="°C">°C</option><option value="°F">°F</option></x-clinical.select></div>
                    <x-clinical.field label="Heart rate" name="heartRate" wire:model="heartRate" type="number" min="0" max="500" hint="beats/min" />
                    <x-clinical.field label="Respiratory rate" name="respiratoryRate" wire:model="respiratoryRate" type="number" min="0" max="200" hint="breaths/min" />
                </div>
            </section>

            <section class="form-section">
                <div class="form-section-heading"><div><p class="eyebrow">Assessment & plan</p><h2>Clinical narrative</h2></div><span>Optional</span></div>
                <div class="form-grid form-grid-two textarea-grid">
                    <x-clinical.textarea label="Assessment or diagnosis" name="diagnosis" wire:model="diagnosis" rows="5" />
                    <x-clinical.textarea label="Differential diagnoses" name="differentialDiagnoses" wire:model="differentialDiagnoses" rows="5" />
                    <x-clinical.textarea label="Treatment today" name="treatment" wire:model="treatment" rows="5" />
                    <x-clinical.textarea label="Home care instructions" name="homeCareInstructions" wire:model="homeCareInstructions" rows="5" />
                    <x-clinical.textarea label="Medication summary" name="medicationsText" wire:model="medicationsText" rows="4" hint="Use the structured medication entries below when available." />
                    <x-clinical.textarea label="Additional clinical context" name="notes" wire:model="notes" rows="4" />
                </div>
                <div class="form-grid form-grid-two">
                    <x-clinical.field label="Suggested next visit" name="nextVisitDate" wire:model="nextVisitDate" type="date" min="{{ now()->addDay()->toDateString() }}" />
                    <x-clinical.field label="Follow-up note" name="nextVisitNotes" wire:model="nextVisitNotes" />
                </div>
            </section>

            <section class="form-section">
                <div class="form-section-heading"><div><p class="eyebrow">Vaccinations</p><h2>Vaccines administered</h2></div><x-clinical.button variant="text" wire:click="addVaccination">+ Add vaccination</x-clinical.button></div>
                <p class="context-note">Enter the product or vaccine name exactly as it appears on the label or certificate.</p>
                @forelse ($vaccinations as $index => $vaccination)
                    <fieldset class="repeater-row" wire:key="vaccination-{{ $index }}">
                        <legend>Vaccination {{ $index + 1 }}</legend><x-clinical.button variant="danger" wire:click="removeVaccination({{ $index }})" aria-label="Remove vaccination {{ $index + 1 }}">Remove</x-clinical.button>
                        <div class="form-grid form-grid-three">
                            <x-clinical.field label="Vaccine name" name="vaccinations.{{ $index }}.vaccine_name" wire:model="vaccinations.{{ $index }}.vaccine_name" required />
                            <x-clinical.select label="Type" name="vaccinations.{{ $index }}.vaccine_type" wire:model="vaccinations.{{ $index }}.vaccine_type"><option value="">Not specified</option><option value="core">Core</option><option value="non-core">Non-core</option><option value="rabies">Rabies</option></x-clinical.select>
                            <x-clinical.field label="Administered date" name="vaccinations.{{ $index }}.administered_date" wire:model="vaccinations.{{ $index }}.administered_date" type="date" required />
                            <x-clinical.field label="Manufacturer" name="vaccinations.{{ $index }}.manufacturer" wire:model="vaccinations.{{ $index }}.manufacturer" />
                            <x-clinical.field label="Batch number" name="vaccinations.{{ $index }}.batch_number" wire:model="vaccinations.{{ $index }}.batch_number" />
                            <x-clinical.field label="Next due date" name="vaccinations.{{ $index }}.next_due_date" wire:model="vaccinations.{{ $index }}.next_due_date" type="date" />
                            <x-clinical.field label="Product expiry" name="vaccinations.{{ $index }}.expiry_date" wire:model="vaccinations.{{ $index }}.expiry_date" type="date" />
                        </div>
                    </fieldset>
                @empty<p class="quiet-empty">No structured vaccination entry added.</p>@endforelse
            </section>

            <section class="form-section">
                <div class="form-section-heading"><div><p class="eyebrow">Medications</p><h2>Prescribed or administered</h2></div><x-clinical.button variant="text" wire:click="addMedication">+ Add medication</x-clinical.button></div>
                @forelse ($medications as $index => $medication)
                    <fieldset class="repeater-row" wire:key="medication-{{ $index }}"><legend>Medication {{ $index + 1 }}</legend><x-clinical.button variant="danger" wire:click="removeMedication({{ $index }})" aria-label="Remove medication {{ $index + 1 }}">Remove</x-clinical.button>
                        <div class="form-grid form-grid-three">
                            <x-clinical.field label="Medication name" name="medications.{{ $index }}.medication_name" wire:model="medications.{{ $index }}.medication_name" required />
                            <x-clinical.field label="Dosage" name="medications.{{ $index }}.dosage" wire:model="medications.{{ $index }}.dosage" />
                            <x-clinical.field label="Frequency" name="medications.{{ $index }}.frequency" wire:model="medications.{{ $index }}.frequency" />
                            <x-clinical.select label="Route" name="medications.{{ $index }}.route" wire:model="medications.{{ $index }}.route"><option value="">Not specified</option>@foreach (['oral', 'topical', 'injection', 'inhalation', 'ophthalmic', 'otic', 'transdermal', 'other'] as $route)<option value="{{ $route }}">{{ str($route)->headline() }}</option>@endforeach</x-clinical.select>
                            <x-clinical.field label="Start date" name="medications.{{ $index }}.start_date" wire:model="medications.{{ $index }}.start_date" type="date" required />
                            <x-clinical.field label="End date" name="medications.{{ $index }}.end_date" wire:model="medications.{{ $index }}.end_date" type="date" />
                        </div>
                        <x-clinical.textarea label="Instructions" name="medications.{{ $index }}.instructions" wire:model="medications.{{ $index }}.instructions" rows="3" />
                    </fieldset>
                @empty<p class="quiet-empty">No structured medication entry added.</p>@endforelse
            </section>

            <section class="form-section">
                <div class="form-section-heading"><div><p class="eyebrow">Conditions & allergies</p><h2>New clinical findings</h2></div><x-clinical.button variant="text" wire:click="addCondition">+ Add finding</x-clinical.button></div>
                @forelse ($conditions as $index => $condition)
                    <fieldset class="repeater-row" wire:key="condition-{{ $index }}"><legend>Finding {{ $index + 1 }}</legend><x-clinical.button variant="danger" wire:click="removeCondition({{ $index }})" aria-label="Remove finding {{ $index + 1 }}">Remove</x-clinical.button>
                        <div class="form-grid form-grid-three">
                            <x-clinical.field label="Name" name="conditions.{{ $index }}.condition_name" wire:model="conditions.{{ $index }}.condition_name" required />
                            <x-clinical.select label="Type" name="conditions.{{ $index }}.condition_type" wire:model="conditions.{{ $index }}.condition_type"><option value="medical_condition">Medical condition</option><option value="allergy">Allergy</option></x-clinical.select>
                            <x-clinical.select label="Severity" name="conditions.{{ $index }}.severity" wire:model="conditions.{{ $index }}.severity"><option value="">Not specified</option>@foreach (['mild', 'moderate', 'severe', 'chronic'] as $severity)<option value="{{ $severity }}">{{ str($severity)->headline() }}</option>@endforeach</x-clinical.select>
                        </div>
                        <div class="form-grid form-grid-three textarea-grid"><x-clinical.textarea label="Description" name="conditions.{{ $index }}.description" wire:model="conditions.{{ $index }}.description" rows="3" /><x-clinical.textarea label="Symptoms" name="conditions.{{ $index }}.symptoms" wire:model="conditions.{{ $index }}.symptoms" rows="3" /><x-clinical.textarea label="Treatment plan" name="conditions.{{ $index }}.treatment_plan" wire:model="conditions.{{ $index }}.treatment_plan" rows="3" /></div>
                    </fieldset>
                @empty<p class="quiet-empty">No condition or allergy entry added.</p>@endforelse
            </section>

            <footer class="form-footer">
                <div class="review-note"><svg aria-hidden="true" viewBox="0 0 24 24" fill="none"><path d="M12 3 5 6v5c0 4.7 2.8 8 7 10 4.2-2 7-5.3 7-10V6l-7-3Z"/><path d="m9 12 2 2 4-4"/></svg><span><strong>Nothing is published automatically.</strong> The profile manager reviews these records before they join the approved care history.</span></div>
                <div class="button-row"><x-clinical.button :href="route('clinical.patients.show', ['grantId' => $grantId])" wire:navigate>Cancel</x-clinical.button><x-clinical.button variant="primary" type="submit" wire:loading.attr="disabled" wire:target="submit"><span wire:loading.remove wire:target="submit">Send for family review</span><span wire:loading wire:target="submit">Sending securely…</span></x-clinical.button></div>
            </footer>
        </form>
    @endif
</div>
