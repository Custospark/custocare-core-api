<?php

declare(strict_types=1);

namespace App\Services\Patient;

use App\Models\Allergy;
use App\Models\ClinicalNote;
use App\Models\Consultation;
use App\Models\Diagnosis;
use App\Models\Facility;
use App\Models\LabRequest;
use App\Models\LabResult;
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
            'lab_results' => $this->mapLabResults($patient->id, $facilitiesMap),
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
        
        // Combine first_name and last_name to get the full name
        $firstName = $user?->first_name ?? '';
        $lastName = $user?->last_name ?? '';
        $fullName = trim($firstName . ' ' . $lastName);
        $name = !empty($fullName) ? $fullName : null;

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
            
            // Get recorded by name from first_name and last_name
            $recordedByName = null;
            if ($a->recordedBy) {
                $firstName = $a->recordedBy->first_name ?? '';
                $lastName = $a->recordedBy->last_name ?? '';
                $recordedByName = trim($firstName . ' ' . $lastName);
                $recordedByName = !empty($recordedByName) ? $recordedByName : null;
            }

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
                    'name' => $recordedByName,
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
            ->with(['items', 'facility', 'prescribedBy'])
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
            
            // Get prescribed by name from first_name and last_name
            $prescribedByName = null;
            if ($p->prescribedBy) {
                $firstName = $p->prescribedBy->first_name ?? '';
                $lastName = $p->prescribedBy->last_name ?? '';
                $prescribedByName = trim($firstName . ' ' . $lastName);
                $prescribedByName = !empty($prescribedByName) ? $prescribedByName : null;
            }

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
                'clinician' => $p->prescribedBy ? [
                    'id' => $p->prescribedBy->id,
                    'name' => $this->formatClinicianName($prescribedByName),
                ] : null,
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
            ->with(['staff.user'])
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
            
            // Get staff name from first_name and last_name via user relationship
            $staffName = null;
            if ($n->staff && $n->staff->user) {
                $firstName = $n->staff->user->first_name ?? '';
                $lastName = $n->staff->user->last_name ?? '';
                $staffName = trim($firstName . ' ' . $lastName);
                $staffName = !empty($staffName) ? $staffName : null;
            }

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
                'clinician' => $n->staff ? [
                    'id' => $n->staff->id,
                    'name' => $this->formatClinicianName($staffName),
                ] : null,
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
            ->with(['staff.user'])
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
            
            // Get staff name from first_name and last_name via user relationship
            $staffName = null;
            if ($v->staff && $v->staff->user) {
                $firstName = $v->staff->user->first_name ?? '';
                $lastName = $v->staff->user->last_name ?? '';
                $staffName = trim($firstName . ' ' . $lastName);
                $staffName = !empty($staffName) ? $staffName : null;
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
                'clinician' => $v->staff ? [
                    'id' => $v->staff->id,
                    'name' => $this->formatClinicianName($staffName),
                ] : null,
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
            ->with(['staff.user'])
            ->orderByDesc('created_at')
            ->limit(self::ROW_LIMIT)
            ->get();

        return $rows->map(function (Diagnosis $d) use ($facilitiesMap) {
            $fid = $d->facility_id ? (int) $d->facility_id : null;
            $snapshot = $fid !== null ? ($facilitiesMap[(string) $fid] ?? null) : null;
            if ($snapshot === null && $fid !== null) {
                $snapshot = $this->formatFacilitySnapshot(Facility::query()->find($fid));
            }
            
            // Get staff name from first_name and last_name via user relationship
            $staffName = null;
            if ($d->staff && $d->staff->user) {
                $firstName = $d->staff->user->first_name ?? '';
                $lastName = $d->staff->user->last_name ?? '';
                $staffName = trim($firstName . ' ' . $lastName);
                $staffName = !empty($staffName) ? $staffName : null;
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
                'clinician' => $d->staff ? [
                    'id' => $d->staff->id,
                    'name' => $this->formatClinicianName($staffName),
                ] : null,
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
            
            // Get requesting staff name from first_name and last_name via user relationship
            $requestingStaffName = null;
            if ($c->requestingStaff && $c->requestingStaff->user) {
                $firstName = $c->requestingStaff->user->first_name ?? '';
                $lastName = $c->requestingStaff->user->last_name ?? '';
                $requestingStaffName = trim($firstName . ' ' . $lastName);
                $requestingStaffName = !empty($requestingStaffName) ? $requestingStaffName : null;
            }
            
            // Get consultant staff name from first_name and last_name via user relationship
            $consultantStaffName = null;
            if ($c->consultantStaff && $c->consultantStaff->user) {
                $firstName = $c->consultantStaff->user->first_name ?? '';
                $lastName = $c->consultantStaff->user->last_name ?? '';
                $consultantStaffName = trim($firstName . ' ' . $lastName);
                $consultantStaffName = !empty($consultantStaffName) ? $consultantStaffName : null;
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
                    'name' => $this->formatClinicianName($requestingStaffName),
                ] : null,
                'consultant_staff' => $c->consultantStaff ? [
                    'id' => $c->consultantStaff->id,
                    'name' => $this->formatClinicianName($consultantStaffName),
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
            ->with(['items.labTest', 'requestedBy.user'])
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
            
            // Get requested by name from first_name and last_name via user relationship
            $requestedByName = null;
            if ($r->requestedBy && $r->requestedBy->user) {
                $firstName = $r->requestedBy->user->first_name ?? '';
                $lastName = $r->requestedBy->user->last_name ?? '';
                $requestedByName = trim($firstName . ' ' . $lastName);
                $requestedByName = !empty($requestedByName) ? $requestedByName : null;
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
                'clinician' => $r->requestedBy ? [
                    'id' => $r->requestedBy->id,
                    'name' => $this->formatClinicianName($requestedByName),
                ] : null,
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

    /**
     * @param  array<string, array<string, mixed>>  $facilitiesMap
     * @return list<array<string, mixed>>
     */
    private function mapLabResults(int $patientId, array $facilitiesMap): array
    {
        $rows = LabResult::query()
            ->whereHas('labRequestItem.labRequest', fn ($q) => $q->where('patient_id', $patientId))
            ->with([
                'templateField',
                'recordedBy.user',
                'verifiedBy.user',
                'labRequestItem.labTest',
                'labRequestItem.labRequest',
            ])
            ->orderByDesc('recorded_at')
            ->orderByDesc('id')
            ->limit(self::ROW_LIMIT)
            ->get();

        return $rows->map(function (LabResult $res) use ($facilitiesMap) {
            $labRequest = $res->labRequestItem?->labRequest;
            $fid = $labRequest?->facility_id ? (int) $labRequest->facility_id : null;
            $snapshot = $fid !== null ? ($facilitiesMap[(string) $fid] ?? null) : null;
            if ($snapshot === null && $fid !== null) {
                $snapshot = $this->formatFacilitySnapshot(Facility::query()->find($fid));
            }

            // Get recorded by name from first_name and last_name via user relationship
            $recordedByName = null;
            if ($res->recordedBy && $res->recordedBy->user) {
                $firstName = $res->recordedBy->user->first_name ?? '';
                $lastName = $res->recordedBy->user->last_name ?? '';
                $recordedByName = trim($firstName . ' ' . $lastName);
                $recordedByName = !empty($recordedByName) ? $recordedByName : null;
            }
            
            // Get verified by name from first_name and last_name via user relationship
            $verifiedByName = null;
            if ($res->verifiedBy && $res->verifiedBy->user) {
                $firstName = $res->verifiedBy->user->first_name ?? '';
                $lastName = $res->verifiedBy->user->last_name ?? '';
                $verifiedByName = trim($firstName . ' ' . $lastName);
                $verifiedByName = !empty($verifiedByName) ? $verifiedByName : null;
            }

            return [
                'id' => $res->id,
                'result_uuid' => $res->result_uuid,
                'visit_id' => $labRequest?->visit_id,
                'facility_id' => $fid,
                'facility' => $snapshot,
                'lab_request_id' => $labRequest?->id,
                'lab_request_item_id' => $res->lab_request_item_id,
                'test_name' => $res->labRequestItem?->labTest?->name,
                'field_name' => $res->templateField?->field_name,
                'value' => $res->value,
                'unit' => $res->unit,
                'numeric_value' => $res->numeric_value,
                'flag' => $res->flag,
                'reference_min' => $res->reference_min,
                'reference_max' => $res->reference_max,
                'interpretation' => $res->interpretation,
                'comments' => $res->comments,
                'recorded_at' => $res->recorded_at?->toIso8601String(),
                'verified_at' => $res->verified_at?->toIso8601String(),
                'occurred_at' => $res->recorded_at?->toIso8601String(),
                'clinician' => $recordedByName ? [
                    'id' => $res->recordedBy?->id,
                    'name' => $this->formatClinicianName($recordedByName),
                ] : null,
                'verified_by' => $verifiedByName ? [
                    'id' => $res->verifiedBy?->id,
                    'name' => $this->formatClinicianName($verifiedByName),
                ] : null,
            ];
        })->values()->all();
    }

    private function formatClinicianName(?string $name): ?string
    {
        if ($name === null || trim($name) === '') {
            return null;
        }

        $clean = trim($name);
        if (str_starts_with(strtolower($clean), 'dr.')) {
            return $clean;
        }

        return 'Dr. '.$clean;
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