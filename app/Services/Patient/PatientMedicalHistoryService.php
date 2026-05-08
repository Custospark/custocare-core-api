<?php

declare(strict_types=1);

namespace App\Services\Patient;

use App\Models\Allergy;
use App\Models\ClinicalNote;
use App\Models\Consultation;
use App\Models\Diagnosis;
use App\Models\Facility;
use App\Models\LabRequest;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\Visit;
use App\Models\Vital;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Aggregates cross-facility clinical documentation for continuity of care.
 * Facility snapshots align with {@see \App\Http\Controllers\Api\FacilityController::getFacilityDetails} shape.
 */
class PatientMedicalHistoryService
{
    private const ROW_LIMIT = 200;

    public function build(Patient $patient): array
    {
        $patient->loadMissing('user');

        $visits = Visit::query()
            ->where('patient_id', $patient->id)
            ->with('facility')
            ->orderByDesc('arrived_at')
            ->orderByDesc('id')
            ->limit(500)
            ->get();

        $facilitiesMap = $this->loadFacilitiesMap($patient->id, $visits);

        return [
            'patient' => $this->mapPatientSummary($patient),
            'facilities' => $facilitiesMap,
            'visits' => $visits->map(fn (Visit $v) => $this->mapVisit($v, $facilitiesMap))->values()->all(),
            'allergies' => $this->mapAllergies($patient->id, $facilitiesMap),
            'prescriptions' => $this->mapPrescriptions($patient->id, $facilitiesMap),
            'clinical_notes' => $this->mapClinicalNotes($patient->id, $facilitiesMap),
            'vitals' => $this->mapVitals($patient->id, $facilitiesMap),
            'diagnoses' => $this->mapDiagnoses($patient->id, $facilitiesMap),
            'consultations' => $this->mapConsultations($patient->id, $facilitiesMap),
            'lab_requests' => $this->mapLabRequests($patient->id, $facilitiesMap),
            'generated_at' => Carbon::now()->toIso8601String(),
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function loadFacilitiesMap(int $patientId, Collection $visits): array
    {
        $ids = collect();

        foreach ($visits as $visit) {
            if ($visit->facility_id) {
                $ids->push((int) $visit->facility_id);
            }
        }

        $ids = $ids->merge(
            Prescription::query()->where('patient_id', $patientId)->whereNotNull('facility_id')->distinct()->pluck('facility_id')
        )->merge(
            ClinicalNote::query()->where('patient_id', $patientId)->whereNotNull('facility_id')->distinct()->pluck('facility_id')
        )->merge(
            Vital::query()->where('patient_id', $patientId)->whereNotNull('facility_id')->distinct()->pluck('facility_id')
        )->merge(
            Diagnosis::query()->where('patient_id', $patientId)->whereNotNull('facility_id')->distinct()->pluck('facility_id')
        )->merge(
            Consultation::query()->where('patient_id', $patientId)->whereNotNull('facility_id')->distinct()->pluck('facility_id')
        )->merge(
            LabRequest::query()->where('patient_id', $patientId)->whereNotNull('facility_id')->distinct()->pluck('facility_id')
        );

        $allergyVisitIds = Allergy::query()
            ->where('patient_id', $patientId)
            ->whereNotNull('visit_id')
            ->pluck('visit_id');

        if ($allergyVisitIds->isNotEmpty()) {
            $ids = $ids->merge(
                Visit::query()->whereIn('id', $allergyVisitIds)->whereNotNull('facility_id')->pluck('facility_id')
            );
        }

        $uniqueIds = $ids->filter(static fn ($id) => $id !== null && $id !== '')->unique()->values()->all();

        if ($uniqueIds === []) {
            return [];
        }

        $rows = Facility::query()
            ->whereIn('id', $uniqueIds)
            ->whereNull('deleted_at')
            ->get();

        $map = [];
        foreach ($rows as $facility) {
            $snap = $this->formatFacilitySnapshot($facility);
            if ($snap !== null) {
                $map[(string) $facility->id] = $snap;
            }
        }

        return $map;
    }

    /**
     * @param  array<string, array<string, mixed>>  $facilitiesMap
     * @return array<string, mixed>
     */
    private function mapPatientSummary(Patient $patient): array
    {
        $user = $patient->user;
        $name = $user?->full_name
            ?? trim(($user?->first_name ?? '').' '.($user?->last_name ?? ''))
            ?: null;

        return [
            'id' => $patient->id,
            'patient_uuid' => $patient->patient_uuid,
            'full_name' => $name,
            'date_of_birth' => $patient->date_of_birth?->format('Y-m-d'),
            'biological_sex' => $patient->biological_sex,
            'blood_type' => $patient->blood_type,
            'status' => $patient->status,
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $facilitiesMap
     * @return array<string, mixed>
     */
    private function mapVisit(Visit $visit, array $facilitiesMap): array
    {
        $fid = $visit->facility_id ? (int) $visit->facility_id : null;
        $snapshot = $fid !== null
            ? ($facilitiesMap[(string) $fid] ?? $this->formatFacilitySnapshot($visit->facility))
            : null;

        return [
            'id' => $visit->id,
            'visit_uuid' => $visit->visit_uuid,
            'facility_id' => $fid,
            'facility' => $snapshot,
            'visit_type' => $visit->visit_type,
            'status' => $visit->status,
            'current_phase' => $visit->current_phase,
            'arrived_at' => $visit->arrived_at?->toIso8601String(),
            'discharged_at' => $visit->discharged_at?->toIso8601String(),
            'occurred_at' => $visit->arrived_at?->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $facilitiesMap
     * @return list<array<string, mixed>>
     */
    private function mapAllergies(int $patientId, array $facilitiesMap): array
    {
        $rows = Allergy::query()
            ->where('patient_id', $patientId)
            ->with(['visit.facility', 'recordedBy'])
            ->orderByDesc('created_at')
            ->limit(self::ROW_LIMIT)
            ->get();

        return $rows->map(function (Allergy $a) use ($facilitiesMap) {
            $fid = $a->visit?->facility_id ? (int) $a->visit->facility_id : null;
            $snapshot = $fid !== null
                ? ($facilitiesMap[(string) $fid] ?? $this->formatFacilitySnapshot($a->visit?->facility))
                : null;

            $occurred = $a->diagnosed_at ?? $a->created_at;

            return [
                'id' => $a->id,
                'visit_id' => $a->visit_id,
                'facility_id' => $fid,
                'facility' => $snapshot,
                'allergen' => $a->allergen,
                'reaction' => $a->reaction,
                'severity' => $a->severity,
                'clinical_notes' => $a->clinical_notes,
                'is_active' => $a->is_active,
                'diagnosed_at' => $a->diagnosed_at?->toIso8601String(),
                'resolved_at' => $a->resolved_at?->toIso8601String(),
                'created_at' => $a->created_at?->toIso8601String(),
                'occurred_at' => $occurred?->toIso8601String(),
                'recorded_by' => $a->recordedBy ? [
                    'id' => $a->recordedBy->id,
                    'name' => $a->recordedBy->full_name,
                ] : null,
            ];
        })->values()->all();
    }

    /**
     * @param  array<string, array<string, mixed>>  $facilitiesMap
     * @return list<array<string, mixed>>
     */
    private function mapPrescriptions(int $patientId, array $facilitiesMap): array
    {
        $rows = Prescription::query()
            ->where('patient_id', $patientId)
            ->with(['items', 'facility'])
            ->orderByDesc('prescription_date')
            ->orderByDesc('id')
            ->limit(self::ROW_LIMIT)
            ->get();

        return $rows->map(function (Prescription $p) use ($facilitiesMap) {
            $fid = $p->facility_id ? (int) $p->facility_id : null;
            $snapshot = $fid !== null
                ? ($facilitiesMap[(string) $fid] ?? $this->formatFacilitySnapshot($p->facility))
                : null;

            $occurred = $p->prescription_date
                ? $p->prescription_date->copy()->startOfDay()
                : $p->created_at;

            return [
                'id' => $p->id,
                'visit_id' => $p->visit_id,
                'facility_id' => $fid,
                'facility' => $snapshot,
                'prescription_number' => $p->prescription_number,
                'prescription_date' => $p->prescription_date,
                'status' => $p->status,
                'prescription_type' => $p->prescription_type,
                'priority' => $p->priority,
                'diagnosis' => $p->diagnosis,
                'clinical_notes' => $p->clinical_notes,
                'special_instructions' => $p->special_instructions,
                'created_at' => $p->created_at?->toIso8601String(),
                'occurred_at' => $occurred?->toIso8601String(),
                'items' => $p->items->map(static function ($item) {
                    return [
                        'id' => $item->id,
                        'medication_name' => $item->medication_name,
                        'brand_name' => $item->brand_name,
                        'strength' => $item->strength,
                        'dosage_form' => $item->dosage_form,
                        'dosage_quantity' => $item->dosage_quantity,
                        'dosage_unit' => $item->dosage_unit,
                        'frequency' => $item->frequency,
                        'duration_value' => $item->duration_value,
                        'duration_unit' => $item->duration_unit,
                        'route' => $item->route,
                        'instructions' => $item->instructions,
                        'refills' => $item->refills,
                    ];
                })->values()->all(),
            ];
        })->values()->all();
    }

    /**
     * @param  array<string, array<string, mixed>>  $facilitiesMap
     * @return list<array<string, mixed>>
     */
    private function mapClinicalNotes(int $patientId, array $facilitiesMap): array
    {
        $rows = ClinicalNote::query()
            ->where('patient_id', $patientId)
            ->orderByDesc('noted_at')
            ->orderByDesc('created_at')
            ->limit(self::ROW_LIMIT)
            ->get();

        return $rows->map(function (ClinicalNote $n) use ($facilitiesMap) {
            $fid = $n->facility_id ? (int) $n->facility_id : null;
            $snapshot = $fid !== null ? ($facilitiesMap[(string) $fid] ?? null) : null;
            if ($snapshot === null && $fid !== null) {
                $snapshot = $this->formatFacilitySnapshot(Facility::query()->find($fid));
            }

            $occurred = $n->noted_at ?? $n->created_at;

            return [
                'id' => $n->id,
                'uuid' => $n->uuid,
                'visit_id' => $n->visit_id,
                'facility_id' => $fid,
                'facility' => $snapshot,
                'note_type' => $n->note_type,
                'note_status' => $n->note_status,
                'subjective' => $n->subjective,
                'objective' => $n->objective,
                'assessment' => $n->assessment,
                'plan' => $n->plan,
                'noted_at' => $n->noted_at?->toIso8601String(),
                'created_at' => $n->created_at?->toIso8601String(),
                'occurred_at' => $occurred?->toIso8601String(),
            ];
        })->values()->all();
    }

    /**
     * @param  array<string, array<string, mixed>>  $facilitiesMap
     * @return list<array<string, mixed>>
     */
    private function mapVitals(int $patientId, array $facilitiesMap): array
    {
        $rows = Vital::query()
            ->where('patient_id', $patientId)
            ->orderByDesc('measured_at')
            ->orderByDesc('id')
            ->limit(self::ROW_LIMIT)
            ->get();

        return $rows->map(function (Vital $v) use ($facilitiesMap) {
            $fid = $v->facility_id ? (int) $v->facility_id : null;
            $snapshot = $fid !== null ? ($facilitiesMap[(string) $fid] ?? null) : null;
            if ($snapshot === null && $fid !== null) {
                $snapshot = $this->formatFacilitySnapshot(Facility::query()->find($fid));
            }

            return [
                'id' => $v->id,
                'visit_id' => $v->visit_id,
                'facility_id' => $fid,
                'facility' => $snapshot,
                'temperature' => $v->temperature,
                'temperature_unit' => $v->temperature_unit,
                'heart_rate' => $v->heart_rate,
                'respiratory_rate' => $v->respiratory_rate,
                'systolic_bp' => $v->systolic_bp,
                'diastolic_bp' => $v->diastolic_bp,
                'oxygen_saturation' => $v->oxygen_saturation,
                'height' => $v->height,
                'weight' => $v->weight,
                'bmi' => $v->bmi,
                'pain_score' => $v->pain_score,
                'measured_at' => $v->measured_at?->toIso8601String(),
                'created_at' => $v->created_at?->toIso8601String(),
                'occurred_at' => $v->measured_at?->toIso8601String(),
            ];
        })->values()->all();
    }

    /**
     * @param  array<string, array<string, mixed>>  $facilitiesMap
     * @return list<array<string, mixed>>
     */
    private function mapDiagnoses(int $patientId, array $facilitiesMap): array
    {
        $rows = Diagnosis::query()
            ->where('patient_id', $patientId)
            ->orderByDesc('created_at')
            ->limit(self::ROW_LIMIT)
            ->get();

        return $rows->map(function (Diagnosis $d) use ($facilitiesMap) {
            $fid = $d->facility_id ? (int) $d->facility_id : null;
            $snapshot = $fid !== null ? ($facilitiesMap[(string) $fid] ?? null) : null;
            if ($snapshot === null && $fid !== null) {
                $snapshot = $this->formatFacilitySnapshot(Facility::query()->find($fid));
            }

            return [
                'id' => $d->id,
                'visit_id' => $d->visit_id,
                'facility_id' => $fid,
                'facility' => $snapshot,
                'diagnosis_code' => $d->diagnosis_code,
                'diagnosis_description' => $d->diagnosis_description,
                'diagnosis_type' => $d->diagnosis_type,
                'clinical_status' => $d->clinical_status,
                'clinical_notes' => $d->clinical_notes,
                'onset_date' => $d->onset_date?->format('Y-m-d'),
                'created_at' => $d->created_at?->toIso8601String(),
                'occurred_at' => $d->created_at?->toIso8601String(),
            ];
        })->values()->all();
    }

    /**
     * @param  array<string, array<string, mixed>>  $facilitiesMap
     * @return list<array<string, mixed>>
     */
    private function mapConsultations(int $patientId, array $facilitiesMap): array
    {
        $rows = Consultation::query()
            ->where('patient_id', $patientId)
            ->with(['requestingStaff.user', 'consultantStaff.user'])
            ->orderByDesc('requested_at')
            ->orderByDesc('id')
            ->limit(self::ROW_LIMIT)
            ->get();

        return $rows->map(function (Consultation $c) use ($facilitiesMap) {
            $fid = $c->facility_id ? (int) $c->facility_id : null;
            $snapshot = $fid !== null ? ($facilitiesMap[(string) $fid] ?? null) : null;
            if ($snapshot === null && $fid !== null) {
                $snapshot = $this->formatFacilitySnapshot(Facility::query()->find($fid));
            }

            return [
                'id' => $c->id,
                'visit_id' => $c->visit_id,
                'facility_id' => $fid,
                'facility' => $snapshot,
                'consultation_type' => $c->consultation_type,
                'priority' => $c->priority,
                'specialty_required' => $c->specialty_required,
                'clinical_question' => $c->clinical_question,
                'findings' => $c->findings,
                'recommendations' => $c->recommendations,
                'request_status' => $c->request_status,
                'requested_at' => $c->requested_at?->toIso8601String(),
                'completed_at' => $c->completed_at?->toIso8601String(),
                'occurred_at' => $c->requested_at?->toIso8601String(),
                'requesting_staff' => $c->requestingStaff ? [
                    'id' => $c->requestingStaff->id,
                    'name' => $c->requestingStaff->user?->full_name
                        ?? trim(($c->requestingStaff->user?->first_name ?? '').' '.($c->requestingStaff->user?->last_name ?? '')),
                ] : null,
                'consultant_staff' => $c->consultantStaff ? [
                    'id' => $c->consultantStaff->id,
                    'name' => $c->consultantStaff->user?->full_name
                        ?? trim(($c->consultantStaff->user?->first_name ?? '').' '.($c->consultantStaff->user?->last_name ?? '')),
                ] : null,
            ];
        })->values()->all();
    }

    /**
     * @param  array<string, array<string, mixed>>  $facilitiesMap
     * @return list<array<string, mixed>>
     */
    private function mapLabRequests(int $patientId, array $facilitiesMap): array
    {
        $rows = LabRequest::query()
            ->where('patient_id', $patientId)
            ->with(['items.labTest'])
            ->orderByDesc('requested_at')
            ->orderByDesc('id')
            ->limit(self::ROW_LIMIT)
            ->get();

        return $rows->map(function (LabRequest $r) use ($facilitiesMap) {
            $fid = $r->facility_id ? (int) $r->facility_id : null;
            $snapshot = $fid !== null ? ($facilitiesMap[(string) $fid] ?? null) : null;
            if ($snapshot === null && $fid !== null) {
                $snapshot = $this->formatFacilitySnapshot(Facility::query()->find($fid));
            }

            return [
                'id' => $r->id,
                'request_uuid' => $r->request_uuid,
                'visit_id' => $r->visit_id,
                'facility_id' => $fid,
                'facility' => $snapshot,
                'status' => $r->status,
                'priority' => $r->priority,
                'clinical_notes' => $r->clinical_notes,
                'requested_at' => $r->requested_at?->toIso8601String(),
                'completed_at' => $r->completed_at?->toIso8601String(),
                'occurred_at' => $r->requested_at?->toIso8601String(),
                'items' => $r->items->map(static function ($item) {
                    return [
                        'id' => $item->id,
                        'status' => $item->status,
                        'sample_type' => $item->sample_type,
                        'test_name' => $item->labTest?->name,
                        'notes' => $item->notes,
                    ];
                })->values()->all(),
            ];
        })->values()->all();
    }

    private function formatFacilitySnapshot(?Facility $facility): ?array
    {
        if ($facility === null) {
            return null;
        }

        $fullAddress = (string) ($facility->address_line1 ?? '');
        if (! empty($facility->address_line2)) {
            $fullAddress .= ', '.$facility->address_line2;
        }
        $fullAddress .= ', '.($facility->city ?? '');
        $fullAddress .= ', '.($facility->state_province ?? '');
        $fullAddress .= ' '.($facility->postal_code ?? '');
        $fullAddress .= ', '.($facility->country_code ?? '');

        return [
            'id' => $facility->id,
            'uuid' => $facility->facility_uuid,
            'code' => $facility->facility_code,
            'name' => $facility->facility_name,
            'legal_name' => $facility->legal_entity_name,
            'type' => $facility->facility_type,
            'tier' => $facility->facility_tier,
            'status' => $facility->operational_status,
            'phone' => $facility->main_phone,
            'email' => $facility->email,
            'address' => [
                'line1' => $facility->address_line1,
                'line2' => $facility->address_line2,
                'city' => $facility->city,
                'state' => $facility->state_province,
                'postal_code' => $facility->postal_code,
                'country' => $facility->country_code,
                'formatted' => $fullAddress,
            ],
        ];
    }
}
