<?php

namespace App\Services\Patient;

use App\Exceptions\PatientCreationException;
use App\Models\Patient;
use App\Repositories\Contracts\PatientRepositoryInterface;
use App\Services\Contracts\PatientServiceInterface;
use App\Support\PatientIdGenerator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class PatientService implements PatientServiceInterface
{
    /**
     * @var PatientRepositoryInterface
     */
    private $patientRepository;

    /**
     * Constructor.
     */
    public function __construct(PatientRepositoryInterface $patientRepository)
    {
        $this->patientRepository = $patientRepository;
    }

    /**
     * Get patient by UUID with authorization check.
     */
    public function getPatientByUuid(string $uuid): ?Patient
    {
        try {
            $patient = $this->patientRepository->findByUuid($uuid);
            
            if (!$patient) {
                return null;
            }

            // Additional business logic can be added here
            // Example: Check if user has permission to view this patient
            
            return $patient;
        } catch (\Exception $e) {
            Log::error('Failed to get patient by UUID', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Get patient by user ID.
     */
    public function getPatientByUserId(int $userId): ?Patient
    {
        try {
            return $this->patientRepository->findByUserId($userId);
        } catch (\Exception $e) {
            Log::error('Failed to get patient by user ID', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Get all patients with pagination.
     */
    public function getAllPatients(int $perPage = 15): LengthAwarePaginator
    {
        try {
            return $this->patientRepository->getAllPaginated($perPage);
        } catch (\Exception $e) {
            Log::error('Failed to get all patients', [
                'error' => $e->getMessage(),
            ]);
            return new LengthAwarePaginator([], 0, $perPage);
        }
    }

    /**
     * Create a new patient record.
     */
    public function createPatient(array $data): Patient
{
    DB::beginTransaction();

    try {
        // Validate incoming demographic/business data
        $validatedData = $this->validatePatientData($data);

        // Ensure user does not already have a patient record
        if (isset($validatedData['user_id'])) {
            if ($this->patientRepository->findByUserId($validatedData['user_id'])) {
                throw new PatientCreationException('This user already has a patient record', 409);
            }
        }

        // Generate Patient UUID (public, human-readable)
        do {
            $patientUuid = PatientIdGenerator::generatePatientUuid();
        } while ($this->patientRepository->findByUuid($patientUuid));
        $validatedData['patient_uuid'] = $patientUuid;

        // Generate MRN and hash
        do {
            $mrn = PatientIdGenerator::generateMedicalRecordNumber();
            $mrnHash = hash('sha256', $mrn);
        } while ($this->patientRepository->findByMrnHash($mrnHash));

        $validatedData['medical_record_number_encrypted'] = encrypt($mrn);
        $validatedData['medical_record_number_hash'] = $mrnHash;
       Log::info($mrnHash);
        // Apply safe defaults
        $validatedData = array_merge([
            'status' => 'active',
            'portal_access_enabled' => true,
            'default_consent_level' => 'full',
            'is_organ_donor' => false,
            'requires_isolation' => false,
            'acuity_baseline' => 1,
            'preferred_language' => 'en',
            'preferred_communication_method' => 'email',
        ], $validatedData);

        // Create patient
        $patient = $this->patientRepository->create($validatedData);

        DB::commit();

        Log::info('Patient created successfully', [
            'patient_uuid' => $patient->patient_uuid,
            'user_id' => $patient->user_id,
        ]);

        return $patient;

    } catch (PatientCreationException $e) {
        DB::rollBack();
        Log::warning('Patient creation business error', [
            'data' => $this->sanitizeForLogging($data),
            'error' => $e->getMessage(),
        ]);
        throw $e; // Controller will handle
    } catch (\Throwable $e) {
        DB::rollBack();
        Log::error('Failed to create patient', [
            'data' => $this->sanitizeForLogging($data),
            'error' => $e->getMessage(),
        ]);
        throw new PatientCreationException(
            config('app.debug') ? $e->getMessage() : 'Internal server error',
            500
        );
    }
}

    /**
     * Update an existing patient record.
     */
    public function updatePatient(Patient $patient, array $data): bool
    {
        if (!$this->canUpdatePatient($patient)) {
            throw new \Exception('Patient cannot be updated due to status restrictions');
        }

        DB::beginTransaction();
        try {
            $validatedData = $this->validatePatientData($data, $patient);
            
            $result = $this->patientRepository->update($patient, $validatedData);
            
            if ($result) {
                Log::info('Patient updated successfully', [
                    'patient_uuid' => $patient->patient_uuid,
                ]);
            }
            
            DB::commit();
            return $result;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update patient', [
                'patient_uuid' => $patient->patient_uuid,
                'data' => $this->sanitizeForLogging($data),
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Delete a patient record (soft delete).
     */
    public function deletePatient(Patient $patient): bool
    {
        if ($patient->isDeceased()) {
            throw new \Exception('Deceased patients cannot be deleted');
        }

        DB::beginTransaction();
        try {
            $result = $this->patientRepository->delete($patient);
            
            if ($result) {
                Log::info('Patient soft deleted', [
                    'patient_uuid' => $patient->patient_uuid,
                ]);
            }
            
            DB::commit();
            return $result;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to delete patient', [
                'patient_uuid' => $patient->patient_uuid,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Restore a soft-deleted patient.
     */
    public function restorePatient(Patient $patient): bool
    {
        DB::beginTransaction();
        try {
            $result = $this->patientRepository->restore($patient);
            
            if ($result) {
                Log::info('Patient restored', [
                    'patient_uuid' => $patient->patient_uuid,
                ]);
            }
            
            DB::commit();
            return $result;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to restore patient', [
                'patient_uuid' => $patient->patient_uuid,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Permanently delete a patient.
     */
    public function forceDeletePatient(Patient $patient): bool
    {
        // Only allow force delete for test patients or with special authorization
        if ($patient->status !== 'test_patient') {
            throw new \Exception('Only test patients can be permanently deleted');
        }

        DB::beginTransaction();
        try {
            $result = $this->patientRepository->forceDelete($patient);
            
            if ($result) {
                Log::warning('Patient permanently deleted', [
                    'patient_uuid' => $patient->patient_uuid,
                ]);
            }
            
            DB::commit();
            return $result;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to permanently delete patient', [
                'patient_uuid' => $patient->patient_uuid,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Search patients by criteria.
     */
    public function searchPatients(array $criteria): Collection
    {
        try {
            return $this->patientRepository->search($criteria);
        } catch (\Exception $e) {
            Log::error('Failed to search patients', [
                'criteria' => $criteria,
                'error' => $e->getMessage(),
            ]);
            return new Collection();
        }
    }

    /**
     * Update patient status with validation.
     */
    public function updatePatientStatus(Patient $patient, string $status): bool
    {
        $allowedStatuses = ['active', 'inactive', 'deceased', 'merged', 'test_patient'];
        
        if (!in_array($status, $allowedStatuses)) {
            throw new \Exception("Invalid status: {$status}");
        }

        // Business rule: Cannot change from deceased to another status
        if ($patient->isDeceased() && $status !== 'deceased') {
            throw new \Exception('Cannot change status of deceased patient');
        }

        DB::beginTransaction();
        try {
            $result = $this->patientRepository->updateStatus($patient, $status);
            
            if ($result) {
                Log::info('Patient status updated', [
                    'patient_uuid' => $patient->patient_uuid,
                    'old_status' => $patient->status,
                    'new_status' => $status,
                ]);
            }
            
            DB::commit();
            return $result;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update patient status', [
                'patient_uuid' => $patient->patient_uuid,
                'status' => $status,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Mark patient as deceased.
     */
    public function markAsDeceased(Patient $patient, \DateTimeInterface $deceasedAt): bool
    {
        if ($patient->isDeceased()) {
            throw new \Exception('Patient is already marked as deceased');
        }

        DB::beginTransaction();
        try {
            $patient->status = 'deceased';
            $patient->deceased_at = $deceasedAt;
            
            $result = $patient->save();
            
            if ($result) {
                Log::info('Patient marked as deceased', [
                    'patient_uuid' => $patient->patient_uuid,
                    'deceased_at' => $deceasedAt,
                ]);
            }
            
            DB::commit();
            return $result;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to mark patient as deceased', [
                'patient_uuid' => $patient->patient_uuid,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Merge patient records.
     */
    public function mergePatients(Patient $sourcePatient, Patient $targetPatient): bool
    {
        if ($sourcePatient->id === $targetPatient->id) {
            throw new \Exception('Cannot merge patient with themselves');
        }

        if ($sourcePatient->isDeceased() || $targetPatient->isDeceased()) {
            throw new \Exception('Cannot merge deceased patients');
        }

        DB::beginTransaction();
        try {
            $result = $this->patientRepository->merge($sourcePatient, $targetPatient);
            
            if ($result) {
                Log::info('Patients merged successfully', [
                    'source_patient' => $sourcePatient->patient_uuid,
                    'target_patient' => $targetPatient->patient_uuid,
                ]);
            }
            
            DB::commit();
            return $result;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to merge patients', [
                'source_patient' => $sourcePatient->patient_uuid,
                'target_patient' => $targetPatient->patient_uuid,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Get patients by blood type for emergency matching.
     */
    public function getPatientsByBloodType(string $bloodType): Collection
    {
        try {
            return $this->patientRepository->getByBloodType($bloodType);
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
            return $this->patientRepository->getPatientsRequiringIsolation();
        } catch (\Exception $e) {
            Log::error('Failed to get patients requiring isolation', [
                'error' => $e->getMessage(),
            ]);
            return new Collection();
        }
    }

    /**
     * Update patient consent level.
     */
    public function updateConsentLevel(Patient $patient, string $consentLevel): bool
    {
        $allowedLevels = ['full', 'restricted', 'minimal', 'none'];
        
        if (!in_array($consentLevel, $allowedLevels)) {
            throw new \Exception("Invalid consent level: {$consentLevel}");
        }

        DB::beginTransaction();
        try {
            $patient->default_consent_level = $consentLevel;
            $result = $patient->save();
            
            if ($result) {
                Log::info('Patient consent level updated', [
                    'patient_uuid' => $patient->patient_uuid,
                    'consent_level' => $consentLevel,
                ]);
            }
            
            DB::commit();
            return $result;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update patient consent level', [
                'patient_uuid' => $patient->patient_uuid,
                'consent_level' => $consentLevel,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Validate patient data before creation/update.
     */
    public function validatePatientData(array $data, ?Patient $patient = null): array
    {
        $rules = [
            'user_id' => 'required|integer|exists:users,id',
            'medical_record_number_hash' => 'nullable|string|max:128|unique:patients,medical_record_number_hash' 
                . ($patient ? ',' . $patient->id : ''),
            'medical_record_number_encrypted' => 'nullable|string|max:512',
            'previous_mrn_list_encrypted' => 'nullable|string|max:2048',
            'date_of_birth' => 'required|date|before:today',
            'biological_sex' => 'required|in:male,female,intersex,unknown',
            'gender_identity' => 'nullable|in:male,female,non_binary,prefer_not_to_say,other',
            'blood_type' => 'nullable|string|max:5',
            'ethnicity' => 'nullable|string|max:100',
            'genetic_markers' => 'nullable|array',
            'emergency_contact_chain_encrypted' => 'nullable|array',
            'known_allergies' => 'nullable|array',
            'chronic_conditions' => 'nullable|array',
            'active_medications' => 'nullable|array',
            'is_organ_donor' => 'boolean',
            'advance_directives' => 'nullable|array',
            'acuity_baseline' => 'integer|min:1|max:5',
            'risk_factors' => 'nullable|array',
            'requires_isolation' => 'boolean',
            'isolation_type' => 'nullable|string|max:50',
            'default_consent_level' => 'in:full,restricted,minimal,none',
            'privacy_flags' => 'array',
            'research_participation_allowed' => 'boolean',
            'data_sharing_allowed' => 'boolean',
            'primary_insurance_provider' => 'nullable|string|max:200',
            'primary_insurance_id_encrypted' => 'nullable|string|max:512',
            'secondary_insurance_provider' => 'nullable|string|max:200',
            'secondary_insurance_id_encrypted' => 'nullable|string|max:512',
            'payment_responsibility' => 'in:self_pay,insurance,government,charity',
            'primary_care_provider_staff_id' => 'nullable|integer|exists:staff,id',
            'primary_care_facility_id' => 'nullable|integer|exists:facilities,id',
            'portal_access_enabled' => 'boolean',
            'portal_terms_accepted_at' => 'nullable|date',
            'preferred_language' => 'string|max:10',
            'preferred_communication_method' => 'in:email,sms,phone,postal',
            'status' => 'in:active,inactive,deceased,merged,test_patient',
            'deceased_at' => 'nullable|date',
            'merged_into_patient_id' => 'nullable|integer|exists:patients,id',
            'created_by_staff_id' => 'nullable|integer|exists:staff,id',
            'updated_by_staff_id' => 'nullable|integer|exists:staff,id',
            'metadata' => 'nullable|array',
        ];

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    /**
     * Check if patient can be updated based on status.
     */
    public function canUpdatePatient(Patient $patient): bool
    {
        // Business rules for patient updates
        if ($patient->isDeceased()) {
            return false;
        }

        if ($patient->status === 'merged') {
            return false;
        }

        return true;
    }

    /**
     * Get patient statistics.
     */
    public function getPatientStatistics(): array
    {
        try {
            $total = Patient::count();
            $active = Patient::active()->count();
            $deceased = Patient::where('status', 'deceased')->count();
            $requiresIsolation = Patient::requiringIsolation()->count();
            
            $bloodTypes = Patient::select('blood_type', DB::raw('count(*) as count'))
                ->whereNotNull('blood_type')
                ->groupBy('blood_type')
                ->get()
                ->pluck('count', 'blood_type')
                ->toArray();

            $consentLevels = Patient::select('default_consent_level', DB::raw('count(*) as count'))
                ->groupBy('default_consent_level')
                ->get()
                ->pluck('count', 'default_consent_level')
                ->toArray();

            return [
                'total_patients' => $total,
                'active_patients' => $active,
                'deceased_patients' => $deceased,
                'patients_requiring_isolation' => $requiresIsolation,
                'blood_type_distribution' => $bloodTypes,
                'consent_level_distribution' => $consentLevels,
                'average_acuity' => Patient::avg('acuity_baseline'),
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get patient statistics', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Export patient data with consent verification.
     */
    public function exportPatientData(Patient $patient): array
    {
        if (!$patient->data_sharing_allowed) {
            throw new \Exception('Patient does not allow data sharing');
        }

        try {
            $data = $patient->toArray();
            
            // Remove sensitive fields based on consent level
            if ($patient->default_consent_level === 'restricted') {
                $sensitiveFields = [
                    'medical_record_number_encrypted',
                    'previous_mrn_list_encrypted',
                    'emergency_contact_chain_encrypted',
                    'primary_insurance_id_encrypted',
                    'secondary_insurance_id_encrypted',
                    'genetic_markers',
                    'chronic_conditions',
                    'active_medications',
                    'advance_directives',
                    'risk_factors',
                    'privacy_flags',
                    'metadata',
                ];
                
                foreach ($sensitiveFields as $field) {
                    unset($data[$field]);
                }
            } elseif ($patient->default_consent_level === 'minimal') {
                // Only basic demographic info
                $allowedFields = [
                    'patient_uuid',
                    'date_of_birth',
                    'biological_sex',
                    'gender_identity',
                    'blood_type',
                    'ethnicity',
                    'preferred_language',
                    'status',
                ];
                
                $data = array_intersect_key($data, array_flip($allowedFields));
            }

            return $data;
        } catch (\Exception $e) {
            Log::error('Failed to export patient data', [
                'patient_uuid' => $patient->patient_uuid,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Sanitize sensitive data for logging.
     */
    private function sanitizeForLogging(array $data): array
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