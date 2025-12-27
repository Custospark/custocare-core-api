<?php

namespace App\Services\ClinicalEncounter;

use App\Models\ClinicalEncounter;
use App\Repositories\Contracts\ClinicalEncounterRepositoryInterface;
use App\Services\Contracts\ClinicalEncounterServiceInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ClinicalEncounterService implements ClinicalEncounterServiceInterface
{
    /**
     * Repository instance
     *
     * @var ClinicalEncounterRepositoryInterface
     */
    protected ClinicalEncounterRepositoryInterface $repository;

    /**
     * Required fields for completed documentation
     *
     * @var array
     */
    private array $requiredCompletionFields = [
        'subjective_assessment',
        'objective_findings',
        'assessment_diagnosis_codes',
        'clinical_impression',
        'treatment_plan',
    ];

    /**
     * Constructor with dependency injection
     *
     * @param ClinicalEncounterRepositoryInterface $repository
     */
    public function __construct(ClinicalEncounterRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * {@inheritdoc}
     */
    public function getAllEncounters(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        try {
            return $this->repository->getAllPaginated($filters, $perPage);
        } catch (\Exception $e) {
            Log::error('Failed to get all encounters', [
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);
            throw new \RuntimeException('Unable to retrieve clinical encounters. Please try again later.');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getEncounterByUuid(string $uuid): ClinicalEncounter
    {
        try {
            return $this->repository->findByUuid($uuid);
        } catch (\Exception $e) {
            Log::warning('Clinical encounter not found by UUID', ['uuid' => $uuid]);
            throw new \RuntimeException('Clinical encounter not found.');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function createEncounter(array $data): ClinicalEncounter
    {
        try {
            // Set audit trail fields
            $data['created_by_staff_id'] = Auth::id();
            $data['updated_by_staff_id'] = Auth::id();

            // Validate required clinical data
            $this->validateClinicalData($data);

            return $this->repository->create($data);
        } catch (\Exception $e) {
            Log::error('Failed to create clinical encounter', [
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            
            if ($e instanceof \RuntimeException) {
                throw $e;
            }
            
            throw new \RuntimeException('Failed to create clinical encounter. Please check the data and try again.');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function updateEncounter(string $uuid, array $data): ClinicalEncounter
    {
        try {
            $encounter = $this->repository->findByUuid($uuid);
            
            // Update audit trail
            $data['updated_by_staff_id'] = Auth::id();
            
            // If encounter is being signed, validate completeness
            if (isset($data['documentation_status']) && $data['documentation_status'] === 'completed') {
                $this->validateEncounterCompleteness($encounter);
            }

            return $this->repository->update($encounter, $data);
        } catch (\Exception $e) {
            Log::error('Failed to update clinical encounter', [
                'uuid' => $uuid,
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            
            if ($e instanceof \RuntimeException) {
                throw $e;
            }
            
            throw new \RuntimeException('Failed to update clinical encounter. Please check the data and try again.');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function deleteEncounter(string $uuid): bool
    {
        try {
            $encounter = $this->repository->findByUuid($uuid);
            
            // Check if encounter can be deleted
            if ($encounter->signed_at) {
                throw new \RuntimeException('Signed encounters cannot be deleted. Please create an amendment instead.');
            }

            return $this->repository->delete($encounter);
        } catch (\Exception $e) {
            Log::error('Failed to delete clinical encounter', [
                'uuid' => $uuid,
                'error' => $e->getMessage()
            ]);
            
            if ($e instanceof \RuntimeException) {
                throw $e;
            }
            
            throw new \RuntimeException('Failed to delete clinical encounter. Please try again later.');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function restoreEncounter(string $uuid): ClinicalEncounter
    {
        try {
            $encounter = $this->repository->findByUuid($uuid);
            
            if (!$encounter->trashed()) {
                throw new \RuntimeException('Encounter is not deleted.');
            }

            $this->repository->restore($encounter);
            
            return $encounter->refresh();
        } catch (\Exception $e) {
            Log::error('Failed to restore clinical encounter', [
                'uuid' => $uuid,
                'error' => $e->getMessage()
            ]);
            
            if ($e instanceof \RuntimeException) {
                throw $e;
            }
            
            throw new \RuntimeException('Failed to restore clinical encounter. Please try again later.');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function signEncounter(string $uuid, string $signatureHash): ClinicalEncounter
    {
        try {
            DB::beginTransaction();
            
            $encounter = $this->repository->findByUuid($uuid);
            
            // Validate encounter can be signed
            if ($encounter->signed_at) {
                throw new \RuntimeException('Encounter is already signed.');
            }
            
            if ($encounter->documentation_status !== 'completed') {
                throw new \RuntimeException('Encounter must be completed before signing.');
            }
            
            // Validate clinical completeness
            $completeness = $this->validateEncounterCompleteness($encounter);
            if (!$completeness['is_complete']) {
                throw new \RuntimeException('Encounter documentation is incomplete: ' . implode(', ', $completeness['missing_fields']));
            }
            
            // Update encounter with signature
            $updateData = [
                'documentation_status' => 'signed',
                'signed_at' => now(),
                'electronic_signature_hash' => $signatureHash,
                'updated_by_staff_id' => Auth::id(),
            ];
            
            $encounter = $this->repository->update($encounter, $updateData);
            
            DB::commit();
            
            Log::info('Clinical encounter signed', [
                'encounter_id' => $encounter->id,
                'signed_by' => Auth::id(),
            ]);
            
            return $encounter;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to sign clinical encounter', [
                'uuid' => $uuid,
                'error' => $e->getMessage()
            ]);
            
            if ($e instanceof \RuntimeException) {
                throw $e;
            }
            
            throw new \RuntimeException('Failed to sign clinical encounter. Please try again later.');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function createAmendment(string $originalUuid, array $amendmentData, string $amendmentReason): ClinicalEncounter
    {
        try {
            DB::beginTransaction();
            
            $originalEncounter = $this->repository->findByUuid($originalUuid);
            
            // Validate amendment can be created
            if (!$originalEncounter->signed_at) {
                throw new \RuntimeException('Only signed encounters can be amended.');
            }
            
            if (empty($amendmentReason)) {
                throw new \RuntimeException('Amendment reason is required.');
            }
            
            // Prepare amendment data
            $amendmentData['amended_from_encounter_id'] = $originalEncounter->id;
            $amendmentData['amendment_reason'] = $amendmentReason;
            $amendmentData['amended_at'] = now();
            $amendmentData['documentation_status'] = 'amended';
            $amendmentData['created_by_staff_id'] = Auth::id();
            $amendmentData['updated_by_staff_id'] = Auth::id();
            
            // Create amendment encounter
            $amendment = $this->repository->create($amendmentData);
            
            // Update original encounter status
            $this->repository->update($originalEncounter, [
                'documentation_status' => 'amended'
            ]);
            
            DB::commit();
            
            Log::info('Clinical encounter amendment created', [
                'original_id' => $originalEncounter->id,
                'amendment_id' => $amendment->id,
                'amendment_reason' => $amendmentReason,
            ]);
            
            return $amendment;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create amendment', [
                'original_uuid' => $originalUuid,
                'error' => $e->getMessage()
            ]);
            
            if ($e instanceof \RuntimeException) {
                throw $e;
            }
            
            throw new \RuntimeException('Failed to create amendment. Please try again later.');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getEncountersRequiringAttention(int $facilityId): Collection
    {
        try {
            return $this->repository->getRequiringAttention($facilityId);
        } catch (\Exception $e) {
            Log::error('Failed to get encounters requiring attention', [
                'facility_id' => $facilityId,
                'error' => $e->getMessage()
            ]);
            throw new \RuntimeException('Unable to retrieve encounters requiring attention.');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getIncompleteDocumentation(int $facilityId, int $daysThreshold = 3): Collection
    {
        try {
            return $this->repository->getIncompleteDocumentation($facilityId, $daysThreshold);
        } catch (\Exception $e) {
            Log::error('Failed to get incomplete documentation', [
                'facility_id' => $facilityId,
                'error' => $e->getMessage()
            ]);
            throw new \RuntimeException('Unable to retrieve incomplete documentation.');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function validateEncounterCompleteness(ClinicalEncounter $encounter): array
    {
        $missingFields = [];
        
        foreach ($this->requiredCompletionFields as $field) {
            if (empty($encounter->$field)) {
                $missingFields[] = $field;
            }
        }
        
        // Validate diagnosis codes format
        if (!empty($encounter->assessment_diagnosis_codes)) {
            $diagnosisCodes = $encounter->assessment_diagnosis_codes;
            if (!is_array($diagnosisCodes) || empty($diagnosisCodes)) {
                $missingFields[] = 'assessment_diagnosis_codes';
            }
        }
        
        return [
            'is_complete' => empty($missingFields),
            'missing_fields' => $missingFields,
            'has_diagnosis_codes' => !empty($encounter->assessment_diagnosis_codes),
            'has_treatment_plan' => !empty($encounter->treatment_plan),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function generateBillingInformation(ClinicalEncounter $encounter): array
    {
        if (!$encounter->is_billable) {
            return [
                'billable' => false,
                'message' => 'This encounter is not billable.',
            ];
        }
        
        // Calculate billing based on encounter type, complexity, and duration
        $billingData = [
            'billable' => true,
            'billing_code' => $encounter->billing_code,
            'encounter_type' => $encounter->encounter_type,
            'severity_score' => $encounter->severity_score,
            'diagnosis_codes' => $encounter->assessment_diagnosis_codes,
            'treatment_codes' => $encounter->plan_treatment_codes,
        ];
        
        // Add estimated billing amount based on encounter type
        $billingAmounts = [
            'initial_consultation' => 250.00,
            'followup_consultation' => 150.00,
            'procedure' => 500.00,
            'telehealth_visit' => 100.00,
            // Add other encounter types
        ];
        
        $billingData['estimated_amount'] = $billingAmounts[$encounter->encounter_type] ?? 200.00;
        
        // Adjust based on severity
        if ($encounter->severity_score > 7) {
            $billingData['estimated_amount'] *= 1.3;
            $billingData['complexity_adjustment'] = 'High severity';
        }
        
        return $billingData;
    }

    /**
     * {@inheritdoc}
     */
    public function exportEncounter(ClinicalEncounter $encounter, string $format = 'pdf')
    {
        // This would integrate with a PDF generation library
        // For now, return structured data for export
        
        return [
            'encounter_summary' => [
                'uuid' => $encounter->encounter_uuid,
                'patient' => $encounter->patient->full_name ?? 'Unknown',
                'provider' => $encounter->primaryProvider->full_name ?? 'Unknown',
                'date' => $encounter->documented_at->format('Y-m-d H:i:s'),
                'type' => $encounter->encounter_type,
            ],
            'clinical_data' => [
                'subjective' => $encounter->subjective_assessment,
                'objective' => $encounter->objective_findings,
                'assessment' => $encounter->clinical_impression,
                'plan' => $encounter->treatment_plan,
            ],
            'diagnosis_codes' => $encounter->assessment_diagnosis_codes,
            'vital_signs' => $encounter->vital_signs,
            'medications' => $encounter->medications_prescribed,
        ];
    }

    /**
     * Validate clinical data for encounter creation/update
     *
     * @param array $data
     * @throws \RuntimeException
     */
    private function validateClinicalData(array $data): void
    {
        $requiredFields = [
            'visit_id',
            'patient_id',
            'encounter_type',
            'primary_provider_staff_id',
            'department_id',
            'assessment_diagnosis_codes',
        ];
        
        $missingFields = [];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                $missingFields[] = $field;
            }
        }
        
        if (!empty($missingFields)) {
            throw new \RuntimeException('Missing required fields: ' . implode(', ', $missingFields));
        }
        
        // Validate diagnosis codes format
        if (isset($data['assessment_diagnosis_codes'])) {
            $diagnosisCodes = json_decode($data['assessment_diagnosis_codes'], true);
            if (!is_array($diagnosisCodes) || empty($diagnosisCodes)) {
                throw new \RuntimeException('Diagnosis codes must be a non-empty array.');
            }
        }
        
        // Validate encounter type
        $validTypes = [
            'initial_consultation',
            'followup_consultation',
            'procedure',
            'diagnostic_review',
            'medication_review',
            'telehealth_visit',
            'specialist_consultation',
            'pre_operative_assessment',
            'post_operative_followup',
            'discharge_summary'
        ];
        
        if (isset($data['encounter_type']) && !in_array($data['encounter_type'], $validTypes)) {
            throw new \RuntimeException('Invalid encounter type.');
        }
    }
}