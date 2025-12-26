<?php

namespace App\Repositories\Patient;

use App\Models\Patient;
use App\Repositories\Contracts\PatientRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PatientRepository implements PatientRepositoryInterface
{
    /**
     * Find a patient by UUID.
     */
    public function findByUuid(string $uuid): ?Patient
    {
        try {
            return Patient::where('patient_uuid', $uuid)->first();
        } catch (\Exception $e) {
            Log::error('Failed to find patient by UUID', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Find a patient by user ID.
     */
    public function findByUserId(int $userId): ?Patient
    {
        try {
            return Patient::where('user_id', $userId)->first();
        } catch (\Exception $e) {
            Log::error('Failed to find patient by user ID', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Find a patient by medical record number hash.
     */
    public function findByMrnHash(string $mrnHash): ?Patient
    {
        try {
            return Patient::where('medical_record_number_hash', $mrnHash)->first();
        } catch (\Exception $e) {
            Log::error('Failed to find patient by MRN hash', [
                'mrn_hash' => $mrnHash,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Get all patients with pagination.
     */
    public function getAllPaginated(int $perPage = 15): LengthAwarePaginator
    {
        try {
            return Patient::with(['user', 'primaryCareProvider', 'primaryCareFacility'])
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);
        } catch (\Exception $e) {
            Log::error('Failed to get paginated patients', [
                'per_page' => $perPage,
                'error' => $e->getMessage(),
            ]);
            return new LengthAwarePaginator([], 0, $perPage);
        }
    }

    /**
     * Get active patients.
     */
    public function getActivePatients(int $perPage = 15): LengthAwarePaginator
    {
        try {
            return Patient::active()
                ->with(['user', 'primaryCareProvider'])
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);
        } catch (\Exception $e) {
            Log::error('Failed to get active patients', [
                'error' => $e->getMessage(),
            ]);
            return new LengthAwarePaginator([], 0, $perPage);
        }
    }

    /**
     * Search patients by criteria.
     */
    public function search(array $criteria): Collection
    {
        try {
            $query = Patient::query();

            if (isset($criteria['status'])) {
                $query->where('status', $criteria['status']);
            }

            if (isset($criteria['biological_sex'])) {
                $query->where('biological_sex', $criteria['biological_sex']);
            }

            if (isset($criteria['blood_type'])) {
                $query->where('blood_type', $criteria['blood_type']);
            }

            if (isset($criteria['requires_isolation'])) {
                $query->where('requires_isolation', $criteria['requires_isolation']);
            }

            if (isset($criteria['date_of_birth_from'])) {
                $query->where('date_of_birth', '>=', $criteria['date_of_birth_from']);
            }

            if (isset($criteria['date_of_birth_to'])) {
                $query->where('date_of_birth', '<=', $criteria['date_of_birth_to']);
            }

            if (isset($criteria['facility_id'])) {
                $query->where('primary_care_facility_id', $criteria['facility_id']);
            }

            return $query->with(['user', 'primaryCareProvider'])
                ->orderBy('created_at', 'desc')
                ->get();
        } catch (\Exception $e) {
            Log::error('Failed to search patients', [
                'criteria' => $criteria,
                'error' => $e->getMessage(),
            ]);
            return new Collection();
        }
    }

    /**
     * Create a new patient.
     */
    public function create(array $data): Patient
    {
        DB::beginTransaction();
        try {
            $patient = Patient::create($data);
            DB::commit();
            return $patient;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create patient', [
                'data' => $this->sanitizeDataForLogging($data),
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Update an existing patient.
     */
    public function update(Patient $patient, array $data): bool
    {
        DB::beginTransaction();
        try {
            $result = $patient->update($data);
            DB::commit();
            return $result;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update patient', [
                'patient_uuid' => $patient->patient_uuid,
                'data' => $this->sanitizeDataForLogging($data),
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Soft delete a patient.
     */
    public function delete(Patient $patient): bool
    {
        DB::beginTransaction();
        try {
            $result = $patient->delete();
            DB::commit();
            return $result;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to soft delete patient', [
                'patient_uuid' => $patient->patient_uuid,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Restore a soft-deleted patient.
     */
    public function restore(Patient $patient): bool
    {
        DB::beginTransaction();
        try {
            $result = $patient->restore();
            DB::commit();
            return $result;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to restore patient', [
                'patient_uuid' => $patient->patient_uuid,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Permanently delete a patient.
     */
    public function forceDelete(Patient $patient): bool
    {
        DB::beginTransaction();
        try {
            $result = $patient->forceDelete();
            DB::commit();
            return $result;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to permanently delete patient', [
                'patient_uuid' => $patient->patient_uuid,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Get patients by blood type.
     */
    public function getByBloodType(string $bloodType): Collection
    {
        try {
            return Patient::withBloodType($bloodType)
                ->with(['user'])
                ->get();
        } catch (\Exception $e) {
            Log::error('Failed to get patients by blood type', [
                'blood_type' => $bloodType,
                'error' => $e->getMessage(),
            ]);
            return new Collection();
        }
    }

    /**
     * Get patients requiring isolation.
     */
    public function getPatientsRequiringIsolation(): Collection
    {
        try {
            return Patient::requiringIsolation()
                ->with(['user', 'primaryCareProvider'])
                ->get();
        } catch (\Exception $e) {
            Log::error('Failed to get patients requiring isolation', [
                'error' => $e->getMessage(),
            ]);
            return new Collection();
        }
    }

    /**
     * Get patients with specific consent level.
     */
    public function getByConsentLevel(string $consentLevel): Collection
    {
        try {
            return Patient::where('default_consent_level', $consentLevel)
                ->with(['user'])
                ->get();
        } catch (\Exception $e) {
            Log::error('Failed to get patients by consent level', [
                'consent_level' => $consentLevel,
                'error' => $e->getMessage(),
            ]);
            return new Collection();
        }
    }

    /**
     * Update patient status.
     */
    public function updateStatus(Patient $patient, string $status): bool
    {
        DB::beginTransaction();
        try {
            $patient->status = $status;
            
            if ($status === 'deceased') {
                $patient->deceased_at = now();
            }
            
            $result = $patient->save();
            DB::commit();
            return $result;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update patient status', [
                'patient_uuid' => $patient->patient_uuid,
                'status' => $status,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Get patients by primary care provider.
     */
    public function getByPrimaryCareProvider(int $staffId): Collection
    {
        try {
            return Patient::where('primary_care_provider_staff_id', $staffId)
                ->active()
                ->with(['user'])
                ->get();
        } catch (\Exception $e) {
            Log::error('Failed to get patients by primary care provider', [
                'staff_id' => $staffId,
                'error' => $e->getMessage(),
            ]);
            return new Collection();
        }
    }

    /**
     * Get patients by facility.
     */
    public function getByFacility(int $facilityId): Collection
    {
        try {
            return Patient::where('primary_care_facility_id', $facilityId)
                ->active()
                ->with(['user', 'primaryCareProvider'])
                ->get();
        } catch (\Exception $e) {
            Log::error('Failed to get patients by facility', [
                'facility_id' => $facilityId,
                'error' => $e->getMessage(),
            ]);
            return new Collection();
        }
    }

    /**
     * Merge patient records.
     */
    public function merge(Patient $sourcePatient, Patient $targetPatient): bool
    {
        DB::beginTransaction();
        try {
            // Update source patient to merged status
            $sourcePatient->status = 'merged';
            $sourcePatient->merged_into_patient_id = $targetPatient->id;
            
            // Add source MRN to target's previous MRN list
            $previousMrns = json_decode($targetPatient->previous_mrn_list_encrypted ?? '[]', true);
            $previousMrns[] = $sourcePatient->medical_record_number_encrypted;
            $targetPatient->previous_mrn_list_encrypted = json_encode($previousMrns);
            
            $sourcePatient->save();
            $targetPatient->save();
            
            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to merge patient records', [
                'source_patient' => $sourcePatient->patient_uuid,
                'target_patient' => $targetPatient->patient_uuid,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Get deceased patients.
     */
    public function getDeceasedPatients(): Collection
    {
        try {
            return Patient::where('status', 'deceased')
                ->with(['user'])
                ->orderBy('deceased_at', 'desc')
                ->get();
        } catch (\Exception $e) {
            Log::error('Failed to get deceased patients', [
                'error' => $e->getMessage(),
            ]);
            return new Collection();
        }
    }

    /**
     * Sanitize sensitive data for logging.
     */
    private function sanitizeDataForLogging(array $data): array
    {
        $sensitiveFields = [
            'medical_record_number_encrypted',
            'previous_mrn_list_encrypted',
            'emergency_contact_chain_encrypted',
            'primary_insurance_id_encrypted',
            'secondary_insurance_id_encrypted',
            'genetic_markers',
            'known_allergies',
            'chronic_conditions',
            'active_medications',
            'advance_directives',
            'risk_factors',
            'privacy_flags',
        ];

        foreach ($sensitiveFields as $field) {
            if (isset($data[$field])) {
                $data[$field] = '[REDACTED]';
            }
        }

        return $data;
    }
}