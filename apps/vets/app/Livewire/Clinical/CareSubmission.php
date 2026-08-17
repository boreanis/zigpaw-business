<?php

namespace App\Livewire\Clinical;

use App\Exceptions\PlatformApiException;
use App\Livewire\Concerns\UsesPortalWorkspace;
use App\Services\PlatformApiClient;
use App\Support\PortalAccessTokenStore;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

class CareSubmission extends Component
{
    use UsesPortalWorkspace;

    #[Locked]
    public string $grantId;

    #[Locked]
    public string $idempotencyKey;

    /** @var array<string, mixed> */
    public array $grant = [];

    /** @var list<array<string, mixed>> */
    public array $locations = [];

    public bool $canSubmit = false;

    public ?string $message = null;

    public string $vetName = '';

    public string $clinicName = '';

    public string $clinicEmail = '';

    public string $clinicPhone = '';

    public string $visitDate = '';

    public string $visitType = 'checkup';

    public string $visitReason = '';

    public string $weight = '';

    public string $weightUnit = 'kg';

    public string $bodyConditionScore = '';

    public string $temperature = '';

    public string $temperatureUnit = '°C';

    public string $heartRate = '';

    public string $respiratoryRate = '';

    public string $diagnosis = '';

    public string $differentialDiagnoses = '';

    public string $treatment = '';

    public string $prognosis = '';

    public string $medicationsText = '';

    public string $homeCareInstructions = '';

    public string $nextVisitDate = '';

    public string $nextVisitNotes = '';

    public string $notes = '';

    /** @var list<array<string, mixed>> */
    public array $vaccinations = [];

    /** @var list<array<string, mixed>> */
    public array $medications = [];

    /** @var list<array<string, mixed>> */
    public array $conditions = [];

    public function mount(string $grantId, PlatformApiClient $api, PortalAccessTokenStore $tokens): void
    {
        $this->grantId = $grantId;
        $this->idempotencyKey = (string) Str::uuid();
        $this->visitDate = now()->toDateString();
        $this->loadFormContext($api, $tokens);
    }

    public function addVaccination(): void
    {
        if (count($this->vaccinations) < 10) {
            $this->vaccinations[] = [
                'vaccine_name' => '',
                'vaccine_type' => '',
                'manufacturer' => '',
                'batch_number' => '',
                'administered_date' => $this->visitDate,
                'expiry_date' => '',
                'next_due_date' => '',
            ];
        }
    }

    public function removeVaccination(int $index): void
    {
        unset($this->vaccinations[$index]);
        $this->vaccinations = array_values($this->vaccinations);
    }

    public function addMedication(): void
    {
        if (count($this->medications) < 10) {
            $this->medications[] = [
                'medication_name' => '',
                'dosage' => '',
                'frequency' => '',
                'route' => '',
                'instructions' => '',
                'start_date' => $this->visitDate,
                'end_date' => '',
            ];
        }
    }

    public function removeMedication(int $index): void
    {
        unset($this->medications[$index]);
        $this->medications = array_values($this->medications);
    }

    public function addCondition(): void
    {
        if (count($this->conditions) < 10) {
            $this->conditions[] = [
                'condition_name' => '',
                'condition_type' => 'medical_condition',
                'severity' => '',
                'description' => '',
                'symptoms' => '',
                'treatment_plan' => '',
            ];
        }
    }

    public function removeCondition(int $index): void
    {
        unset($this->conditions[$index]);
        $this->conditions = array_values($this->conditions);
    }

    public function submit(PlatformApiClient $api, PortalAccessTokenStore $tokens): void
    {
        if (! $this->canSubmit) {
            $this->addError('submission', 'This grant does not allow this clinical organisation to submit records.');

            return;
        }

        $credentials = $this->portalCredentials($tokens);
        if ($credentials === null) {
            return;
        }

        $validated = $this->validate($this->rules(), [], $this->validationAttributes());
        [$accessToken, $organizationId] = $credentials;

        try {
            $submission = $api->createCareSubmission(
                $accessToken,
                $organizationId,
                $this->grantId,
                $this->payload($validated),
                $this->idempotencyKey,
            );

            session()->flash('success', 'Care records were sent securely for the family to review.');
            $this->redirectRoute('submissions.show', ['submissionId' => $submission['id']], navigate: true);
        } catch (PlatformApiException $exception) {
            $this->clearPortalSessionIfUnauthorized($exception->status, $tokens);

            foreach ($exception->errors as $field => $messages) {
                foreach ($messages as $fieldMessage) {
                    $this->addError($this->propertyName($field), $fieldMessage);
                }
            }

            if ($exception->errors === []) {
                $this->addError('submission', match ($exception->status) {
                    403 => 'The family’s sharing grant no longer allows this submission.',
                    404 => 'The patient grant is no longer available.',
                    409 => 'These records were already submitted. Open submission history to check their status.',
                    default => $exception->getMessage(),
                });
            }
        }
    }

