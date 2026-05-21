<?php

declare(strict_types=1);

namespace App\Services\FacilityShiftHandover;

use App\Models\FacilityShiftHandover;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class FacilityShiftHandoverService
{
    public function findForFacility(int $handoverId, int $facilityId): ?FacilityShiftHandover
    {
        return FacilityShiftHandover::query()
            ->whereKey($handoverId)
            ->where('facility_id', $facilityId)
            ->first();
    }

    public function findWithRelations(int $handoverId, int $facilityId): ?FacilityShiftHandover
    {
        return FacilityShiftHandover::query()
            ->whereKey($handoverId)
            ->where('facility_id', $facilityId)
            ->with([
                'ward:id,name,code',
                'handedOverBy:id,display_name,first_name,last_name',
                'handedOverTo:id,display_name,first_name,last_name',
                'createdBy:id,display_name',
                'updatedBy:id,display_name',
            ])
            ->first();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateIndex(int $facilityId, array $filters, int $perPage = 20): LengthAwarePaginator
    {
        $q = FacilityShiftHandover::query()
            ->where('facility_id', $facilityId)
            ->with([
                'ward:id,name,code',
                'handedOverBy:id,display_name,first_name,last_name',
                'handedOverTo:id,display_name,first_name,last_name',
            ]);

        if (! empty($filters['ward_id'])) {
            $q->where('ward_id', (int) $filters['ward_id']);
        }

        if (! empty($filters['shift_date'])) {
            $q->whereDate('shift_date', $filters['shift_date']);
        }

        if (! empty($filters['shift_date_from'])) {
            $q->whereDate('shift_date', '>=', $filters['shift_date_from']);
        }

        if (! empty($filters['shift_date_to'])) {
            $q->whereDate('shift_date', '<=', $filters['shift_date_to']);
        }

        if (! empty($filters['status'])) {
            $q->where('status', $filters['status']);
        }

        if (! empty($filters['shift_slot'])) {
            $q->where('shift_slot', $filters['shift_slot']);
        }

        return $q->orderByDesc('shift_date')->orderByDesc('id')->paginate($perPage);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, int $actorUserId): FacilityShiftHandover
    {
        return DB::transaction(function () use ($data, $actorUserId) {
            $row = FacilityShiftHandover::query()->create([
                'facility_id' => (int) $data['facility_id'],
                'ward_id' => $data['ward_id'] ?? null,
                'shift_date' => $data['shift_date'] ?? today()->toDateString(),
                'shift_slot' => $data['shift_slot'] ?? 'morning',
                'shift_label' => $data['shift_label'] ?? null,
                'outgoing_summary' => $data['outgoing_summary'],
                'pending_tasks_highlight' => $data['pending_tasks_highlight'] ?? null,
                'incidents_notes' => $data['incidents_notes'] ?? null,
                'equipment_issues' => $data['equipment_issues'] ?? null,
                'staffing_notes' => $data['staffing_notes'] ?? null,
                'handed_over_by_user_id' => $data['handed_over_by_user_id'] ?? $actorUserId,
                'received_by_user_id' => $data['received_by_user_id'] ?? null,
                'handed_over_at' => ($data['status'] ?? 'draft') === 'submitted' ? ($data['handed_over_at'] ?? now()) : ($data['handed_over_at'] ?? null),
                'status' => $data['status'] ?? 'draft',
                'created_by_user_id' => $actorUserId,
                'updated_by_user_id' => $actorUserId,
            ]);

            return $row->fresh([
                'ward:id,name,code',
                'handedOverBy:id,display_name,first_name,last_name',
                'handedOverTo:id,display_name,first_name,last_name',
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(FacilityShiftHandover $handover, array $data, int $actorUserId): FacilityShiftHandover
    {
        return DB::transaction(function () use ($handover, $data, $actorUserId) {
            $fields = [
                'ward_id', 'shift_date', 'shift_slot', 'shift_label',
                'outgoing_summary', 'pending_tasks_highlight', 'incidents_notes',
                'equipment_issues', 'staffing_notes',
                'handed_over_by_user_id', 'handed_over_at',
                'received_by_user_id', 'acknowledged_at', 'status',
            ];

            foreach ($fields as $key) {
                if (array_key_exists($key, $data)) {
                    $handover->{$key} = $data[$key];
                }
            }

            if (($handover->status ?? '') === 'submitted' && ! $handover->handed_over_at) {
                $handover->handed_over_at = now();
            }

            if (($handover->status ?? '') === 'acknowledged' && ! $handover->acknowledged_at) {
                $handover->acknowledged_at = now();
                if (empty($handover->received_by_user_id)) {
                    $handover->received_by_user_id = $actorUserId;
                }
            }

            $handover->updated_by_user_id = $actorUserId;
            $handover->save();

            return $handover->fresh([
                'ward:id,name,code',
                'handedOverBy:id,display_name,first_name,last_name',
                'handedOverTo:id,display_name,first_name,last_name',
            ]);
        });
    }
}
