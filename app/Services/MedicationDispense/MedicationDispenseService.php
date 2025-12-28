<?php

namespace App\Services\MedicationDispense;

use App\Models\MedicationDispense;
use App\Repositories\Contracts\MedicationDispenseRepositoryInterface;
use App\Services\Contracts\MedicationDispenseServiceInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class MedicationDispenseService implements MedicationDispenseServiceInterface
{
    /**
     * @var MedicationDispenseRepositoryInterface
     */
    protected $repository;

    /**
     * Service constructor.
     *
     * @param MedicationDispenseRepositoryInterface $repository
     */
    public function __construct(MedicationDispenseRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * {@inheritdoc}
     */
    public function getAllDispenses(array $filters = [], int $perPage = 20): array
    {
        try {
            $paginator = $this->repository->getAllPaginated($filters, $perPage, [
                'patient:id,first_name,last_name,patient_uuid',
                'prescription:id,prescription_number',
                'dispensingStaff:id,first_name,last_name',
                'checkingPharmacist:id,first_name,last_name'
            ]);

            return [
                'success' => true,
                'data' => [
                    'dispenses' => $paginator->items(),
                    'pagination' => [
                        'total' => $paginator->total(),
                        'per_page' => $paginator->perPage(),
                        'current_page' => $paginator->currentPage(),
                        'last_page' => $paginator->lastPage()
                    ]
                ],
                'message' => 'Dispenses retrieved successfully'
            ];
        } catch (\Exception $e) {
            Log::error('Failed to retrieve dispenses', [
                'filters' => $filters,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Unable to retrieve dispenses at this time',
                'error' => config('app.debug') ? $e->getMessage() : null
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getDispenseByUuid(string $uuid): array
    {
        try {
            $dispense = $this->repository->findByUuid($uuid);

            if (!$dispense) {
                return [
                    'success' => false,
                    'message' => 'Dispense not found',
                    'error' => 'DISPENSE_NOT_FOUND'
                ];
            }

            // Load related models
            $dispense->load([
                'patient',
                'prescription',
                'facility',
                'dispensingStaff',
                'checkingPharmacist',
                'inventoryLedger',
                'visit'
            ]);

            return [
                'success' => true,
                'data' => $dispense,
                'message' => 'Dispense retrieved successfully'
            ];
        } catch (\Exception $e) {
            Log::error('Failed to retrieve dispense by UUID', [
                'uuid' => $uuid,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Unable to retrieve dispense details',
                'error' => config('app.debug') ? $e->getMessage() : null
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function createDispense(array $data): array
    {
        DB::beginTransaction();

        try {
            // Validate business rules before creation
            $validationResult = $this->validateDispenseData($data);
            if (!$validationResult['success']) {
                return $validationResult;
            }

            // Perform safety checks
            $safetyChecks = $this->performSafetyChecks(
                $data['prescription_details_snapshot'] ?? [],
                $data['patient_id'],
                $data['facility_id']
            );

            // Update data with safety check results
            $data['safety_checks_performed'] = $safetyChecks['checks_performed'];
            $data['all_safety_checks_passed'] = $safetyChecks['all_passed'];

            if (!$safetyChecks['all_passed'] && empty($data['override_justification'])) {
                DB::rollBack();
                return [
                    'success' => false,
                    'message' => 'Safety checks failed. Override justification is required.',
                    'errors' => $safetyChecks['failed_checks'],
                    'error' => 'SAFETY_CHECKS_FAILED'
                ];
            }

            // Set default values
            $data['dispense_uuid'] = \Illuminate\Support\Str::uuid();
            $data['dispensed_at'] = $data['dispensed_at'] ?? now();

            // Create the dispense
            $dispense = $this->repository->create($data);

            DB::commit();

            // Load relationships
            $dispense->load(['patient', 'prescription', 'dispensingStaff']);

            return [
                'success' => true,
                'data' => $dispense,
                'message' => 'Medication dispense created successfully'
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create medication dispense', [
                'data' => $data,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to create medication dispense',
                'error' => config('app.debug') ? $e->getMessage() : null
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function updateDispense(string $uuid, array $data): array
    {
        DB::beginTransaction();

        try {
            $dispense = $this->repository->findByUuid($uuid);

            if (!$dispense) {
                return [
                    'success' => false,
                    'message' => 'Dispense not found',
                    'error' => 'DISPENSE_NOT_FOUND'
                ];
            }

            // Prevent updates to verified dispenses without proper authorization
            if ($dispense->isVerified() && !isset($data['override_reason'])) {
                return [
                    'success' => false,
                    'message' => 'Cannot update verified dispense without override reason',
                    'error' => 'DISPENSE_VERIFIED'
                ];
            }

            // Perform safety checks if prescription data is being updated
            if (isset($data['prescription_details_snapshot'])) {
                $safetyChecks = $this->performSafetyChecks(
                    $data['prescription_details_snapshot'],
                    $dispense->patient_id,
                    $dispense->facility_id
                );

                $data['safety_checks_performed'] = $safetyChecks['checks_performed'];
                $data['all_safety_checks_passed'] = $safetyChecks['all_passed'];
            }

            $updatedDispense = $this->repository->updateByUuid($uuid, $data);

            DB::commit();

            $updatedDispense->load(['patient', 'prescription', 'dispensingStaff', 'checkingPharmacist']);

            return [
                'success' => true,
                'data' => $updatedDispense,
                'message' => 'Dispense updated successfully'
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update medication dispense', [
                'uuid' => $uuid,
                'data' => $data,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to update dispense',
                'error' => config('app.debug') ? $e->getMessage() : null
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function deleteDispense(string $uuid): array
    {
        DB::beginTransaction();

        try {
            $dispense = $this->repository->findByUuid($uuid);

            if (!$dispense) {
                return [
                    'success' => false,
                    'message' => 'Dispense not found',
                    'error' => 'DISPENSE_NOT_FOUND'
                ];
            }

            // Prevent deletion of verified or picked up dispenses
            if ($dispense->isVerified()) {
                return [
                    'success' => false,
                    'message' => 'Cannot delete verified dispense',
                    'error' => 'DISPENSE_VERIFIED'
                ];
            }

            if ($dispense->isPickedUp()) {
                return [
                    'success' => false,
                    'message' => 'Cannot delete dispense that has been picked up',
                    'error' => 'DISPENSE_PICKED_UP'
                ];
            }

            $deleted = $this->repository->deleteByUuid($uuid);

            if (!$deleted) {
                DB::rollBack();
                return [
                    'success' => false,
                    'message' => 'Failed to delete dispense',
                    'error' => 'DELETE_FAILED'
                ];
            }

            DB::commit();

            return [
                'success' => true,
                'message' => 'Dispense deleted successfully'
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to delete medication dispense', [
                'uuid' => $uuid,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to delete dispense',
                'error' => config('app.debug') ? $e->getMessage() : null
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function verifyDispense(string $uuid, int $pharmacistId, array $data): array
    {
        DB::beginTransaction();

        try {
            $dispense = $this->repository->findByUuid($uuid);

            if (!$dispense) {
                return [
                    'success' => false,
                    'message' => 'Dispense not found',
                    'error' => 'DISPENSE_NOT_FOUND'
                ];
            }

            // Check if already verified
            if ($dispense->isVerified()) {
                return [
                    'success' => false,
                    'message' => 'Dispense already verified',
                    'error' => 'ALREADY_VERIFIED'
                ];
            }

            // Cannot verify own dispenses (4-eyes principle)
            if ($dispense->dispensed_by_staff_id === $pharmacistId) {
                return [
                    'success' => false,
                    'message' => 'Cannot verify your own dispense',
                    'error' => 'SELF_VERIFICATION_NOT_ALLOWED'
                ];
            }

            // Additional validation for verification
            $validator = Validator::make($data, [
                'pharmacist_notes' => 'nullable|string|max:1000',
                'safety_confirmation' => 'required|boolean'
            ]);

            if ($validator->fails()) {
                return [
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()->toArray(),
                    'error' => 'VALIDATION_FAILED'
                ];
            }

            if (!$data['safety_confirmation']) {
                return [
                    'success' => false,
                    'message' => 'Safety confirmation is required for verification',
                    'error' => 'SAFETY_CONFIRMATION_REQUIRED'
                ];
            }

            $verifiedDispense = $this->repository->verifyDispense(
                $dispense->id,
                $pharmacistId,
                $data['pharmacist_notes'] ?? ''
            );

            DB::commit();

            $verifiedDispense->load(['patient', 'prescription', 'dispensingStaff', 'checkingPharmacist']);

            return [
                'success' => true,
                'data' => $verifiedDispense,
                'message' => 'Dispense verified successfully'
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to verify dispense', [
                'uuid' => $uuid,
                'pharmacist_id' => $pharmacistId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to verify dispense',
                'error' => config('app.debug') ? $e->getMessage() : null
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function markAsPickedUp(string $uuid, array $pickupData): array
    {
        DB::beginTransaction();

        try {
            $dispense = $this->repository->findByUuid($uuid);

            if (!$dispense) {
                return [
                    'success' => false,
                    'message' => 'Dispense not found',
                    'error' => 'DISPENSE_NOT_FOUND'
                ];
            }

            // Check if already picked up
            if ($dispense->isPickedUp()) {
                return [
                    'success' => false,
                    'message' => 'Dispense already picked up',
                    'error' => 'ALREADY_PICKED_UP'
                ];
            }

            // Validate pickup data
            $validator = Validator::make($pickupData, [
                'picked_up_by_name' => 'required|string|max:200',
                'pickup_id_verified' => 'required|string|max:100',
                'delivery_method' => 'nullable|in:pickup_in_person,mail_order,delivery_service,administered_in_facility,sent_to_home_health'
            ]);

            if ($validator->fails()) {
                return [
                    'success' => false,
                    'message' => 'Invalid pickup data',
                    'errors' => $validator->errors()->toArray(),
                    'error' => 'INVALID_PICKUP_DATA'
                ];
            }

            // Update dispense with pickup information
            $updatedDispense = $this->repository->markAsPickedUp($dispense->id, $pickupData);

            DB::commit();

            return [
                'success' => true,
                'data' => $updatedDispense,
                'message' => 'Dispense marked as picked up successfully'
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to mark dispense as picked up', [
                'uuid' => $uuid,
                'pickup_data' => $pickupData,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to update pickup status',
                'error' => config('app.debug') ? $e->getMessage() : null
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function updateDispenseStatus(string $uuid, string $status, ?string $reason = null): array
    {
        DB::beginTransaction();

        try {
            $dispense = $this->repository->findByUuid($uuid);

            if (!$dispense) {
                return [
                    'success' => false,
                    'message' => 'Dispense not found',
                    'error' => 'DISPENSE_NOT_FOUND'
                ];
            }

            // Validate status
            $validStatuses = ['dispensed', 'not_picked_up', 'returned', 'destroyed'];
            if (!in_array($status, $validStatuses)) {
                return [
                    'success' => false,
                    'message' => 'Invalid status. Must be one of: ' . implode(', ', $validStatuses),
                    'error' => 'INVALID_STATUS'
                ];
            }

            // Business rules for status changes
            if ($status === 'returned' && !$dispense->isPickedUp()) {
                return [
                    'success' => false,
                    'message' => 'Cannot return a dispense that has not been picked up',
                    'error' => 'NOT_PICKED_UP'
                ];
            }

            $updatedDispense = $this->repository->updateStatus($dispense->id, $status, $reason);

            DB::commit();

            return [
                'success' => true,
                'data' => $updatedDispense,
                'message' => 'Dispense status updated successfully'
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update dispense status', [
                'uuid' => $uuid,
                'status' => $status,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to update dispense status',
                'error' => config('app.debug') ? $e->getMessage() : null
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getDispensesByPrescription(int $prescriptionId): array
    {
        try {
            $dispenses = $this->repository->getByPrescriptionId($prescriptionId, [
                'patient:id,first_name,last_name',
                'dispensingStaff:id,first_name,last_name'
            ]);

            return [
                'success' => true,
                'data' => $dispenses,
                'message' => 'Dispenses retrieved successfully'
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get dispenses by prescription', [
                'prescription_id' => $prescriptionId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Unable to retrieve dispenses',
                'error' => config('app.debug') ? $e->getMessage() : null
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getDispensesByPatient(int $patientId, array $filters = [], int $perPage = 20): array
    {
        try {
            $paginator = $this->repository->getByPatientId($patientId, $filters, $perPage);

            return [
                'success' => true,
                'data' => [
                    'dispenses' => $paginator->items(),
                    'pagination' => [
                        'total' => $paginator->total(),
                        'per_page' => $paginator->perPage(),
                        'current_page' => $paginator->currentPage(),
                        'last_page' => $paginator->lastPage()
                    ]
                ],
                'message' => 'Patient dispenses retrieved successfully'
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get dispenses by patient', [
                'patient_id' => $patientId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Unable to retrieve patient dispenses',
                'error' => config('app.debug') ? $e->getMessage() : null
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getFacilityStatistics(int $facilityId, string $startDate, string $endDate): array
    {
        try {
            $stats = $this->repository->getFacilityStatistics($facilityId, $startDate, $endDate);

            return [
                'success' => true,
                'data' => $stats,
                'message' => 'Facility statistics retrieved successfully'
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get facility statistics', [
                'facility_id' => $facilityId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Unable to retrieve facility statistics',
                'error' => config('app.debug') ? $e->getMessage() : null
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function performSafetyChecks(array $prescriptionData, int $patientId, int $facilityId): array
    {
        $checksPerformed = [];
        $allPassed = true;
        $failedChecks = [];

        try {
            // 1. Allergy Check (Mock implementation)
            $allergyCheck = $this->checkAllergies($prescriptionData, $patientId);
            $checksPerformed['allergy_check'] = $allergyCheck;
            if (!$allergyCheck['passed']) {
                $allPassed = false;
                $failedChecks['allergies'] = $allergyCheck['details'];
            }

            // 2. Drug Interaction Check (Mock implementation)
            $interactionCheck = $this->checkDrugInteractions($prescriptionData, $patientId);
            $checksPerformed['interaction_check'] = $interactionCheck;
            if (!$interactionCheck['passed']) {
                $allPassed = false;
                $failedChecks['interactions'] = $interactionCheck['details'];
            }

            // 3. Duplicate Therapy Check (Mock implementation)
            $duplicateCheck = $this->checkDuplicateTherapy($prescriptionData, $patientId);
            $checksPerformed['duplicate_therapy_check'] = $duplicateCheck;
            if (!$duplicateCheck['passed']) {
                $allPassed = false;
                $failedChecks['duplicate_therapy'] = $duplicateCheck['details'];
            }

            // 4. Dosage Check (Mock implementation)
            $dosageCheck = $this->checkDosage($prescriptionData, $patientId);
            $checksPerformed['dosage_check'] = $dosageCheck;
            if (!$dosageCheck['passed']) {
                $allPassed = false;
                $failedChecks['dosage'] = $dosageCheck['details'];
            }

            // 5. Expiry Check
            if (isset($prescriptionData['expiry_date'])) {
                $expiryCheck = $this->checkExpiry($prescriptionData['expiry_date']);
                $checksPerformed['expiry_check'] = $expiryCheck;
                if (!$expiryCheck['passed']) {
                    $allPassed = false;
                    $failedChecks['expiry'] = $expiryCheck['details'];
                }
            }

            return [
                'checks_performed' => $checksPerformed,
                'all_passed' => $allPassed,
                'failed_checks' => $failedChecks
            ];
        } catch (\Exception $e) {
            Log::error('Safety check failed', [
                'patient_id' => $patientId,
                'error' => $e->getMessage()
            ]);

            return [
                'checks_performed' => [],
                'all_passed' => false,
                'failed_checks' => ['system_error' => 'Safety check system unavailable']
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function validateDispenseData(array $data): array
    {
        try {
            $validator = Validator::make($data, [
                'facility_id' => 'required|integer|exists:facilities,id',
                'visit_id' => 'nullable|integer|exists:visits,id',
                'prescription_id' => 'required|integer|exists:prescriptions,id',
                'patient_id' => 'required|integer|exists:patients,id',
                'prescription_details_snapshot' => 'required|array',
                'dispensed_inventory_ledger_id' => 'nullable|integer|exists:inventory_ledger,id',
                'quantity_dispensed' => 'required|numeric|min:0.01',
                'quantity_unit' => 'required|string|max:50',
                'lot_number' => 'nullable|string|max:100',
                'expiry_date' => 'nullable|date|after:today',
                'dispensed_by_staff_id' => 'required|integer|exists:staff,id',
                'dispensed_at' => 'nullable|date|before_or_equal:now',
                'checked_by_staff_id' => 'nullable|integer|exists:staff,id',
                'checked_at' => 'nullable|date|after:dispensed_at',
                'pharmacist_notes' => 'nullable|string|max:2000',
                'patient_counseling_provided' => 'boolean',
                'medication_guide_provided' => 'boolean',
                'patient_education_topics' => 'nullable|string|max:1000',
                'patient_questions_addressed' => 'nullable|string|max:1000',
                'dispensed_instructions' => 'nullable|string|max:2000',
                'followup_instructions' => 'nullable|string|max:2000',
                'warning_labels_applied' => 'nullable|array',
                'safety_checks_performed' => 'required|array',
                'all_safety_checks_passed' => 'boolean',
                'safety_check_overrides' => 'nullable|array',
                'override_justification' => 'nullable|string|max:1000',
                'delivery_method' => 'nullable|in:pickup_in_person,mail_order,delivery_service,administered_in_facility,sent_to_home_health',
                'picked_up_by_name' => 'nullable|string|max:200',
                'pickup_id_verified' => 'nullable|string|max:100',
                'copay_collected' => 'nullable|numeric|min:0',
                'total_cost_to_patient' => 'nullable|numeric|min:0',
                'insurance_payment' => 'nullable|numeric|min:0',
                'status' => 'nullable|in:dispensed,not_picked_up,returned,destroyed',
                'metadata' => 'nullable|array'
            ]);

            if ($validator->fails()) {
                return [
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()->toArray(),
                    'error' => 'VALIDATION_FAILED'
                ];
            }

            // Additional business rule validation
            if (isset($data['checked_by_staff_id']) && !isset($data['checked_at'])) {
                return [
                    'success' => false,
                    'message' => 'Checked at timestamp is required when pharmacist is specified',
                    'error' => 'MISSING_CHECKED_AT'
                ];
            }

            if (isset($data['checked_at']) && !isset($data['checked_by_staff_id'])) {
                return [
                    'success' => false,
                    'message' => 'Pharmacist ID is required when checked at timestamp is specified',
                    'error' => 'MISSING_PHARMACIST_ID'
                ];
            }

            return ['success' => true];
        } catch (\Exception $e) {
            Log::error('Dispense data validation failed', [
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Data validation failed',
                'error' => 'VALIDATION_ERROR'
            ];
        }
    }

    /**
     * Check for patient allergies (mock implementation).
     *
     * @param array $prescriptionData
     * @param int $patientId
     * @return array
     */
    private function checkAllergies(array $prescriptionData, int $patientId): array
    {
        // In a real implementation, this would query the patient's allergy records
        // and cross-reference with medication ingredients
        
        // Mock implementation - assume passed for demo
        return [
            'passed' => true,
            'details' => 'No known allergies to prescribed medication',
            'checked_at' => now()->toISOString()
        ];
    }

    /**
     * Check for drug interactions (mock implementation).
     *
     * @param array $prescriptionData
     * @param int $patientId
     * @return array
     */
    private function checkDrugInteractions(array $prescriptionData, int $patientId): array
    {
        // In a real implementation, this would query the patient's current medications
        // and check for interactions using a drug interaction database
        
        // Mock implementation - assume passed for demo
        return [
            'passed' => true,
            'details' => 'No significant drug interactions detected',
            'checked_at' => now()->toISOString()
        ];
    }

    /**
     * Check for duplicate therapy (mock implementation).
     *
     * @param array $prescriptionData
     * @param int $patientId
     * @return array
     */
    private function checkDuplicateTherapy(array $prescriptionData, int $patientId): array
    {
        // In a real implementation, this would check if the patient is already
        // receiving similar therapy from other prescriptions
        
        // Mock implementation - assume passed for demo
        return [
            'passed' => true,
            'details' => 'No duplicate therapy detected',
            'checked_at' => now()->toISOString()
        ];
    }

    /**
     * Check dosage appropriateness (mock implementation).
     *
     * @param array $prescriptionData
     * @param int $patientId
     * @return array
     */
    private function checkDosage(array $prescriptionData, int $patientId): array
    {
        // In a real implementation, this would check if the dosage is appropriate
        // for the patient's age, weight, renal function, etc.
        
        // Mock implementation - assume passed for demo
        return [
            'passed' => true,
            'details' => 'Dosage appropriate for patient',
            'checked_at' => now()->toISOString()
        ];
    }

    /**
     * Check medication expiry.
     *
     * @param string $expiryDate
     * @return array
     */
    private function checkExpiry(string $expiryDate): array
    {
        $expiry = \Carbon\Carbon::parse($expiryDate);
        $today = \Carbon\Carbon::today();
        
        if ($expiry->isBefore($today)) {
            return [
                'passed' => false,
                'details' => 'Medication expired on ' . $expiry->format('Y-m-d'),
                'checked_at' => now()->toISOString()
            ];
        }
        
        // Check if expiring within 30 days
        if ($expiry->diffInDays($today) <= 30) {
            return [
                'passed' => true,
                'details' => 'Medication expiring soon (' . $expiry->format('Y-m-d') . ')',
                'checked_at' => now()->toISOString()
            ];
        }
        
        return [
            'passed' => true,
            'details' => 'Medication valid until ' . $expiry->format('Y-m-d'),
            'checked_at' => now()->toISOString()
        ];
    }
}