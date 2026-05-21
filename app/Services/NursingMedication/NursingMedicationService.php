<?php

declare(strict_types=1);

namespace App\Services\NursingMedication;

use App\Models\NursingMedicationAdministration;
use App\Models\NursingMedicationDose;
use App\Models\NursingTreatmentLog;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\Visit;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class NursingMedicationService
{
    /**
     * Medication schedule board — doses in a datetime window.
     * When filtering by status=pending, auto-generates dose records
     * from active prescription items that don't have doses yet.
     *
     * @param  array<string, mixed>  $filters
     */
    public function paginateDoses(int $facilityId, array $filters, int $perPage = 25): LengthAwarePaginator
    {
        $perPage = min(100, max(1, $perPage));

        $status = $filters['status'] ?? null;
        if ($status === 'pending') {
            $this->ensurePendingDosesExist($facilityId, $filters);
        }

        $q = NursingMedicationDose::query()
            ->where('facility_id', $facilityId)
            ->with([
                'visit:id,patient_id,visit_uuid,status',
                'patient:id,user_id',
                'patient.user:id,display_name,first_name,last_name',
                'prescription:id,prescription_number,patient_id',
                'prescriptionItem:id,prescription_id,medication_name,strength,dosage_quantity,dosage_unit,route',
                'ward:id,name,code',
            ]);

        if (! empty($filters['visit_id'])) {
            $q->where('visit_id', (int) $filters['visit_id']);
        }

        if (! empty($filters['ward_id'])) {
            $q->where('ward_id', (int) $filters['ward_id']);
        }

        if (! empty($filters['status'])) {
            $statuses = is_array($filters['status'])
                ? $filters['status']
                : array_map('trim', explode(',', (string) $filters['status']));
            $q->whereIn('status', $statuses);
        }

        if (! empty($filters['from'])) {
            $q->where('scheduled_for', '>=', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $q->where('scheduled_for', '<=', $filters['to']);
        }

        if (! empty($filters['patient_id'])) {
            $q->where('patient_id', (int) $filters['patient_id']);
        }

        $q->orderBy('scheduled_for');

        return $q->paginate($perPage);
    }

    /**
     * Auto-generate pending dose records for prescription items that don't have one yet.
     * Only for active/in-progress visits with active prescriptions at this facility.
     */
    protected function ensurePendingDosesExist(int $facilityId, array $filters): void
    {
        $visitId = $filters['visit_id'] ?? null;
        $patientId = $filters['patient_id'] ?? null;

        $visitsQuery = Visit::query()
            ->where('facility_id', $facilityId)
            ->whereIn('status', ['active', 'in_progress']);

        if ($visitId) {
            $visitsQuery->whereKey((int) $visitId);
        }

        if ($patientId) {
            $visitsQuery->where('patient_id', (int) $patientId);
        }

        $visitsQuery->chunk(50, function ($visits) use ($facilityId) {
            foreach ($visits as $visit) {
                $prescriptions = Prescription::query()
                    ->where('patient_id', $visit->patient_id)
                    ->where('facility_id', $facilityId)
                    ->whereIn('status', ['Active - Ready for Dispensing', 'Partially Dispensed'])
                    ->pluck('id');

                if ($prescriptions->isEmpty()) continue;

                $items = PrescriptionItem::query()
                    ->whereIn('prescription_id', $prescriptions)
                    ->get();

                foreach ($items as $item) {
                    $exists = NursingMedicationDose::query()
                        ->where('facility_id', $facilityId)
                        ->where('visit_id', $visit->id)
                        ->where('prescription_item_id', $item->id)
                        ->where('status', 'pending')
                        ->exists();

                    if ($exists) continue;

                    NursingMedicationDose::query()->create([
                        'facility_id' => $facilityId,
                        'visit_id' => $visit->id,
                        'patient_id' => $visit->patient_id,
                        'prescription_id' => $item->prescription_id,
                        'prescription_item_id' => $item->id,
                        'scheduled_for' => now(),
                        'status' => 'pending',
                    ]);
                }
            }
        });
    }

    /**
     * Doses that are still pending and past their scheduled time (missed / overdue for action).
     *
     * @param  array<string, mixed>  $filters
     */
    public function paginateMissedDoses(int $facilityId, array $filters, int $perPage = 25): LengthAwarePaginator
    {
        $perPage = min(100, max(1, $perPage));
        $asOf = $filters['as_of'] ?? now()->toDateTimeString();

        $this->ensurePendingDosesExist($facilityId, $filters);

        $q = NursingMedicationDose::query()
            ->where('facility_id', $facilityId)
            ->where('status', 'pending')
            ->where('scheduled_for', '<', $asOf)
            ->with([
                'visit:id,patient_id,visit_uuid,status',
                'patient:id,user_id',
                'patient.user:id,display_name,first_name,last_name',
                'prescription:id,prescription_number',
                'prescriptionItem:id,prescription_id,medication_name,strength',
                'ward:id,name,code',
            ]);

        if (! empty($filters['ward_id'])) {
            $q->where('ward_id', (int) $filters['ward_id']);
        }

        if (! empty($filters['visit_id'])) {
            $q->where('visit_id', (int) $filters['visit_id']);
        }

        $q->orderBy('scheduled_for');

        return $q->paginate($perPage);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createDose(array $data, ?int $actorUserId): NursingMedicationDose
    {
        $facilityId = (int) $data['facility_id'];
        $this->assertPrescriptionContext($facilityId, $data);

        $visit = Visit::query()
            ->whereKey((int) $data['visit_id'])
            ->where('facility_id', $facilityId)
            ->firstOrFail();

        $dose = new NursingMedicationDose;
        $dose->fill([
            'facility_id' => $facilityId,
            'visit_id' => $visit->id,
            'patient_id' => $visit->patient_id,
            'prescription_id' => (int) $data['prescription_id'],
            'prescription_item_id' => (int) $data['prescription_item_id'],
            'scheduled_for' => $data['scheduled_for'],
            'status' => $data['status'] ?? 'pending',
            'ward_id' => $data['ward_id'] ?? null,
            'schedule_notes' => $data['schedule_notes'] ?? null,
            'created_by_user_id' => $actorUserId,
        ]);
        $dose->save();

        return $dose->fresh([
            'visit:id,patient_id,visit_uuid',
            'patient:id,user_id',
            'patient.user:id,display_name,first_name,last_name',
            'prescription:id,prescription_number',
            'prescriptionItem:id,medication_name,strength',
            'ward:id,name,code',
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateDose(NursingMedicationDose $dose, array $data): NursingMedicationDose
    {
        if (array_key_exists('scheduled_for', $data)) {
            $dose->scheduled_for = $data['scheduled_for'];
        }
        if (array_key_exists('status', $data)) {
            $dose->status = $data['status'];
        }
        if (array_key_exists('ward_id', $data)) {
            $dose->ward_id = $data['ward_id'];
        }
        if (array_key_exists('schedule_notes', $data)) {
            $dose->schedule_notes = $data['schedule_notes'];
        }
        $dose->save();

        return $dose->fresh([
            'visit:id,patient_id,visit_uuid',
            'patient:id,user_id',
            'patient.user:id,display_name,first_name,last_name',
            'prescriptionItem:id,medication_name,strength',
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function recordAdministration(array $data, int $actorUserId): NursingMedicationAdministration
    {
        $facilityId = (int) $data['facility_id'];

        return DB::transaction(function () use ($data, $facilityId, $actorUserId) {
            $visit = Visit::query()
                ->whereKey((int) $data['visit_id'])
                ->where('facility_id', $facilityId)
                ->firstOrFail();

            $item = PrescriptionItem::query()
                ->whereKey((int) $data['prescription_item_id'])
                ->firstOrFail();

            $rx = Prescription::query()
                ->whereKey($item->prescription_id)
                ->where('facility_id', $facilityId)
                ->firstOrFail();

            if ($rx->patient_id !== $visit->patient_id) {
                throw ValidationException::withMessages([
                    'prescription_item_id' => ['Prescription item does not belong to this visit’s patient.'],
                ]);
            }

            $doseId = isset($data['nursing_medication_dose_id']) ? (int) $data['nursing_medication_dose_id'] : null;
            $dose = null;

            if ($doseId !== null) {
                $dose = NursingMedicationDose::query()
                    ->whereKey($doseId)
                    ->where('facility_id', $facilityId)
                    ->where('visit_id', $visit->id)
                    ->where('prescription_item_id', $item->id)
                    ->firstOrFail();
            }

            $admin = NursingMedicationAdministration::query()->create([
                'nursing_medication_dose_id' => $dose?->id,
                'facility_id' => $facilityId,
                'visit_id' => $visit->id,
                'prescription_item_id' => $item->id,
                'administered_by_user_id' => $actorUserId,
                'administered_at' => $data['administered_at'],
                'outcome' => $data['outcome'],
                'quantity_given' => $data['quantity_given'] ?? null,
                'quantity_unit' => $data['quantity_unit'] ?? null,
                'notes' => $data['notes'] ?? null,
                'refusal_or_omission_reason' => $data['refusal_or_omission_reason'] ?? null,
            ]);

            if ($dose !== null && in_array($data['outcome'], ['given', 'partial'], true)) {
                $dose->status = 'administered';
                $dose->save();
            }

            return $admin->fresh([
                'prescriptionItem:id,medication_name,strength,dosage_unit',
                'administeredBy:id,display_name,first_name,last_name',
                'dose:id,scheduled_for,status',
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateAdministrations(int $facilityId, array $filters, int $perPage = 25): LengthAwarePaginator
    {
        $perPage = min(100, max(1, $perPage));

        $q = NursingMedicationAdministration::query()
            ->where('facility_id', $facilityId)
            ->with([
                'visit:id,patient_id,visit_uuid',
                'prescriptionItem:id,medication_name,strength',
                'administeredBy:id,display_name,first_name,last_name',
                'dose:id,scheduled_for',
            ]);

        if (! empty($filters['visit_id'])) {
            $q->where('visit_id', (int) $filters['visit_id']);
        }

        if (! empty($filters['from'])) {
            $q->where('administered_at', '>=', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $q->where('administered_at', '<=', $filters['to']);
        }

        if (! empty($filters['outcome'])) {
            $q->where('outcome', $filters['outcome']);
        }

        $q->orderByDesc('administered_at');

        return $q->paginate($perPage);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createTreatmentLog(array $data, int $actorUserId): NursingTreatmentLog
    {
        $facilityId = (int) $data['facility_id'];

        $visit = Visit::query()
            ->whereKey((int) $data['visit_id'])
            ->where('facility_id', $facilityId)
            ->firstOrFail();

        if ((int) $data['patient_id'] !== (int) $visit->patient_id) {
            throw ValidationException::withMessages([
                'patient_id' => ['Patient does not match the selected visit.'],
            ]);
        }

        $log = NursingTreatmentLog::query()->create([
            'facility_id' => $facilityId,
            'visit_id' => $visit->id,
            'patient_id' => $visit->patient_id,
            'ward_id' => $data['ward_id'] ?? null,
            'logged_by_user_id' => $actorUserId,
            'performed_at' => $data['performed_at'],
            'category' => $data['category'],
            'title' => $data['title'],
            'notes' => $data['notes'] ?? null,
        ]);

        return $log->fresh([
            'visit:id,visit_uuid',
            'patient:id,user_id',
            'patient.user:id,display_name,first_name,last_name',
            'ward:id,name,code',
            'loggedBy:id,display_name,first_name,last_name',
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateTreatmentLogs(int $facilityId, array $filters, int $perPage = 25): LengthAwarePaginator
    {
        $perPage = min(100, max(1, $perPage));

        $q = NursingTreatmentLog::query()
            ->where('facility_id', $facilityId)
            ->with([
                'visit:id,patient_id,visit_uuid',
                'patient:id,user_id',
                'patient.user:id,display_name,first_name,last_name',
                'ward:id,name,code',
                'loggedBy:id,display_name,first_name,last_name',
            ]);

        if (! empty($filters['visit_id'])) {
            $q->where('visit_id', (int) $filters['visit_id']);
        }

        if (! empty($filters['category'])) {
            $q->where('category', $filters['category']);
        }

        if (! empty($filters['from'])) {
            $q->where('performed_at', '>=', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $q->where('performed_at', '<=', $filters['to']);
        }

        $q->orderByDesc('performed_at');

        return $q->paginate($perPage);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function assertPrescriptionContext(int $facilityId, array $data): void
    {
        $visit = Visit::query()
            ->whereKey((int) $data['visit_id'])
            ->where('facility_id', $facilityId)
            ->firstOrFail();

        $rx = Prescription::query()
            ->whereKey((int) $data['prescription_id'])
            ->where('facility_id', $facilityId)
            ->firstOrFail();

        if ($rx->patient_id !== $visit->patient_id) {
            throw ValidationException::withMessages([
                'prescription_id' => ['Prescription does not belong to this visit’s patient.'],
            ]);
        }

        $item = PrescriptionItem::query()
            ->whereKey((int) $data['prescription_item_id'])
            ->where('prescription_id', $rx->id)
            ->first();

        if (! $item) {
            throw ValidationException::withMessages([
                'prescription_item_id' => ['Prescription line item not found for this prescription.'],
            ]);
        }
    }
}
