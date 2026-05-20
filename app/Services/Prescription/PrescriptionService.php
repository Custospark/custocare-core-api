<?php

declare(strict_types=1);

namespace App\Services\Prescription;

use App\Repositories\Contracts\PrescriptionRepositoryInterface;
use App\Repositories\Contracts\PrescriptionItemRepositoryInterface;
use App\Repositories\Contracts\ClinicalTemplateRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PrescriptionService
{
    protected PrescriptionRepositoryInterface $prescriptionRepository;
    protected PrescriptionItemRepositoryInterface $prescriptionItemRepository;
    protected ClinicalTemplateRepositoryInterface $clinicalTemplateRepository;

    public function __construct(
        PrescriptionRepositoryInterface $prescriptionRepository,
        PrescriptionItemRepositoryInterface $prescriptionItemRepository,
        ClinicalTemplateRepositoryInterface $clinicalTemplateRepository
    ) {
        $this->prescriptionRepository = $prescriptionRepository;
        $this->prescriptionItemRepository = $prescriptionItemRepository;
        $this->clinicalTemplateRepository = $clinicalTemplateRepository;
    }

    public function getAllPrescriptions(array $filters = []): Collection
    {
        return $this->prescriptionRepository->all($filters);
    }

    public function getPrescriptionsPaginated(int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        return $this->prescriptionRepository->paginate($perPage, $filters);
    }

    public function getPrescription(int $id): ?object
    {
        return $this->prescriptionRepository->findWithItems($id);
    }

    public function getPatientPrescriptions(int $patientId, array $statuses = []): Collection
    {
        return $this->prescriptionRepository->getByPatient($patientId, $statuses);
    }

    public function getActivePrescriptionsForBilling(int $patientId): Collection
    {
        return $this->prescriptionRepository->getReadyForBilling($patientId);
    }

    public function createPrescription(array $data, array $items, int $userId): array
    {
        try {
            DB::beginTransaction();

            // Generate prescription number
            $data['prescription_number'] = $this->prescriptionRepository->generatePrescriptionNumber($data['facility_id']);
            $data['created_by'] = $userId;
            $data['prescribed_by'] = $userId;
            $data['updated_by'] = $userId;
            $data['status'] = 'Active - Ready for Dispensing';

            // Create prescription
            $prescription = $this->prescriptionRepository->create($data);

            // Create prescription items
            foreach ($items as &$item) {
                $item['created_by'] = $userId;
                $item['updated_by'] = $userId;
            }
            
            $createdItems = $this->prescriptionItemRepository->createMany($prescription->id, $items);

            DB::commit();

            return [
                'success' => true,
                'message' => 'Prescription created successfully',
                'data' => $prescription->fresh(['items', 'patient', 'prescribedBy'])
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create prescription: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Failed to create prescription: ' . $e->getMessage(),
                'data' => null
            ];
        }
    }


    public function updatePrescription(int $id, array $data, ?array $items = null, int $userId): array
    {
        try {
            DB::beginTransaction();

            $data['updated_by'] = $userId;
            $updated = $this->prescriptionRepository->update($id, $data);

            if (!$updated) {
                throw new \Exception('Prescription not found or could not be updated');
            }

            // Update items if provided
            if ($items !== null) {
                // Delete existing items
                $this->prescriptionItemRepository->deleteByPrescription($id);
                
                // Create new items
                foreach ($items as &$item) {
                    $item['created_by'] = $userId;
                    $item['updated_by'] = $userId;
                }
                
                $this->prescriptionItemRepository->createMany($id, $items);
            }

            DB::commit();

            $prescription = $this->prescriptionRepository->findWithItems($id);

            return [
                'success' => true,
                'message' => 'Prescription updated successfully',
                'data' => $prescription
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update prescription: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Failed to update prescription: ' . $e->getMessage(),
                'data' => null
            ];
        }
    }

    public function deletePrescription(int $id): array
    {
        try {
            DB::beginTransaction();

            $deleted = $this->prescriptionRepository->delete($id);

            DB::commit();

            return [
                'success' => $deleted,
                'message' => $deleted ? 'Prescription deleted successfully' : 'Prescription not found',
                'data' => null
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to delete prescription: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Failed to delete prescription: ' . $e->getMessage(),
                'data' => null
            ];
        }
    }

    public function cancelPrescription(int $id, string $reason, int $cancelledByUserId, ?string $notes = null): array
    {
        try {
            $cancelled = $this->prescriptionRepository->cancel($id, $reason, $cancelledByUserId, $notes);

            return [
                'success' => $cancelled,
                'message' => $cancelled ? 'Prescription cancelled successfully' : 'Prescription not found',
                'data' => $cancelled ? $this->prescriptionRepository->find($id) : null
            ];
        } catch (\Exception $e) {
            Log::error('Failed to cancel prescription: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Failed to cancel prescription: ' . $e->getMessage(),
                'data' => null
            ];
        }
    }

    public function markAsDispensed(int $id, array $dispensingData): array
    {
        try {
            $dispensed = $this->prescriptionRepository->markAsDispensed($id, $dispensingData);

            return [
                'success' => $dispensed,
                'message' => $dispensed ? 'Prescription marked as dispensed' : 'Prescription not found',
                'data' => $dispensed ? $this->prescriptionRepository->find($id) : null
            ];
        } catch (\Exception $e) {
            Log::error('Failed to mark prescription as dispensed: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Failed to mark prescription as dispensed: ' . $e->getMessage(),
                'data' => null
            ];
        }
    }

    public function applyTemplate(int $prescriptionId, int $templateId, int $userId): array
    {
        try {
            $template = $this->clinicalTemplateRepository->find($templateId);
            
            if (!$template) {
                return [
                    'success' => false,
                    'message' => 'Template not found',
                    'data' => null
                ];
            }

            $prescription = $this->prescriptionRepository->find($prescriptionId);
            
            if (!$prescription) {
                return [
                    'success' => false,
                    'message' => 'Prescription not found',
                    'data' => null
                ];
            }

            DB::beginTransaction();

            // Apply template data to prescription
            $prescription->diagnosis = $template->default_diagnosis;
            $prescription->clinical_notes = $template->default_notes;
            $prescription->patient_education_notes = $template->patient_instructions;
            $prescription->clinical_template_id = $template->id;
            $prescription->updated_by = $userId;
            $prescription->save();

            // Increment template usage
            $this->clinicalTemplateRepository->incrementUsage($templateId);

            // Get template medications
            $templateMedications = $template->default_medications;
            
            if (!empty($templateMedications)) {
                // Delete existing items
                $this->prescriptionItemRepository->deleteByPrescription($prescriptionId);

                // Create new items from template with defaults for safety
                $items = [];
                foreach ($templateMedications as $med) {
                    $med['prescription_id'] = $prescriptionId;
                    $med['created_by'] = $userId;
                    $med['updated_by'] = $userId;
                    // Provide defaults for fields that may be missing from older templates
                    $med['dosage_form'] ??= 'Tablet';
                    $med['dosage_unit'] ??= 'tablet(s)';
                    $med['duration_unit'] ??= 'Day(s)';
                    $med['route'] ??= 'By mouth (Oral)';
                    $med['administration_instructions'] ??= 'No special instructions';
                    $med['refills'] ??= '0 refills - One time only';
                    $med['substitution'] ??= 'Generic substitution allowed';
                    $items[] = $med;
                }

                $this->prescriptionItemRepository->createMany($prescriptionId, $items);
            }

            DB::commit();

            $updatedPrescription = $this->prescriptionRepository->findWithItems($prescriptionId);

            return [
                'success' => true,
                'message' => 'Template applied successfully',
                'data' => $updatedPrescription
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to apply template: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Failed to apply template: ' . $e->getMessage(),
                'data' => null
            ];
        }
    }

    public function getPrescriptionForBilling(int $prescriptionId): array
    {
        try {
            $prescription = $this->prescriptionRepository->findWithItems($prescriptionId);
            
            if (!$prescription) {
                return [
                    'success' => false,
                    'message' => 'Prescription not found',
                    'data' => null
                ];
            }

            return [
                'success' => true,
                'message' => 'Prescription retrieved successfully',
                'data' => [
                    'prescription' => $prescription,
                    'billing_items' => $prescription->getBillingItems(),
                    'total_items' => $prescription->total_items_count,
                    'total_quantity' => $prescription->total_quantity
                ]
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get prescription for billing: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Failed to retrieve prescription: ' . $e->getMessage(),
                'data' => null
            ];
        }
    }
}