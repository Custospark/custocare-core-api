<?php

namespace App\Repositories\Appointment;

use App\Models\Appointment;
use App\Repositories\Contracts\AppointmentRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AppointmentRepository implements AppointmentRepositoryInterface
{
    /**
     * Model instance
     */
    protected Appointment $model;

    /**
     * Constructor
     */
    public function __construct(Appointment $model)
    {
        $this->model = $model;
    }

    /**
     * {@inheritdoc}
     */
    public function findById(int $id): ?Appointment
    {
        try {
            return $this->model->with([
                'facility',
                'patient',
                'provider',
                'visit'
            ])->find($id);
        } catch (\Exception $e) {
            Log::error('Failed to find appointment by ID', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function findByUuid(string $uuid): ?Appointment
    {
        try {
            return $this->model->with([
                'facility',
                'patient',
                'provider',
                'visit'
            ])->where('appointment_uuid', $uuid)->first();
        } catch (\Exception $e) {
            Log::error('Failed to find appointment by UUID', [
                'uuid' => $uuid,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function all(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        try {
            $query = $this->model->with([
                'facility:id,name',
                'patient:id,first_name,last_name',
                'provider:id,first_name,last_name'
            ]);

            // Apply filters
            if (!empty($filters['facility_id'])) {
                $query->where('facility_id', $filters['facility_id']);
            }

            if (!empty($filters['patient_id'])) {
                $query->where('patient_id', $filters['patient_id']);
            }

            if (!empty($filters['provider_staff_id'])) {
                $query->where('provider_staff_id', $filters['provider_staff_id']);
            }

            if (!empty($filters['status'])) {
                $query->where('status', $filters['status']);
            }

            if (!empty($filters['appointment_type'])) {
                $query->where('appointment_type', $filters['appointment_type']);
            }

            if (!empty($filters['date_from'])) {
                $query->whereDate('scheduled_start_time', '>=', $filters['date_from']);
            }

            if (!empty($filters['date_to'])) {
                $query->whereDate('scheduled_start_time', '<=', $filters['date_to']);
            }

            if (!empty($filters['search'])) {
                $query->where(function ($q) use ($filters) {
                    $q->whereHas('patient', function ($patientQuery) use ($filters) {
                        $patientQuery->where('first_name', 'like', "%{$filters['search']}%")
                            ->orWhere('last_name', 'like', "%{$filters['search']}%");
                    });
                });
            }

            if (isset($filters['upcoming'])) {
                $upcoming = $filters['upcoming'];
                $truthy = $upcoming === true || $upcoming === 1 || $upcoming === '1' || $upcoming === 'true';
                if ($truthy) {
                    $query->where('scheduled_start_time', '>', now());
                }
            }

            // Default ordering
            $query->orderBy('scheduled_start_time', 'desc');

            return $query->paginate($perPage);
        } catch (\Exception $e) {
            Log::error('Failed to fetch appointments', [
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);
            
            // Return empty paginator instead of throwing exception
            return new \Illuminate\Pagination\LengthAwarePaginator(
                [],
                0,
                $perPage,
                1
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function create(array $data): Appointment
{
    try {
        DB::beginTransaction();

        if (!isset($data['appointment_uuid'])) {
            $data['appointment_uuid'] = Appointment::generateUuid();
        }

        if (isset($data['scheduled_start_time'], $data['duration_minutes'])) {
            $data['scheduled_end_time'] = Carbon::parse($data['scheduled_start_time'])
                ->addMinutes($data['duration_minutes']);
        }

        $appointment = $this->model->create($data);

        DB::commit();

        return $appointment->load(['facility', 'patient', 'provider']);
    } catch (\Throwable $e) {
        DB::rollBack();

        Log::error('Failed to create appointment', [
            'data' => $data,
            'error' => $e->getMessage(),
        ]);

        // ✅ Preserve contract: never return null
        throw $e;
    }
}


    /**
     * {@inheritdoc}
     */
    public function update(Appointment $appointment, array $data): Appointment
    {
        try {
            DB::beginTransaction();

            // Recalculate end time if start time or duration changed
            if (isset($data['scheduled_start_time']) || isset($data['duration_minutes'])) {
                $startTime = $data['scheduled_start_time'] ?? $appointment->scheduled_start_time;
                $duration = $data['duration_minutes'] ?? $appointment->duration_minutes;
                
                $data['scheduled_end_time'] = Carbon::parse($startTime)->addMinutes($duration);
            }

            // Update status timestamps
            if (isset($data['status'])) {
                $this->updateStatusTimestamps($appointment, $data['status'], $data);
            }

            $appointment->update($data);
            
            DB::commit();
            
            return $appointment->fresh(['facility', 'patient', 'provider', 'visit']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update appointment', [
                'appointment_id' => $appointment->id,
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            
            return $appointment;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function delete(Appointment $appointment): bool
    {
        try {
            return $appointment->delete();
        } catch (\Exception $e) {
            Log::error('Failed to delete appointment', [
                'appointment_id' => $appointment->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function forceDelete(Appointment $appointment): bool
    {
        try {
            return $appointment->forceDelete();
        } catch (\Exception $e) {
            Log::error('Failed to force delete appointment', [
                'appointment_id' => $appointment->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function restore(Appointment $appointment): bool
    {
        try {
            return $appointment->restore();
        } catch (\Exception $e) {
            Log::error('Failed to restore appointment', [
                'appointment_id' => $appointment->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getByFacility(int $facilityId, array $filters = []): Collection
    {
        try {
            $query = $this->model->with(['patient', 'provider'])
                ->where('facility_id', $facilityId);

            return $this->applyCommonFilters($query, $filters)->get();
        } catch (\Exception $e) {
            Log::error('Failed to get appointments by facility', [
                'facility_id' => $facilityId,
                'error' => $e->getMessage()
            ]);
            return new Collection();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getByPatient(int $patientId, array $filters = []): Collection
    {
        try {
            $query = $this->model->with(['facility', 'provider'])
                ->where('patient_id', $patientId);

            return $this->applyCommonFilters($query, $filters)->get();
        } catch (\Exception $e) {
            Log::error('Failed to get appointments by patient', [
                'patient_id' => $patientId,
                'error' => $e->getMessage()
            ]);
            return new Collection();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getByProvider(int $providerId, array $filters = []): Collection
    {
        try {
            $query = $this->model->with(['facility', 'patient'])
                ->where('provider_staff_id', $providerId);

            return $this->applyCommonFilters($query, $filters)->get();
        } catch (\Exception $e) {
            Log::error('Failed to get appointments by provider', [
                'provider_id' => $providerId,
                'error' => $e->getMessage()
            ]);
            return new Collection();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getUpcoming(array $filters = []): Collection
    {
        try {
            $query = $this->model->with(['facility', 'patient', 'provider'])
                ->upcoming();

            return $this->applyCommonFilters($query, $filters)->get();
        } catch (\Exception $e) {
            Log::error('Failed to get upcoming appointments', [
                'error' => $e->getMessage()
            ]);
            return new Collection();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getByDateRange(Carbon $startDate, Carbon $endDate, array $filters = []): Collection
    {
        try {
            $query = $this->model->with(['facility', 'patient', 'provider'])
                ->whereBetween('scheduled_start_time', [$startDate, $endDate]);

            return $this->applyCommonFilters($query, $filters)->get();
        } catch (\Exception $e) {
            Log::error('Failed to get appointments by date range', [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'error' => $e->getMessage()
            ]);
            return new Collection();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function updateStatus(Appointment $appointment, string $status, array $additionalData = []): Appointment
    {
        try {
            DB::beginTransaction();

            $updateData = array_merge(['status' => $status], $additionalData);
            $this->updateStatusTimestamps($appointment, $status, $updateData);

            $appointment->update($updateData);
            
            DB::commit();
            
            return $appointment->fresh();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update appointment status', [
                'appointment_id' => $appointment->id,
                'status' => $status,
                'error' => $e->getMessage()
            ]);
            return $appointment;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function hasSchedulingConflict(
        int $facilityId,
        int $providerId,
        Carbon $startTime,
        Carbon $endTime,
        ?int $excludeAppointmentId = null
    ): bool {
        try {
            $query = $this->model->where('facility_id', $facilityId)
                ->where('provider_staff_id', $providerId)
                ->where(function ($q) use ($startTime, $endTime) {
                    $q->whereBetween('scheduled_start_time', [$startTime, $endTime])
                        ->orWhereBetween('scheduled_end_time', [$startTime, $endTime])
                        ->orWhere(function ($innerQ) use ($startTime, $endTime) {
                            $innerQ->where('scheduled_start_time', '<', $startTime)
                                ->where('scheduled_end_time', '>', $endTime);
                        });
                })
                ->whereNotIn('status', [
                    Appointment::STATUS_CANCELLED,
                    Appointment::STATUS_NO_SHOW,
                    Appointment::STATUS_RESCHEDULED
                ]);

            if ($excludeAppointmentId) {
                $query->where('id', '!=', $excludeAppointmentId);
            }

            return $query->exists();
        } catch (\Exception $e) {
            Log::error('Failed to check scheduling conflict', [
                'facility_id' => $facilityId,
                'provider_id' => $providerId,
                'error' => $e->getMessage()
            ]);
            return false; // Assume no conflict on error
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getStatistics(array $filters = []): array
    {
        try {
            $query = $this->model->query();

            // Apply filters
            if (!empty($filters['facility_id'])) {
                $query->where('facility_id', $filters['facility_id']);
            }

            if (!empty($filters['date_from'])) {
                $query->whereDate('scheduled_start_time', '>=', $filters['date_from']);
            }

            if (!empty($filters['date_to'])) {
                $query->whereDate('scheduled_start_time', '<=', $filters['date_to']);
            }

            return [
                'total' => $query->count(),
                'scheduled' => (clone $query)->where('status', Appointment::STATUS_SCHEDULED)->count(),
                'confirmed' => (clone $query)->where('status', Appointment::STATUS_CONFIRMED)->count(),
                'completed' => (clone $query)->where('status', Appointment::STATUS_COMPLETED)->count(),
                'cancelled' => (clone $query)->where('status', Appointment::STATUS_CANCELLED)->count(),
                'no_show' => (clone $query)->where('status', Appointment::STATUS_NO_SHOW)->count(),
                'average_duration' => (clone $query)->whereNotNull('duration_minutes')->avg('duration_minutes'),
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get appointment statistics', [
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);
            return [
                'total' => 0,
                'scheduled' => 0,
                'confirmed' => 0,
                'completed' => 0,
                'cancelled' => 0,
                'no_show' => 0,
                'average_duration' => 0,
            ];
        }
    }

    /**
     * Update status-related timestamps
     */
    private function updateStatusTimestamps(Appointment $appointment, string $status, array &$data): void
    {
        $now = now();

        switch ($status) {
            case Appointment::STATUS_CONFIRMED:
                $data['confirmed_at'] = $now;
                break;
            case Appointment::STATUS_CHECKED_IN:
                $data['checked_in_at'] = $now;
                break;
            case Appointment::STATUS_CANCELLED:
                $data['cancelled_at'] = $now;
                break;
        }
    }

    /**
     * Apply common filters to query
     */
    private function applyCommonFilters($query, array $filters)
    {
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['appointment_type'])) {
            $query->where('appointment_type', $filters['appointment_type']);
        }

        if (!empty($filters['date'])) {
            $query->whereDate('scheduled_start_time', $filters['date']);
        }

        if (!empty($filters['upcoming'])) {
            $query->where('scheduled_start_time', '>', now());
        }

        // Default ordering
        $query->orderBy('scheduled_start_time', 'asc');

        return $query;
    }
}