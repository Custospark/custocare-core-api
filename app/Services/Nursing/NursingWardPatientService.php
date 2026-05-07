<?php

declare(strict_types=1);

namespace App\Services\Nursing;

use App\Http\Resources\PatientSearchResource;
use App\Models\Visit;
use App\Models\Ward;

/**
 * Lists active visits that have a ward/bed assignment in visit metadata (nursing_ward_bed).
 */
class NursingWardPatientService
{
    /**
     * @return array{success: bool, message: string, meta: array<string, mixed>}
     */
    public function listForFacility(int $facilityId, ?int $wardId, int $limit): array
    {
        $limit = min(200, max(1, $limit));

        $query = Visit::query()
            ->where('facility_id', $facilityId)
            ->whereIn('status', ['active', 'in_progress'])
            ->whereNull('deleted_at')
            ->whereNotNull('metadata->nursing_ward_bed->ward_id');

        if ($wardId !== null && $wardId > 0) {
            $query->where('metadata->nursing_ward_bed->ward_id', $wardId);
        }

        $visits = $query
            ->with(['patient.user'])
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get();

        $wardIds = $visits
            ->map(fn ($v) => (int) data_get($v->metadata, 'nursing_ward_bed.ward_id'))
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        $wards = Ward::query()
            ->where('facility_id', $facilityId)
            ->whereIn('id', $wardIds)
            ->get(['id', 'name', 'code'])
            ->keyBy('id');

        $queueVisits = $visits->map(function ($v) use ($wards) {
            $assignment = data_get($v->metadata, 'nursing_ward_bed', []);
            $wId = (int) data_get($assignment, 'ward_id');
            $ward = $wards->get($wId);

            return [
                'visit_id' => $v->id,
                'visit_uuid' => $v->visit_uuid,
                'facility_id' => $v->facility_id,

                'patient_id' => $v->patient_id,
                'patient' => $v->patient
                    ? (new PatientSearchResource($v->patient))->resolve()
                    : null,

                'current_phase' => $v->current_phase,
                'current_department_id' => $v->current_department_id,

                'assigned_staff_id' => $v->assigned_staff_id,
                'assigned_at' => optional($v->assigned_at)?->toISOString(),

                'waiting_since' => optional($v->waiting_since)?->toISOString(),
                'acuity_score' => $v->acuity_score,
                'arrived_at' => optional($v->arrived_at)?->toISOString(),

                'visit_type' => $v->visit_type,
                'status' => $v->status,
                'is_walk_in' => (bool) $v->is_walk_in,

                'ward_assignment' => [
                    'ward_id' => $wId > 0 ? $wId : null,
                    'ward_name' => $ward?->name,
                    'ward_code' => $ward?->code,
                    'bed_id' => (int) data_get($assignment, 'bed_id') ?: null,
                    'bed_label' => data_get($assignment, 'bed_label'),
                    'room_label' => data_get($assignment, 'room_label'),
                ],
            ];
        })->values();

        return [
            'success' => true,
            'message' => 'Ward patients retrieved successfully.',
            'meta' => [
                'facility_id' => $facilityId,
                'ward_id' => $wardId,
                'queue_visits' => $queueVisits,
                'total' => $queueVisits->count(),
            ],
        ];
    }
}
