<?php

namespace App\Repositories\MedicationDispense;

use App\Models\MedicationDispense;
use App\Repositories\Contracts\MedicationDispenseRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MedicationDispenseRepository implements MedicationDispenseRepositoryInterface
{
    /**
     * @var MedicationDispense
     */
    protected $model;

    /**
     * Repository constructor.
     *
     * @param MedicationDispense $model
     */
    public function __construct(MedicationDispense $model)
    {
        $this->model = $model;
    }

    /**
     * {@inheritdoc}
     */
    public function findByUuid(string $uuid): ?MedicationDispense
    {
        try {
            return $this->model->where('dispense_uuid', $uuid)->first();
        } catch (\Exception $e) {
            Log::error('Failed to find medication dispense by UUID', [
                'uuid' => $uuid,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function findById(int $id): ?MedicationDispense
    {
        try {
            return $this->model->find($id);
        } catch (\Exception $e) {
            Log::error('Failed to find medication dispense by ID', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getAllPaginated(array $filters = [], int $perPage = 20, array $relations = []): LengthAwarePaginator
    {
        try {
            $query = $this->model->with($relations);

            // Apply filters
            if (isset($filters['facility_id'])) {
                $query->where('facility_id', $filters['facility_id']);
            }

            if (isset($filters['status'])) {
                $query->where('status', $filters['status']);
            }

            if (isset($filters['patient_id'])) {
                $query->where('patient_id', $filters['patient_id']);
            }

            if (isset($filters['prescription_id'])) {
                $query->where('prescription_id', $filters['prescription_id']);
            }

            if (isset($filters['start_date'])) {
                $query->where('dispensed_at', '>=', $filters['start_date']);
            }

            if (isset($filters['end_date'])) {
                $query->where('dispensed_at', '<=', $filters['end_date']);
            }

            if (isset($filters['verified_only'])) {
                $query->whereNotNull('checked_by_staff_id');
            }

            // Sort by dispensed_at descending by default (most recent first)
            $query->orderBy('dispensed_at', 'desc');

            return $query->paginate($perPage);
        } catch (\Exception $e) {
            Log::error('Failed to get paginated medication dispenses', [
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);
            return new LengthAwarePaginator([], 0, $perPage);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getByPrescriptionId(int $prescriptionId, array $relations = []): Collection
    {
        try {
            return $this->model->with($relations)
                ->where('prescription_id', $prescriptionId)
                ->orderBy('dispensed_at', 'desc')
                ->get();
        } catch (\Exception $e) {
            Log::error('Failed to get dispenses by prescription ID', [
                'prescription_id' => $prescriptionId,
                'error' => $e->getMessage()
            ]);
            return collect();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getByPatientId(int $patientId, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        try {
            $query = $this->model->where('patient_id', $patientId);

            if (isset($filters['status'])) {
                $query->where('status', $filters['status']);
            }

            if (isset($filters['start_date'])) {
                $query->where('dispensed_at', '>=', $filters['start_date']);
            }

            if (isset($filters['end_date'])) {
                $query->where('dispensed_at', '<=', $filters['end_date']);
            }

            $query->orderBy('dispensed_at', 'desc');

            return $query->paginate($perPage);
        } catch (\Exception $e) {
            Log::error('Failed to get dispenses by patient ID', [
                'patient_id' => $patientId,
                'error' => $e->getMessage()
            ]);
            return new LengthAwarePaginator([], 0, $perPage);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getByFacilityId(int $facilityId, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        try {
            $query = $this->model->where('facility_id', $facilityId);

            if (isset($filters['status'])) {
                $query->where('status', $filters['status']);
            }

            if (isset($filters['patient_id'])) {
                $query->where('patient_id', $filters['patient_id']);
            }

            if (isset($filters['start_date'])) {
                $query->where('dispensed_at', '>=', $filters['start_date']);
            }

            if (isset($filters['end_date'])) {
                $query->where('dispensed_at', '<=', $filters['end_date']);
            }

            $query->orderBy('dispensed_at', 'desc');

            return $query->paginate($perPage);
        } catch (\Exception $e) {
            Log::error('Failed to get dispenses by facility ID', [
                'facility_id' => $facilityId,
                'error' => $e->getMessage()
            ]);
            return new LengthAwarePaginator([], 0, $perPage);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function create(array $data): MedicationDispense
    {
        try {
            return $this->model->create($data);
        } catch (\Exception $e) {
            Log::error('Failed to create medication dispense', [
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function update(int $id, array $data): MedicationDispense
    {
        try {
            $dispense = $this->findById($id);
            if (!$dispense) {
                throw new \Exception("Medication dispense not found");
            }

            $dispense->update($data);
            return $dispense->fresh();
        } catch (\Exception $e) {
            Log::error('Failed to update medication dispense', [
                'id' => $id,
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function updateByUuid(string $uuid, array $data): MedicationDispense
    {
        try {
            $dispense = $this->findByUuid($uuid);
            if (!$dispense) {
                throw new \Exception("Medication dispense not found");
            }

            $dispense->update($data);
            return $dispense->fresh();
        } catch (\Exception $e) {
            Log::error('Failed to update medication dispense by UUID', [
                'uuid' => $uuid,
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function delete(int $id): bool
    {
        try {
            $dispense = $this->findById($id);
            if (!$dispense) {
                return false;
            }

            return $dispense->delete();
        } catch (\Exception $e) {
            Log::error('Failed to delete medication dispense', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function deleteByUuid(string $uuid): bool
    {
        try {
            $dispense = $this->findByUuid($uuid);
            if (!$dispense) {
                return false;
            }

            return $dispense->delete();
        } catch (\Exception $e) {
            Log::error('Failed to delete medication dispense by UUID', [
                'uuid' => $uuid,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function verifyDispense(int $id, int $pharmacistId, string $notes): MedicationDispense
    {
        try {
            $dispense = $this->findById($id);
            if (!$dispense) {
                throw new \Exception("Medication dispense not found");
            }

            $updateData = [
                'checked_by_staff_id' => $pharmacistId,
                'checked_at' => now(),
                'pharmacist_notes' => $notes
            ];

            return $this->update($id, $updateData);
        } catch (\Exception $e) {
            Log::error('Failed to verify medication dispense', [
                'id' => $id,
                'pharmacist_id' => $pharmacistId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function markAsPickedUp(int $id, array $pickupData): MedicationDispense
    {
        try {
            $dispense = $this->findById($id);
            if (!$dispense) {
                throw new \Exception("Medication dispense not found");
            }

            $updateData = array_merge($pickupData, [
                'picked_up_at' => now(),
                'status' => 'dispensed' // Ensure status remains dispensed
            ]);

            return $this->update($id, $updateData);
        } catch (\Exception $e) {
            Log::error('Failed to mark dispense as picked up', [
                'id' => $id,
                'pickup_data' => $pickupData,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function updateStatus(int $id, string $status, ?string $reason = null): MedicationDispense
    {
        try {
            $dispense = $this->findById($id);
            if (!$dispense) {
                throw new \Exception("Medication dispense not found");
            }

            $updateData = ['status' => $status];
            if ($reason) {
                $updateData['pharmacist_notes'] = ($dispense->pharmacist_notes ? $dispense->pharmacist_notes . "\n" : '') . "Status changed: $reason";
            }

            return $this->update($id, $updateData);
        } catch (\Exception $e) {
            Log::error('Failed to update dispense status', [
                'id' => $id,
                'status' => $status,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getFacilityStatistics(int $facilityId, string $startDate, string $endDate): array
    {
        try {
            return DB::table('medication_dispenses')
                ->selectRaw('
                    COUNT(*) as total_dispenses,
                    SUM(CASE WHEN checked_by_staff_id IS NOT NULL THEN 1 ELSE 0 END) as verified_dispenses,
                    SUM(CASE WHEN patient_counseling_provided = 1 THEN 1 ELSE 0 END) as counseling_provided_count,
                    SUM(CASE WHEN all_safety_checks_passed = 1 THEN 1 ELSE 0 END) as safety_checks_passed_count,
                    COUNT(DISTINCT patient_id) as unique_patients,
                    COUNT(DISTINCT dispensed_by_staff_id) as unique_dispensing_staff,
                    SUM(quantity_dispensed) as total_quantity_dispensed,
                    AVG(quantity_dispensed) as average_quantity_per_dispense,
                    COUNT(DISTINCT CASE WHEN status = "not_picked_up" THEN id END) as not_picked_up_count
                ')
                ->where('facility_id', $facilityId)
                ->whereBetween('dispensed_at', [$startDate, $endDate])
                ->first()
                ?? [];
        } catch (\Exception $e) {
            Log::error('Failed to get facility statistics', [
                'facility_id' => $facilityId,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
}