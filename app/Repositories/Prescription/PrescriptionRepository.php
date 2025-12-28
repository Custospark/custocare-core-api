<?php

namespace App\Repositories\Prescription;

use App\Models\Prescription;
use App\Repositories\Contracts\PrescriptionRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class PrescriptionRepository implements PrescriptionRepositoryInterface
{
    /**
     * Find prescription by UUID
     *
     * @param string $uuid
     * @return Prescription|null
     */
    public function findByUuid(string $uuid): ?Prescription
    {
        try {
            return Prescription::where('prescription_uuid', $uuid)->first();
        } catch (\Exception $e) {
            Log::error('Failed to find prescription by UUID', [
                'uuid' => $uuid,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Find prescription by UUID or throw exception
     *
     * @param string $uuid
     * @return Prescription
     * @throws ModelNotFoundException
     */
    public function findByUuidOrFail(string $uuid): Prescription
    {
        return Prescription::where('prescription_uuid', $uuid)->firstOrFail();
    }

    /**
     * Get all prescriptions with optional filters
     *
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getAll(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = Prescription::query();

        // Apply filters
        if (!empty($filters['facility_id'])) {
            $query->where('facility_id', $filters['facility_id']);
        }

        if (!empty($filters['patient_id'])) {
            $query->where('patient_id', $filters['patient_id']);
        }

        if (!empty($filters['provider_id'])) {
            $query->where('prescribing_provider_staff_id', $filters['provider_id']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['dispense_status'])) {
            $query->where('dispense_status', $filters['dispense_status']);
        }

        if (!empty($filters['is_high_risk'])) {
            $query->where('is_high_risk_medication', (bool)$filters['is_high_risk']);
        }

        if (!empty($filters['controlled_substance'])) {
            $query->whereNotNull('controlled_substance_schedule');
        }

        if (!empty($filters['date_from'])) {
            $query->whereDate('prescribed_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('prescribed_at', '<=', $filters['date_to']);
        }

        // Eager load relationships
        $query->with([
            'patient:id,patient_uuid,first_name,last_name,date_of_birth',
            'prescribingProvider:id,staff_uuid,first_name,last_name,professional_title',
            'inventoryItem:id,item_name,item_code'
        ]);

        // Order by most recent first
        $query->orderBy('prescribed_at', 'desc');

        return $query->paginate($perPage);
    }

    /**
     * Get prescriptions by patient ID
     *
     * @param int $patientId
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getByPatientId(int $patientId, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = Prescription::where('patient_id', $patientId);

        // Apply additional filters
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['active_only'])) {
            $query->active();
        }

        // Eager load relationships
        $query->with([
            'prescribingProvider:id,staff_uuid,first_name,last_name,professional_title',
            'inventoryItem:id,item_name,item_code',
            'visit:id,visit_uuid,visit_date'
        ]);

        $query->orderBy('prescribed_at', 'desc');

        return $query->paginate($perPage);
    }

    /**
     * Get prescriptions by provider ID
     *
     * @param int $providerId
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getByProviderId(int $providerId, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = Prescription::where('prescribing_provider_staff_id', $providerId);

        if (!empty($filters['facility_id'])) {
            $query->where('facility_id', $filters['facility_id']);
        }

        if (!empty($filters['date_range'])) {
            $query->whereBetween('prescribed_at', $filters['date_range']);
        }

        // Eager load minimal patient info for privacy
        $query->with([
            'patient:id,patient_uuid,first_name,last_name',
            'facility:id,facility_uuid,name'
        ]);

        $query->orderBy('prescribed_at', 'desc');

        return $query->paginate($perPage);
    }

    /**
     * Get prescriptions by facility ID
     *
     * @param int $facilityId
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getByFacilityId(int $facilityId, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = Prescription::where('facility_id', $facilityId);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['high_risk_only'])) {
            $query->highRisk();
        }

        $query->with([
            'patient:id,patient_uuid,first_name,last_name',
            'prescribingProvider:id,staff_uuid,first_name,last_name'
        ]);

        $query->orderBy('prescribed_at', 'desc');

        return $query->paginate($perPage);
    }

    /**
     * Get prescriptions needing transmission
     *
     * @param int $facilityId
     * @param int $limit
     * @return Collection
     */
    public function getPrescriptionsNeedingTransmission(int $facilityId, int $limit = 50): Collection
    {
        return Prescription::needsTransmission()
            ->where('facility_id', $facilityId)
            ->with(['patient', 'prescribingProvider', 'inventoryItem'])
            ->limit($limit)
            ->get();
    }

    /**
     * Create new prescription
     *
     * @param array $data
     * @return Prescription
     */
    public function create(array $data): Prescription
    {
        return DB::transaction(function () use ($data) {
            $prescription = Prescription::create($data);
            
            // Log prescription creation for audit trail
            if ($prescription->metadata) {
                $metadata = $prescription->metadata;
                $metadata['creation_log'] = [
                    'created_at' => now()->toIso8601String(),
                    'created_by' => $data['created_by_staff_id'] ?? null,
                    'source' => 'api'
                ];
                $prescription->update(['metadata' => $metadata]);
            }
            
            return $prescription->fresh();
        });
    }

    /**
     * Update prescription
     *
     * @param Prescription $prescription
     * @param array $data
     * @return Prescription
     */
    public function update(Prescription $prescription, array $data): Prescription
    {
        return DB::transaction(function () use ($prescription, $data) {
            $prescription->update($data);
            
            // Log update for audit trail
            if ($prescription->metadata) {
                $metadata = $prescription->metadata;
                $metadata['update_logs'][] = [
                    'updated_at' => now()->toIso8601String(),
                    'updated_fields' => array_keys($data),
                    'updated_by' => auth::id() ?? null
                ];
                $prescription->update(['metadata' => $metadata]);
            }
            
            return $prescription->fresh();
        });
    }

    /**
     * Delete prescription (soft delete)
     *
     * @param Prescription $prescription
     * @return bool
     */
    public function delete(Prescription $prescription): bool
    {
        try {
            // Don't allow deletion of prescriptions that have been transmitted or dispensed
            if (in_array($prescription->dispense_status, ['transmitted', 'received_by_pharmacy', 'dispensed'])) {
                return false;
            }
            
            return $prescription->delete();
        } catch (\Exception $e) {
            Log::error('Failed to delete prescription', [
                'prescription_id' => $prescription->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Restore soft-deleted prescription
     *
     * @param Prescription $prescription
     * @return bool
     */
    public function restore(Prescription $prescription): bool
    {
        try {
            return $prescription->restore();
        } catch (\Exception $e) {
            Log::error('Failed to restore prescription', [
                'prescription_id' => $prescription->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Process prescription refill
     *
     * @param Prescription $prescription
     * @param array $refillData
     * @return Prescription
     */
    public function processRefill(Prescription $prescription, array $refillData): Prescription
    {
        return DB::transaction(function () use ($prescription, $refillData) {
            // Validate refill is allowed
            if ($prescription->refills_remaining <= 0) {
                throw new \InvalidArgumentException('No refills remaining for this prescription');
            }
            
            // Decrement refills remaining
            $prescription->refills_remaining -= 1;
            
            // Update dispense status if needed
            if ($prescription->dispense_status === 'dispensed') {
                $prescription->dispense_status = 'pending';
            }
            
            // Add refill metadata
            if ($prescription->metadata) {
                $metadata = $prescription->metadata;
                $metadata['refills'][] = [
                    'refilled_at' => now()->toIso8601String(),
                    'refilled_by' => $refillData['refilled_by'] ?? null,
                    'refill_number' => $refillData['refill_number'] ?? null,
                    'pharmacy_ncpdp_id' => $refillData['pharmacy_ncpdp_id'] ?? null
                ];
                $prescription->metadata = $metadata;
            }
            
            $prescription->save();
            
            return $prescription->fresh();
        });
    }

    /**
     * Update dispense status
     *
     * @param Prescription $prescription
     * @param string $status
     * @param array $metadata
     * @return Prescription
     */
    public function updateDispenseStatus(Prescription $prescription, string $status, array $metadata = []): Prescription
    {
        $validStatuses = [
            'pending', 'transmitted', 'received_by_pharmacy', 'in_progress',
            'ready_for_pickup', 'dispensed', 'not_picked_up', 'cancelled', 'discontinued'
        ];
        
        if (!in_array($status, $validStatuses)) {
            throw new \InvalidArgumentException('Invalid dispense status');
        }
        
        $prescription->dispense_status = $status;
        
        // Update transmitted_at if status is transmitted
        if ($status === 'transmitted' && empty($prescription->transmitted_at)) {
            $prescription->transmitted_at = now();
        }
        
        // Add status change metadata
        if ($prescription->metadata) {
            $currentMetadata = $prescription->metadata;
            $currentMetadata['dispense_status_changes'][] = [
                'status' => $status,
                'changed_at' => now()->toIso8601String(),
                'changed_by' => auth::id() ?? null,
                'additional_info' => $metadata
            ];
            $prescription->metadata = $currentMetadata;
        }
        
        $prescription->save();
        
        return $prescription->fresh();
    }

    /**
     * Discontinue prescription
     *
     * @param Prescription $prescription
     * @param string $reason
     * @param int|null $discontinuedById
     * @return Prescription
     */
    public function discontinue(Prescription $prescription, string $reason, ?int $discontinuedById = null): Prescription
    {
        return DB::transaction(function () use ($prescription, $reason, $discontinuedById) {
            $prescription->status = 'discontinued';
            $prescription->status_reason = $reason;
            $prescription->discontinued_at = now();
            $prescription->discontinued_by_staff_id = $discontinuedById ?? auth::id();
            
            // Also update dispense status if needed
            if ($prescription->dispense_status !== 'cancelled' && $prescription->dispense_status !== 'discontinued') {
                $prescription->dispense_status = 'discontinued';
            }
            
            $prescription->save();
            
            return $prescription->fresh();
        });
    }

    /**
     * Get prescription statistics
     *
     * @param int $facilityId
     * @param array $dateRange
     * @return array
     */
    public function getStatistics(int $facilityId, array $dateRange = []): array
    {
        $query = Prescription::where('facility_id', $facilityId);
        
        if (!empty($dateRange)) {
            $query->whereBetween('prescribed_at', $dateRange);
        }
        
        return [
            'total_prescriptions' => $query->count(),
            'active_prescriptions' => $query->clone()->where('status', 'active')->count(),
            'electronic_prescriptions' => $query->clone()->where('is_electronic_prescription', true)->count(),
            'high_risk_prescriptions' => $query->clone()->where('is_high_risk_medication', true)->count(),
            'controlled_substances' => $query->clone()->whereNotNull('controlled_substance_schedule')->count(),
            'pending_transmission' => $query->clone()->needsTransmission()->count(),
            'by_status' => $query->clone()->groupBy('status')->selectRaw('status, count(*) as count')->pluck('count', 'status')->toArray(),
            'by_dispense_status' => $query->clone()->groupBy('dispense_status')->selectRaw('dispense_status, count(*) as count')->pluck('count', 'dispense_status')->toArray(),
        ];
    }
}