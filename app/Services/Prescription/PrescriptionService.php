<?php

namespace App\Services\Prescription;

use App\Services\Contracts\PrescriptionServiceInterface;
use App\Repositories\Contracts\PrescriptionRepositoryInterface;
use App\Models\Prescription;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class PrescriptionService implements PrescriptionServiceInterface
{
    /**
     * @var PrescriptionRepositoryInterface
     */
    protected $prescriptionRepository;

    /**
     * Constructor
     *
     * @param PrescriptionRepositoryInterface $prescriptionRepository
     */
    public function __construct(PrescriptionRepositoryInterface $prescriptionRepository)
    {
        $this->prescriptionRepository = $prescriptionRepository;
    }

    /**
     * Get all prescriptions with filters
     *
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getAllPrescriptions(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        try {
            // Apply authorization filters if user is not admin
            if (!auth::user()()->hasRole('admin')) {
                $filters['facility_id'] = auth::user()()->facility_id;
                
                // If user is a provider, only show their prescriptions
                if (auth::user()()->hasRole('provider')) {
                    $filters['provider_id'] = auth::id()();
                }
            }
            
            return $this->prescriptionRepository->getAll($filters, $perPage);
        } catch (\Exception $e) {
            Log::error('Failed to get all prescriptions', [
                'user_id' => auth::id()(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw new \RuntimeException('Unable to retrieve prescriptions. Please try again later.');
        }
    }

    /**
     * Get prescription by UUID
     *
     * @param string $uuid
     * @return Prescription
     */
    public function getPrescriptionByUuid(string $uuid): Prescription
    {
        try {
            $prescription = $this->prescriptionRepository->findByUuid($uuid);
            
            if (!$prescription) {
                throw new \Illuminate\Database\Eloquent\ModelNotFoundException(
                    "Prescription with UUID {$uuid} not found."
                );
            }
            
            // Check authorization
            $this->authorizePrescriptionAccess($prescription);
            
            return $prescription;
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            throw $e; // Re-throw for controller to handle
        } catch (\Exception $e) {
            Log::error('Failed to get prescription by UUID', [
                'uuid' => $uuid,
                'user_id' => auth::id()(),
                'error' => $e->getMessage()
            ]);
            
            throw new \RuntimeException('Unable to retrieve prescription. Please try again later.');
        }
    }

    /**
     * Get patient prescriptions
     *
     * @param int $patientId
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getPatientPrescriptions(int $patientId, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        try {
            // Verify patient exists and user has access
            $this->validatePatientAccess($patientId);
            
            return $this->prescriptionRepository->getByPatientId($patientId, $filters, $perPage);
        } catch (\Exception $e) {
            Log::error('Failed to get patient prescriptions', [
                'patient_id' => $patientId,
                'user_id' => auth::id()(),
                'error' => $e->getMessage()
            ]);
            
            throw new \RuntimeException('Unable to retrieve patient prescriptions. Please try again later.');
        }
    }

    /**
     * Create new prescription with validation
     *
     * @param array $data
     * @return Prescription
     */
    public function createPrescription(array $data): Prescription
    {
        return DB::transaction(function () use ($data) {
            try {
                // Validate required fields
                $validator = Validator::make($data, [
                    'patient_id' => 'required|exists:patients,id',
                    'prescribing_provider_staff_id' => 'required|exists:staff,id',
                    'inventory_item_id' => 'required|exists:inventory_items,id',
                    'medication_name' => 'required|string|max:300',
                    'dosage_strength' => 'required|string|max:100',
                    'dosage_form' => 'required|string|max:100',
                    'route' => 'required|string|max:100',
                    'sig_instructions' => 'required|string',
                    'quantity_prescribed' => 'required|numeric|min:0.01',
                    'quantity_unit' => 'required|string|max:50',
                    'valid_from' => 'required|date',
                    'valid_to' => 'required|date|after:valid_from',
                ]);
                
                if ($validator->fails()) {
                    throw new \InvalidArgumentException($validator->errors()->first());
                }
                
                // Check for duplicate prescription attempts
                $duplicateCheck = Prescription::where('patient_id', $data['patient_id'])
                    ->where('medication_name', $data['medication_name'])
                    ->where('prescribed_at', '>=', now()->subHours(24))
                    ->exists();
                    
                if ($duplicateCheck) {
                    throw new \InvalidArgumentException('A similar prescription was created recently for this patient.');
                }
                
                // Set default values
                $data['prescription_uuid'] = \Illuminate\Support\Str::uuid()->toString();
                $data['facility_id'] = $data['facility_id'] ?? auth::user()()->facility_id;
                $data['prescribed_at'] = $data['prescribed_at'] ?? now();
                $data['status'] = 'active';
                $data['dispense_status'] = 'pending';
                
                // Validate drug safety if enabled
                if (config('prescription.enable_drug_safety_check')) {
                    $safetyCheck = $this->validateDrugSafety(
                        $data['patient_id'],
                        $data['medication_name'],
                        $data['diagnosis_codes'] ?? []
                    );
                    
                    if (!$safetyCheck['is_safe']) {
                        $data['drug_allergy_check_results'] = $safetyCheck['allergy_check'];
                        $data['drug_interaction_check_results'] = $safetyCheck['interaction_check'];
                        
                        if ($safetyCheck['has_critical_interaction']) {
                            throw new \InvalidArgumentException(
                                'Critical drug interaction detected. Prescription cannot be created.'
                            );
                        }
                    }
                }
                
                // Calculate days supply if not provided
                if (empty($data['days_supply']) && !empty($data['quantity_prescribed'])) {
                    $data['days_supply'] = $this->calculateDaysSupply(
                        $data['quantity_prescribed'],
                        $data['quantity_unit'],
                        $data['sig_instructions']
                    );
                }
                
                // Create prescription
                $prescription = $this->prescriptionRepository->create($data);
                
                // Log prescription creation
                Log::info('Prescription created successfully', [
                    'prescription_id' => $prescription->id,
                    'prescription_uuid' => $prescription->prescription_uuid,
                    'patient_id' => $prescription->patient_id,
                    'provider_id' => $prescription->prescribing_provider_staff_id,
                    'created_by' => auth::id()()
                ]);
                
                return $prescription;
                
            } catch (\InvalidArgumentException $e) {
                throw $e; // Re-throw validation errors
            } catch (\Exception $e) {
                Log::error('Failed to create prescription', [
                    'data' => $this->sanitizeLogData($data),
                    'user_id' => auth::id()(),
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                
                throw new \RuntimeException('Failed to create prescription. Please try again.');
            }
        });
    }

    /**
     * Update prescription
     *
     * @param string $uuid
     * @param array $data
     * @return Prescription
     */
    public function updatePrescription(string $uuid, array $data): Prescription
    {
        return DB::transaction(function () use ($uuid, $data) {
            try {
                $prescription = $this->getPrescriptionByUuid($uuid);
                
                // Check if prescription can be updated
                if (!$this->canUpdatePrescription($prescription)) {
                    throw new \InvalidArgumentException(
                        'Prescription cannot be updated in its current state.'
                    );
                }
                
                // Remove fields that shouldn't be updated directly
                unset($data['prescription_uuid']);
                unset($data['patient_id']);
                unset($data['prescribing_provider_staff_id']);
                unset($data['created_by_staff_id']);
                
                // If updating medication, re-run safety checks
                if (isset($data['medication_name']) && $data['medication_name'] !== $prescription->medication_name) {
                    if (config('prescription.enable_drug_safety_check')) {
                        $safetyCheck = $this->validateDrugSafety(
                            $prescription->patient_id,
                            $data['medication_name'],
                            $prescription->diagnosis_codes ?? []
                        );
                        
                        $data['drug_allergy_check_results'] = $safetyCheck['allergy_check'];
                        $data['drug_interaction_check_results'] = $safetyCheck['interaction_check'];
                    }
                }
                
                $updatedPrescription = $this->prescriptionRepository->update($prescription, $data);
                
                Log::info('Prescription updated successfully', [
                    'prescription_uuid' => $uuid,
                    'updated_by' => auth::id()(),
                    'updated_fields' => array_keys($data)
                ]);
                
                return $updatedPrescription;
                
            } catch (\InvalidArgumentException $e) {
                throw $e;
            } catch (\Exception $e) {
                Log::error('Failed to update prescription', [
                    'uuid' => $uuid,
                    'user_id' => auth::id()(),
                    'error' => $e->getMessage()
                ]);
                
                throw new \RuntimeException('Failed to update prescription. Please try again.');
            }
        });
    }

    /**
     * Delete prescription (soft delete)
     *
     * @param string $uuid
     * @return bool
     */
    public function deletePrescription(string $uuid): bool
    {
        try {
            $prescription = $this->getPrescriptionByUuid($uuid);
            
            // Check if prescription can be deleted
            if (!$this->canDeletePrescription($prescription)) {
                throw new \InvalidArgumentException(
                    'Prescription cannot be deleted in its current state.'
                );
            }
            
            $deleted = $this->prescriptionRepository->delete($prescription);
            
            if ($deleted) {
                Log::info('Prescription deleted', [
                    'prescription_uuid' => $uuid,
                    'deleted_by' => auth::id()()
                ]);
            }
            
            return $deleted;
            
        } catch (\InvalidArgumentException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Failed to delete prescription', [
                'uuid' => $uuid,
                'user_id' => auth::id()(),
                'error' => $e->getMessage()
            ]);
            
            throw new \RuntimeException('Failed to delete prescription. Please try again.');
        }
    }

    /**
     * Process prescription refill
     *
     * @param string $uuid
     * @param array $refillData
     * @return Prescription
     */
    public function processRefill(string $uuid, array $refillData): Prescription
    {
        return DB::transaction(function () use ($uuid, $refillData) {
            try {
                $prescription = $this->getPrescriptionByUuid($uuid);
                
                // Check refill eligibility
                $eligibility = $this->checkRefillEligibility($uuid);
                
                if (!$eligibility['is_eligible']) {
                    throw new \InvalidArgumentException($eligibility['reason']);
                }
                
                // Add audit information
                $refillData['refilled_by'] = auth::id()();
                $refillData['refilled_at'] = now()->toIso8601String();
                
                $refilledPrescription = $this->prescriptionRepository->processRefill($prescription, $refillData);
                
                Log::info('Prescription refill processed', [
                    'prescription_uuid' => $uuid,
                    'refilled_by' => auth::id()(),
                    'refills_remaining' => $refilledPrescription->refills_remaining
                ]);
                
                return $refilledPrescription;
                
            } catch (\InvalidArgumentException $e) {
                throw $e;
            } catch (\Exception $e) {
                Log::error('Failed to process prescription refill', [
                    'uuid' => $uuid,
                    'user_id' => auth::id()(),
                    'error' => $e->getMessage()
                ]);
                
                throw new \RuntimeException('Failed to process refill. Please try again.');
            }
        });
    }

    /**
     * Update dispense status
     *
     * @param string $uuid
     * @param string $status
     * @param array $metadata
     * @return Prescription
     */
    public function updateDispenseStatus(string $uuid, string $status, array $metadata = []): Prescription
    {
        try {
            $prescription = $this->getPrescriptionByUuid($uuid);
            
            // Validate status transition
            $validTransitions = $this->getValidDispenseStatusTransitions($prescription->dispense_status);
            
            if (!in_array($status, $validTransitions)) {
                throw new \InvalidArgumentException(
                    "Cannot transition from {$prescription->dispense_status} to {$status}"
                );
            }
            
            $updatedPrescription = $this->prescriptionRepository->updateDispenseStatus(
                $prescription,
                $status,
                $metadata
            );
            
            Log::info('Dispense status updated', [
                'prescription_uuid' => $uuid,
                'old_status' => $prescription->dispense_status,
                'new_status' => $status,
                'updated_by' => auth::id()()
            ]);
            
            return $updatedPrescription;
            
        } catch (\InvalidArgumentException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Failed to update dispense status', [
                'uuid' => $uuid,
                'status' => $status,
                'user_id' => auth::id()(),
                'error' => $e->getMessage()
            ]);
            
            throw new \RuntimeException('Failed to update dispense status. Please try again.');
        }
    }

    /**
     * Discontinue prescription
     *
     * @param string $uuid
     * @param string $reason
     * @param int|null $discontinuedById
     * @return Prescription
     */
    public function discontinuePrescription(string $uuid, string $reason, ?int $discontinuedById = null): Prescription
    {
        try {
            $prescription = $this->getPrescriptionByUuid($uuid);
            
            // Validate reason
            if (empty($reason) || strlen($reason) < 5) {
                throw new \InvalidArgumentException('A valid discontinuation reason is required.');
            }
            
            $discontinuedPrescription = $this->prescriptionRepository->discontinue(
                $prescription,
                $reason,
                $discontinuedById ?? auth::id()()
            );
            
            Log::info('Prescription discontinued', [
                'prescription_uuid' => $uuid,
                'reason' => $reason,
                'discontinued_by' => $discontinuedById ?? auth::id()()
            ]);
            
            return $discontinuedPrescription;
            
        } catch (\InvalidArgumentException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Failed to discontinue prescription', [
                'uuid' => $uuid,
                'user_id' => auth::id()(),
                'error' => $e->getMessage()
            ]);
            
            throw new \RuntimeException('Failed to discontinue prescription. Please try again.');
        }
    }

    /**
     * Validate drug interactions and allergies
     *
     * @param int $patientId
     * @param string $medicationName
     * @param array $existingConditions
     * @return array
     */
    public function validateDrugSafety(int $patientId, string $medicationName, array $existingConditions = []): array
    {
        // This would integrate with external drug safety API
        // For now, return mock response
        
        return [
            'is_safe' => true,
            'has_allergy' => false,
            'has_interaction' => false,
            'has_critical_interaction' => false,
            'allergy_check' => [
                'checked_at' => now()->toIso8601String(),
                'allergies_found' => [],
                'recommendations' => []
            ],
            'interaction_check' => [
                'checked_at' => now()->toIso8601String(),
                'interactions_found' => [],
                'severity' => 'none',
                'recommendations' => []
            ]
        ];
    }

    /**
     * Check prescription refill eligibility
     *
     * @param string $uuid
     * @return array
     */
    public function checkRefillEligibility(string $uuid): array
    {
        try {
            $prescription = $this->getPrescriptionByUuid($uuid);
            
            if (!$prescription->isActive()) {
                return [
                    'is_eligible' => false,
                    'reason' => 'Prescription is not active.',
                    'prescription_status' => $prescription->status
                ];
            }
            
            if (!$prescription->isRefillable()) {
                return [
                    'is_eligible' => false,
                    'reason' => 'No refills remaining or prescription not refillable.',
                    'refills_remaining' => $prescription->refills_remaining
                ];
            }
            
            // Check if too early for refill
            if ($prescription->do_not_fill_before && $prescription->do_not_fill_before > now()) {
                return [
                    'is_eligible' => false,
                    'reason' => 'Too early for refill. Do not fill before ' . $prescription->do_not_fill_before->format('Y-m-d'),
                    'do_not_fill_before' => $prescription->do_not_fill_before
                ];
            }
            
            return [
                'is_eligible' => true,
                'refills_remaining' => $prescription->refills_remaining,
                'valid_until' => $prescription->valid_to,
                'days_supply' => $prescription->days_supply
            ];
            
        } catch (\Exception $e) {
            Log::error('Failed to check refill eligibility', [
                'uuid' => $uuid,
                'error' => $e->getMessage()
            ]);
            
            return [
                'is_eligible' => false,
                'reason' => 'Unable to determine eligibility.'
            ];
        }
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
        try {
            // Verify facility access
            if (!auth::user()()->hasRole('admin') && auth::user()()->facility_id !== $facilityId) {
                throw new \Illuminate\Auth\Access\AuthorizationException(
                    'You do not have access to this facility.'
                );
            }
            
            return $this->prescriptionRepository->getPrescriptionsNeedingTransmission($facilityId, $limit);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Failed to get prescriptions needing transmission', [
                'facility_id' => $facilityId,
                'user_id' => auth::id()(),
                'error' => $e->getMessage()
            ]);
            
            throw new \RuntimeException('Unable to retrieve prescriptions for transmission.');
        }
    }

    /**
     * Transmit prescription to pharmacy
     *
     * @param string $uuid
     * @param array $transmissionData
     * @return Prescription
     */
    public function transmitPrescription(string $uuid, array $transmissionData = []): Prescription
    {
        return DB::transaction(function () use ($uuid, $transmissionData) {
            try {
                $prescription = $this->getPrescriptionByUuid($uuid);
                
                // Check if prescription can be transmitted
                if (!$prescription->is_electronic_prescription) {
                    throw new \InvalidArgumentException('Prescription is not configured for electronic transmission.');
                }
                
                if ($prescription->transmitted_at) {
                    throw new \InvalidArgumentException('Prescription has already been transmitted.');
                }
                
                // Validate transmission data
                $validator = Validator::make($transmissionData, [
                    'pharmacy_ncpdp_id' => 'required_if:transmit_to_pharmacy,null|string|max:20',
                    'transmit_to_pharmacy' => 'required_if:pharmacy_ncpdp_id,null|string|max:300'
                ]);
                
                if ($validator->fails()) {
                    throw new \InvalidArgumentException($validator->errors()->first());
                }
                
                // Update prescription with transmission data
                $updateData = array_merge([
                    'transmitted_at' => now(),
                    'dispense_status' => 'transmitted'
                ], $transmissionData);
                
                $transmittedPrescription = $this->prescriptionRepository->update($prescription, $updateData);
                
                // Here you would integrate with actual e-prescribing gateway
                // For now, log the transmission
                Log::info('Prescription transmitted', [
                    'prescription_uuid' => $uuid,
                    'pharmacy_ncpdp_id' => $transmissionData['pharmacy_ncpdp_id'] ?? null,
                    'transmitted_by' => auth::id()(),
                    'transmitted_at' => now()->toIso8601String()
                ]);
                
                return $transmittedPrescription;
                
            } catch (\InvalidArgumentException $e) {
                throw $e;
            } catch (\Exception $e) {
                Log::error('Failed to transmit prescription', [
                    'uuid' => $uuid,
                    'user_id' => auth::id()(),
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                
                throw new \RuntimeException('Failed to transmit prescription. Please try again.');
            }
        });
    }

    /**
     * Get prescription statistics
     *
     * @param int $facilityId
     * @param array $dateRange
     * @return array
     */
    public function getPrescriptionStatistics(int $facilityId, array $dateRange = []): array
    {
        try {
            // Verify facility access
            if (!auth::user()()->hasRole('admin') && auth::user()()->facility_id !== $facilityId) {
                throw new \Illuminate\Auth\Access\AuthorizationException(
                    'You do not have access to this facility.'
                );
            }
            
            return $this->prescriptionRepository->getStatistics($facilityId, $dateRange);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Failed to get prescription statistics', [
                'facility_id' => $facilityId,
                'user_id' => auth::id()(),
                'error' => $e->getMessage()
            ]);
            
            throw new \RuntimeException('Unable to retrieve prescription statistics.');
        }
    }

    /**
     * Authorize prescription access
     *
     * @param Prescription $prescription
     * @return void
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    private function authorizePrescriptionAccess(Prescription $prescription): void
    {
        $user = auth::user()();
        
        // Admin has full access
        if ($user->hasRole('admin')) {
            return;
        }
        
        // Provider can access their own prescriptions
        if ($user->hasRole('provider') && $prescription->prescribing_provider_staff_id === $user->id) {
            return;
        }
        
        // Staff can access prescriptions from their facility
        if ($prescription->facility_id === $user->facility_id) {
            return;
        }
        
        throw new \Illuminate\Auth\Access\AuthorizationException(
            'You do not have permission to access this prescription.'
        );
    }

    /**
     * Validate patient access
     *
     * @param int $patientId
     * @return void
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    private function validatePatientAccess(int $patientId): void
    {
        // In a real application, you would check if the user has access to this patient
        // For now, we'll assume basic validation
        $user = auth::user()();
        
        if (!$user->hasRole('admin')) {
            // Check if patient belongs to user's facility
            $patientFacility = \App\Models\Patient::where('id', $patientId)
                ->value('facility_id');
                
            if ($patientFacility !== $user->facility_id) {
                throw new \Illuminate\Auth\Access\AuthorizationException(
                    'You do not have access to this patient.'
                );
            }
        }
    }

    /**
     * Check if prescription can be updated
     *
     * @param Prescription $prescription
     * @return bool
     */
    private function canUpdatePrescription(Prescription $prescription): bool
    {
        // Cannot update if transmitted or dispensed
        if (in_array($prescription->dispense_status, ['transmitted', 'dispensed', 'received_by_pharmacy'])) {
            return false;
        }
        
        // Cannot update if discontinued or cancelled
        if (in_array($prescription->status, ['discontinued', 'cancelled', 'expired'])) {
            return false;
        }
        
        return true;
    }

    /**
     * Check if prescription can be deleted
     *
     * @param Prescription $prescription
     * @return bool
     */
    private function canDeletePrescription(Prescription $prescription): bool
    {
        // Cannot delete if transmitted or dispensed
        if (in_array($prescription->dispense_status, ['transmitted', 'dispensed', 'received_by_pharmacy'])) {
            return false;
        }
        
        // Cannot delete if not active
        if (!$prescription->isActive()) {
            return false;
        }
        
        return true;
    }

    /**
     * Calculate days supply based on quantity and instructions
     *
     * @param float $quantity
     * @param string $unit
     * @param string $instructions
     * @return int|null
     */
    private function calculateDaysSupply(float $quantity, string $unit, string $instructions): ?int
    {
        // This is a simplified calculation
        // In a real application, you would parse the SIG instructions
        // to determine daily dosage and calculate days supply
        
        preg_match('/take (\d+)/i', $instructions, $matches);
        $dailyDosage = $matches[1] ?? 1;
        
        if ($dailyDosage > 0) {
            return (int) ceil($quantity / $dailyDosage);
        }
        
        return null;
    }

    /**
     * Get valid dispense status transitions
     *
     * @param string $currentStatus
     * @return array
     */
    private function getValidDispenseStatusTransitions(string $currentStatus): array
    {
        $transitions = [
            'pending' => ['transmitted', 'cancelled', 'discontinued'],
            'transmitted' => ['received_by_pharmacy', 'cancelled', 'discontinued'],
            'received_by_pharmacy' => ['in_progress', 'cancelled', 'discontinued'],
            'in_progress' => ['ready_for_pickup', 'cancelled', 'discontinued'],
            'ready_for_pickup' => ['dispensed', 'not_picked_up', 'cancelled', 'discontinued'],
            'dispensed' => ['not_picked_up', 'discontinued'], // Rare but possible
            'not_picked_up' => ['ready_for_pickup', 'cancelled', 'discontinued'],
            'cancelled' => [], // Terminal state
            'discontinued' => [], // Terminal state
        ];
        
        return $transitions[$currentStatus] ?? [];
    }

    /**
     * Sanitize log data to remove sensitive information
     *
     * @param array $data
     * @return array
     */
    private function sanitizeLogData(array $data): array
    {
        $sensitiveFields = [
            'prescriber_dea_number_encrypted',
            'drug_allergy_check_results',
            'drug_interaction_check_results',
            'metadata',
            'clinical_indication'
        ];
        
        foreach ($sensitiveFields as $field) {
            if (isset($data[$field])) {
                $data[$field] = '[REDACTED]';
            }
        }
        
        return $data;
    }
}