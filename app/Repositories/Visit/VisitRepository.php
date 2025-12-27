<?php

namespace App\Repositories\Visit;

use App\Models\Visit;
use App\Repositories\Contracts\VisitRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Visit Repository Implementation
 *
 * Handles all database operations for Visit entity
 */
class VisitRepository implements VisitRepositoryInterface
{
    /**
     * Visit model instance
     *
     * @var Visit
     */
    protected $model;

    /**
     * Constructor
     *
     * @param Visit $model
     */
    public function __construct(Visit $model)
    {
        $this->model = $model;
    }

    /**
     * {@inheritDoc}
     */
    public function findByUuid(string $uuid): ?Visit
    {
        try {
            return $this->model->where('visit_uuid', $uuid)->first();
        } catch (\Exception $e) {
            Log::error('Failed to find visit by UUID', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function findById(int $id): ?Visit
    {
        try {
            return $this->model->find($id);
        } catch (\Exception $e) {
            Log::error('Failed to find visit by ID', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getAllPaginated(int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        try {
            $query = $this->model->with([
                'facility:id,name,code',
                'patient:id,first_name,last_name,medical_record_number',
                'currentDepartment:id,name,code',
            ]);

            // Apply filters
            if (!empty($filters['facility_id'])) {
                $query->where('facility_id', $filters['facility_id']);
            }

            if (!empty($filters['status'])) {
                $query->where('status', $filters['status']);
            }

            if (!empty($filters['visit_type'])) {
                $query->where('visit_type', $filters['visit_type']);
            }

            if (!empty($filters['current_phase'])) {
                $query->where('current_phase', $filters['current_phase']);
            }

            if (!empty($filters['date_from'])) {
                $query->where('arrived_at', '>=', $filters['date_from']);
            }

            if (!empty($filters['date_to'])) {
                $query->where('arrived_at', '<=', $filters['date_to']);
            }

            // Order by arrival date (most recent first)
            $query->orderBy('arrived_at', 'desc');

            return $query->paginate($perPage);
        } catch (\Exception $e) {
            Log::error('Failed to get paginated visits', [
                'perPage' => $perPage,
                'filters' => $filters,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getByFacility(int $facilityId, int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        try {
            $query = $this->model->where('facility_id', $facilityId)
                ->with([
                    'patient:id,first_name,last_name,medical_record_number',
                    'currentDepartment:id,name,code',
                ]);

            // Apply additional filters
            if (!empty($filters['status'])) {
                $query->where('status', $filters['status']);
            }

            if (!empty($filters['visit_type'])) {
                $query->where('visit_type', $filters['visit_type']);
            }

            if (!empty($filters['date_from'])) {
                $query->where('arrived_at', '>=', $filters['date_from']);
            }

            if (!empty($filters['date_to'])) {
                $query->where('arrived_at', '<=', $filters['date_to']);
            }

            $query->orderBy('arrived_at', 'desc');

            return $query->paginate($perPage);
        } catch (\Exception $e) {
            Log::error('Failed to get visits by facility', [
                'facility_id' => $facilityId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getByPatient(int $patientId, int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        try {
            $query = $this->model->where('patient_id', $patientId)
                ->with([
                    'facility:id,name,code',
                    'currentDepartment:id,name,code',
                ]);

            // Apply additional filters
            if (!empty($filters['status'])) {
                $query->where('status', $filters['status']);
            }

            if (!empty($filters['visit_type'])) {
                $query->where('visit_type', $filters['visit_type']);
            }

            if (!empty($filters['date_from'])) {
                $query->where('arrived_at', '>=', $filters['date_from']);
            }

            if (!empty($filters['date_to'])) {
                $query->where('arrived_at', '<=', $filters['date_to']);
            }

            $query->orderBy('arrived_at', 'desc');

            return $query->paginate($perPage);
        } catch (\Exception $e) {
            Log::error('Failed to get visits by patient', [
                'patient_id' => $patientId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function create(array $data): Visit
    {
        try {
            DB::beginTransaction();

            // Ensure visit_uuid is generated
            if (!isset($data['visit_uuid'])) {
                $data['visit_uuid'] = (string) \Illuminate\Support\Str::uuid();
            }

            $visit = $this->model->create($data);

            DB::commit();
            return $visit;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create visit', [
                'data' => $data,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function update(Visit $visit, array $data): Visit
    {
        try {
            DB::beginTransaction();

            $visit->update($data);
            $visit->refresh();

            DB::commit();
            return $visit;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update visit', [
                'visit_id' => $visit->id,
                'data' => $data,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function delete(Visit $visit): bool
    {
        try {
            DB::beginTransaction();

            $result = $visit->delete();

            DB::commit();
            return $result;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to delete visit', [
                'visit_id' => $visit->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function restore(Visit $visit): bool
    {
        try {
            DB::beginTransaction();

            $result = $visit->restore();

            DB::commit();
            return $result;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to restore visit', [
                'visit_id' => $visit->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function forceDelete(Visit $visit): bool
    {
        try {
            DB::beginTransaction();

            $result = $visit->forceDelete();

            DB::commit();
            return $result;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to force delete visit', [
                'visit_id' => $visit->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getLongWaitingVisits(int $minutesThreshold, ?int $facilityId = null): Collection
    {
        try {
            $query = $this->model->whereNotNull('waiting_since')
                ->where('status', 'active')
                ->whereRaw('TIMESTAMPDIFF(MINUTE, waiting_since, NOW()) >= ?', [$minutesThreshold]);

            if ($facilityId) {
                $query->where('facility_id', $facilityId);
            }

            return $query->with([
                'patient:id,first_name,last_name',
                'currentDepartment:id,name',
            ])->get();
        } catch (\Exception $e) {
            Log::error('Failed to get long waiting visits', [
                'threshold' => $minutesThreshold,
                'facility_id' => $facilityId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getActiveVisitsByDepartment(int $departmentId): Collection
    {
        try {
            return $this->model->where('current_department_id', $departmentId)
                ->where('status', 'active')
                ->with([
                    'patient:id,first_name,last_name,date_of_birth',
                    'facility:id,name',
                ])
                ->orderBy('acuity_score', 'asc')
                ->orderBy('waiting_since', 'asc')
                ->get();
        } catch (\Exception $e) {
            Log::error('Failed to get active visits by department', [
                'department_id' => $departmentId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getByStatus(string $status, int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        try {
            $query = $this->model->where('status', $status)
                ->with([
                    'facility:id,name',
                    'patient:id,first_name,last_name',
                ]);

            // Apply additional filters
            if (!empty($filters['facility_id'])) {
                $query->where('facility_id', $filters['facility_id']);
            }

            if (!empty($filters['visit_type'])) {
                $query->where('visit_type', $filters['visit_type']);
            }

            if (!empty($filters['date_from'])) {
                $query->where('arrived_at', '>=', $filters['date_from']);
            }

            if (!empty($filters['date_to'])) {
                $query->where('arrived_at', '<=', $filters['date_to']);
            }

            $query->orderBy('arrived_at', 'desc');

            return $query->paginate($perPage);
        } catch (\Exception $e) {
            Log::error('Failed to get visits by status', [
                'status' => $status,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getByDateRange(string $startDate, string $endDate, int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        try {
            $query = $this->model->whereBetween('arrived_at', [$startDate, $endDate])
                ->with([
                    'facility:id,name',
                    'patient:id,first_name,last_name',
                    'currentDepartment:id,name',
                ]);

            // Apply additional filters
            if (!empty($filters['facility_id'])) {
                $query->where('facility_id', $filters['facility_id']);
            }

            if (!empty($filters['status'])) {
                $query->where('status', $filters['status']);
            }

            if (!empty($filters['visit_type'])) {
                $query->where('visit_type', $filters['visit_type']);
            }

            $query->orderBy('arrived_at', 'desc');

            return $query->paginate($perPage);
        } catch (\Exception $e) {
            Log::error('Failed to get visits by date range', [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function updatePhase(Visit $visit, string $phase, array $additionalData = []): Visit
    {
        try {
            DB::beginTransaction();

            $updateData = array_merge(['current_phase' => $phase], $additionalData);

            // If moving out of waiting phases, clear waiting_since
            if (!in_array($phase, ['waiting_triage', 'waiting_provider', 'awaiting_results'])) {
                $updateData['waiting_since'] = null;
            } elseif (!$visit->waiting_since) {
                $updateData['waiting_since'] = now();
            }

            $visit->update($updateData);
            $visit->refresh();

            DB::commit();
            return $visit;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update visit phase', [
                'visit_id' => $visit->id,
                'phase' => $phase,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function updateStatus(Visit $visit, string $status, array $additionalData = []): Visit
    {
        try {
            DB::beginTransaction();

            $updateData = array_merge(['status' => $status], $additionalData);

            // Handle status-specific logic
            if ($status === 'completed') {
                if (!$visit->clinical_care_ended_at) {
                    $updateData['clinical_care_ended_at'] = now();
                }
                if (!$visit->actual_duration_minutes && $visit->clinical_care_started_at) {
                    $updateData['actual_duration_minutes'] = $visit->calculateActualDuration();
                }
            } elseif ($status === 'cancelled') {
                if (!isset($updateData['cancelled_at'])) {
                    $updateData['cancelled_at'] = now();
                }
            }

            $visit->update($updateData);
            $visit->refresh();

            DB::commit();
            return $visit;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update visit status', [
                'visit_id' => $visit->id,
                'status' => $status,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function discharge(Visit $visit, array $dischargeData): Visit
    {
        try {
            DB::beginTransaction();

            $updateData = array_merge($dischargeData, [
                'discharged_at' => $dischargeData['discharged_at'] ?? now(),
                'status' => 'completed',
                'current_phase' => 'discharged',
            ]);

            // Calculate actual duration if not provided
            if (!isset($updateData['actual_duration_minutes']) && $visit->clinical_care_started_at) {
                $updateData['actual_duration_minutes'] = $visit->calculateActualDuration();
            }

            $visit->update($updateData);
            $visit->refresh();

            DB::commit();
            return $visit;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to discharge visit', [
                'visit_id' => $visit->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}