    public function render(): View
    {
        $petName = data_get($this->grant, 'pet.name');
        $title = is_string($petName) && $petName !== ''
            ? 'Submit care for '.$petName.' · Zigpaw clinical'
            : 'Submit care · Zigpaw clinical';

        return view('livewire.clinical.care-submission')
            ->layout('components.layouts.portal', ['title' => $title]);
    }

    /** @return array<string, array<int, mixed>> */
    private function rules(): array
    {
        return [
            'vetName' => ['required', 'string', 'max:255'],
            'clinicName' => ['required', 'string', 'max:255'],
            'clinicPhone' => ['nullable', 'string', 'max:50'],
            'clinicEmail' => ['required', 'email', 'max:255'],
            'visitDate' => ['required', 'date', 'before_or_equal:today'],
            'visitType' => ['required', 'in:checkup,vaccination,health_issue,procedure,emergency,follow_up,other'],
            'visitReason' => ['nullable', 'string', 'max:1000'],
            'weight' => ['nullable', 'numeric', 'min:0', 'max:999'],
            'weightUnit' => ['nullable', 'in:kg,lbs'],
            'bodyConditionScore' => ['nullable', 'integer', 'min:1', 'max:9'],
            'temperature' => ['nullable', 'numeric', 'min:0', 'max:120'],
            'temperatureUnit' => ['nullable', 'in:°C,°F'],
            'heartRate' => ['nullable', 'integer', 'min:0', 'max:500'],
            'respiratoryRate' => ['nullable', 'integer', 'min:0', 'max:200'],
            'diagnosis' => ['nullable', 'string', 'max:2000'],
            'differentialDiagnoses' => ['nullable', 'string', 'max:2000'],
            'treatment' => ['nullable', 'string', 'max:2000'],
            'prognosis' => ['nullable', 'in:excellent,good,fair,guarded,poor'],
            'medicationsText' => ['nullable', 'string', 'max:2000'],
            'homeCareInstructions' => ['nullable', 'string', 'max:2000'],
            'nextVisitDate' => ['nullable', 'date', 'after:today'],
            'nextVisitNotes' => ['nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'vaccinations' => ['array', 'max:10'],
            'vaccinations.*.vaccine_name' => ['required', 'string', 'max:255'],
            'vaccinations.*.vaccine_type' => ['nullable', 'in:core,non-core,rabies'],
            'vaccinations.*.manufacturer' => ['nullable', 'string', 'max:255'],
            'vaccinations.*.batch_number' => ['nullable', 'string', 'max:100'],
            'vaccinations.*.administered_date' => ['required', 'date', 'before_or_equal:today'],
            'vaccinations.*.expiry_date' => ['nullable', 'date', 'after:today'],
            'vaccinations.*.next_due_date' => ['nullable', 'date', 'after:today'],
            'medications' => ['array', 'max:10'],
            'medications.*.medication_name' => ['required', 'string', 'max:255'],
            'medications.*.dosage' => ['nullable', 'string', 'max:255'],
            'medications.*.frequency' => ['nullable', 'string', 'max:255'],
            'medications.*.route' => ['nullable', 'in:oral,topical,injection,inhalation,ophthalmic,otic,transdermal,other'],
            'medications.*.instructions' => ['nullable', 'string', 'max:1000'],
            'medications.*.start_date' => ['required', 'date'],
            'medications.*.end_date' => ['nullable', 'date', 'after_or_equal:medications.*.start_date'],
            'conditions' => ['array', 'max:10'],
            'conditions.*.condition_name' => ['required', 'string', 'max:255'],
            'conditions.*.condition_type' => ['nullable', 'in:medical_condition,allergy'],
            'conditions.*.severity' => ['nullable', 'in:mild,moderate,severe,chronic'],
            'conditions.*.description' => ['nullable', 'string', 'max:1000'],
            'conditions.*.symptoms' => ['nullable', 'string', 'max:1000'],
            'conditions.*.treatment_plan' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /** @return array<string, string> */
    private function validationAttributes(): array
    {
        return [
            'vetName' => 'veterinarian name',
            'clinicName' => 'clinic name',
            'clinicEmail' => 'clinic email',
            'visitDate' => 'visit date',
            'visitType' => 'visit type',
            'bodyConditionScore' => 'body condition score',
            'heartRate' => 'heart rate',
            'respiratoryRate' => 'respiratory rate',
            'nextVisitDate' => 'next visit date',
            'vaccinations.*.vaccine_name' => 'vaccine name',
            'vaccinations.*.administered_date' => 'vaccination date',
            'medications.*.medication_name' => 'medication name',
            'medications.*.start_date' => 'medication start date',
            'conditions.*.condition_name' => 'condition name',
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function payload(array $validated): array
    {
        $mapping = [
            'vetName' => 'vet_name', 'clinicName' => 'clinic_name', 'clinicEmail' => 'clinic_email',
            'clinicPhone' => 'clinic_phone', 'visitDate' => 'visit_date', 'visitType' => 'visit_type',
            'visitReason' => 'visit_reason', 'weight' => 'weight', 'weightUnit' => 'weight_unit',
            'bodyConditionScore' => 'body_condition_score', 'temperature' => 'temperature',
            'temperatureUnit' => 'temperature_unit', 'heartRate' => 'heart_rate',
            'respiratoryRate' => 'respiratory_rate', 'diagnosis' => 'diagnosis',
            'differentialDiagnoses' => 'differential_diagnoses', 'treatment' => 'treatment',
            'prognosis' => 'prognosis', 'medicationsText' => 'medications_text',
            'homeCareInstructions' => 'home_care_instructions', 'nextVisitDate' => 'next_visit_date',
            'nextVisitNotes' => 'next_visit_notes', 'notes' => 'notes', 'vaccinations' => 'vaccinations',
            'medications' => 'medications', 'conditions' => 'conditions',
        ];
        $payload = [];

        foreach ($mapping as $property => $field) {
            $payload[$field] = $validated[$property] ?? null;
        }

        return $this->removeEmptyValues($payload);
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function removeEmptyValues(array $values): array
    {
        foreach ($values as $key => $value) {
            if (is_array($value)) {
                $value = array_map(fn (array $row): array => $this->removeEmptyValues($row), $value);
                $values[$key] = array_values(array_filter($value, fn (array $row): bool => $row !== []));
            }

            if ($values[$key] === '' || $values[$key] === null || $values[$key] === []) {
                unset($values[$key]);
            }
        }

        return $values;
    }

    private function propertyName(string $apiField): string
    {
        $segments = explode('.', $apiField);
        $first = array_shift($segments);
        $property = Str::camel((string) $first);

        return implode('.', array_filter([$property, ...$segments], fn (string $segment): bool => $segment !== ''));
    }

    private function loadFormContext(PlatformApiClient $api, PortalAccessTokenStore $tokens): void
    {
        $credentials = $this->portalCredentials($tokens);
        if ($credentials === null) {
            return;
        }

        [$accessToken, $organizationId] = $credentials;

        try {
            $this->grant = $api->providerGrant($accessToken, $organizationId, $this->grantId);
            $identity = $api->identity($accessToken, $organizationId);
            $dashboard = $api->dashboard($accessToken, $organizationId);
            $this->organizationName = (string) data_get($identity, 'organization.name', $this->organizationName);
            session()->put('portal.organization_name', $this->organizationName);
            $this->vetName = (string) ($identity['name'] ?? '');
            $this->clinicName = $this->organizationName;
            $this->clinicEmail = (string) ($identity['email'] ?? '');
            $this->locations = array_values(Arr::wrap($dashboard['locations'] ?? []));
            $this->canSubmit = data_get($this->grant, 'capabilities.submit_care') === true;

            if (! $this->canSubmit) {
                $this->message = 'This family has not allowed this clinical organisation to submit records for this patient.';
            }
        } catch (PlatformApiException $exception) {
            $this->clearPortalSessionIfUnauthorized($exception->status, $tokens);
            $this->canSubmit = false;
            $this->message = match ($exception->status) {
                403 => 'This family has not allowed this clinical organisation to submit records for this patient.',
                404 => 'This patient grant is no longer available.',
                default => $exception->getMessage(),
            };
        }
    }
}
