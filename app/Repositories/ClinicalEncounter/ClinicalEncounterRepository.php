<?php

namespace App\Repositories\ClinicalEncounter;

use App\Models\ClinicalEncounter;
use App\Repositories\Contracts\ClinicalEncounterRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ClinicalEncounterRepository implements ClinicalEncounterRepositoryInterface
{
    /**
     * Base query builder with relationships
     *
     * @return Builder
     */
    private function baseQuery(): Builder
    {
        return ClinicalEncounter::with([
            'visit',
            'patient',
            'primaryProvider',
            'supervisingProvider',
            'department',
            'facility',
            'amendedFrom',
            'createdBy',
            'updatedBy',
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function findById(int $id): ClinicalEncounter
    {
        try {
            return $this->baseQuery()->findOrFail($id);
        } catch (ModelNotFoundException $e) {
            Log::warning('Clinical encounter not found by ID', ['id' => $id]);
            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function findByUuid(string $uuid): ClinicalEncounter
    {
        try {
            return $this->baseQuery()->where('encounter_uuid', $uuid)->firstOrFail();
        } catch (ModelNotFoundException $e) {
            Log::warning('Clinical encounter not found by UUID', ['uuid' => $uuid]);
            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getAllPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->baseQuery();

        // Apply filters
        if (!empty($filters)) {
            $query = $this->applyFilters($query, $filters);
        }

        // Order by most recent documented encounters first
        return $query->orderBy('documented_at', 'desc')->paginate($perPage);
    }

    /**
     * {@inheritdoc}
     */
    public function getByVisitId(int $visitId, array $filters = []): Collection
    {
        $query = $this->baseQuery()->where('visit_id', $visitId);

        if (!empty($filters)) {
            $query = $this->applyFilters($query, $filters);
        }

        return $query->orderBy('documented_at', 'asc')->get();
    }

    /**
     * {@inheritdoc}
     */
    public function getByPatientId(int $patientId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->baseQuery()->where('patient_id', $patientId);

        if (!empty($filters)) {
            $query = $this->applyFilters($query, $filters);
        }

        return $query->orderBy('documented_at', 'desc')->paginate($perPage);
    }

    /**
     * {@inheritdoc}
     */
    public function getByProviderId(int $providerId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->baseQuery()
            ->where(function ($q) use ($providerId) {
                $q->where('primary_provider_staff_id', $providerId)
                  ->orWhere('supervising_provider_staff_id', $providerId);
            });

        if (!empty($filters)) {
            $query = $this->applyFilters($query, $filters);
        }

        return $query->orderBy('documented_at', 'desc')->paginate($perPage);
    }

    /**
     * {@inheritdoc}
     */
    public function create(array $data): ClinicalEncounter
    {
        try {
            DB::beginTransaction();

            $encounter = ClinicalEncounter::create($data);

            // If this is an amendment, link it to the original
            if (isset($data['amended_from_encounter_id'])) {
                $original = ClinicalEncounter::find($data['amended_from_encounter_id']);
                if ($original) {
                    $original->update(['documentation_status' => 'amended']);
                }
            }

            DB::commit();
            return $encounter->loadMissing(['visit', 'patient', 'primaryProvider', 'department']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create clinical encounter', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function update(ClinicalEncounter $encounter, array $data): ClinicalEncounter
    {
        try {
            DB::beginTransaction();

            // Prevent updating signed encounters unless amending
            if ($encounter->signed_at && !isset($data['amendment_reason'])) {
                throw new \RuntimeException('Cannot update signed encounter without amendment reason');
            }

            $encounter->update($data);

            DB::commit();
            return $encounter->refresh();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update clinical encounter', [
                'encounter_id' => $encounter->id,
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function delete(ClinicalEncounter $encounter): bool
    {
        try {
            // Prevent deleting signed encounters
            if ($encounter->signed_at) {
                throw new \RuntimeException('Cannot delete signed encounter');
            }

            return $encounter->delete();
        } catch (\Exception $e) {
            Log::error('Failed to delete clinical encounter', [
                'encounter_id' => $encounter->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function restore(ClinicalEncounter $encounter): bool
    {
        try {
            return $encounter->restore();
        } catch (\Exception $e) {
            Log::error('Failed to restore clinical encounter', [
                'encounter_id' => $encounter->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function forceDelete(ClinicalEncounter $encounter): bool
    {
        try {
            // Only allow force delete for non-signed encounters
            if ($encounter->signed_at) {
                throw new \RuntimeException('Cannot permanently delete signed encounter');
            }

            return $encounter->forceDelete();
        } catch (\Exception $e) {
            Log::error('Failed to force delete clinical encounter', [
                'encounter_id' => $encounter->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getRequiringAttention(int $facilityId): Collection
    {
        return $this->baseQuery()
            ->where('facility_id', $facilityId)
            ->where('requires_immediate_attention', true)
            ->whereIn('documentation_status', ['in_progress', 'completed'])
            ->orderBy('documented_at', 'asc')
            ->get();
    }

    /**
     * {@inheritdoc}
     */
    public function getByDocumentationStatus(string $status, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->baseQuery()->where('documentation_status', $status);

        if (!empty($filters)) {
            $query = $this->applyFilters($query, $filters);
        }

        return $query->orderBy('documented_at', 'desc')->paginate($perPage);
    }

    /**
     * {@inheritdoc}
     */
    public function getIncompleteDocumentation(int $facilityId, int $daysThreshold = 3): Collection
    {
        $cutoffDate = now()->subDays($daysThreshold);

        return $this->baseQuery()
            ->where('facility_id', $facilityId)
            ->where('documentation_status', 'in_progress')
            ->where('documented_at', '<=', $cutoffDate)
            ->orderBy('documented_at', 'asc')
            ->get();
    }

    /**
     * {@inheritdoc}
     */
    public function search(array $criteria, int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->baseQuery();

        // Search by patient name (assuming patient relationship exists)
        if (isset($criteria['patient_name'])) {
            $query->whereHas('patient', function ($q) use ($criteria) {
                $q->where('first_name', 'like', "%{$criteria['patient_name']}%")
                  ->orWhere('last_name', 'like', "%{$criteria['patient_name']}%");
            });
        }

        // Search by provider name
        if (isset($criteria['provider_name'])) {
            $query->whereHas('primaryProvider', function ($q) use ($criteria) {
                $q->where('first_name', 'like', "%{$criteria['provider_name']}%")
                  ->orWhere('last_name', 'like', "%{$criteria['provider_name']}%");
            });
        }

        // Search by diagnosis codes
        if (isset($criteria['diagnosis_code'])) {
            $query->whereJsonContains('assessment_diagnosis_codes->code', $criteria['diagnosis_code']);
        }

        // Date range search
        if (isset($criteria['start_date'])) {
            $query->where('documented_at', '>=', $criteria['start_date']);
        }
        if (isset($criteria['end_date'])) {
            $query->where('documented_at', '<=', $criteria['end_date']);
        }

        return $query->orderBy('documented_at', 'desc')->paginate($perPage);
    }

    /**
     * Apply filters to query
     *
     * @param Builder $query
     * @param array $filters
     * @return Builder
     */
    private function applyFilters(Builder $query, array $filters): Builder
    {
        // Facility filter
        if (isset($filters['facility_id'])) {
            $query->where('facility_id', $filters['facility_id']);
        }

        // Encounter type filter
        if (isset($filters['encounter_type'])) {
            $query->where('encounter_type', $filters['encounter_type']);
        }

        // Department filter
        if (isset($filters['department_id'])) {
            $query->where('department_id', $filters['department_id']);
        }

        // Billable filter
        if (isset($filters['is_billable'])) {
            $query->where('is_billable', (bool) $filters['is_billable']);
        }

        // Date range filters
        if (isset($filters['start_date'])) {
            $query->where('documented_at', '>=', $filters['start_date']);
        }
        if (isset($filters['end_date'])) {
            $query->where('documented_at', '<=', $filters['end_date']);
        }

        // Severity score filter
        if (isset($filters['min_severity'])) {
            $query->where('severity_score', '>=', $filters['min_severity']);
        }
        if (isset($filters['max_severity'])) {
            $query->where('severity_score', '<=', $filters['max_severity']);
        }

        return $query;
    }
}