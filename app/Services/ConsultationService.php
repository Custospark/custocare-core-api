<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Consultation;
use App\Repositories\Contracts\ConsultationRepositoryInterface;
use App\Services\Contracts\ConsultationServiceInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ConsultationService implements ConsultationServiceInterface
{
    /**
     * @var ConsultationRepositoryInterface
     */
    protected ConsultationRepositoryInterface $consultationRepository;

    /**
     * Constructor.
     *
     * @param ConsultationRepositoryInterface $consultationRepository
     */
    public function __construct(ConsultationRepositoryInterface $consultationRepository)
    {
        $this->consultationRepository = $consultationRepository;
    }

    /**
     * {@inheritdoc}
     */
    public function getAllConsultations(array $filters = [], int $perPage = 20): array
    {
        try {
            $consultations = $this->consultationRepository->getAllPaginated($filters, $perPage);

            return [
                'success' => true,
                'data' => $consultations,
                'message' => 'Consultations retrieved successfully',
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get consultations', [
                'error' => $e->getMessage(),
                'filters' => $filters,
            ]);

            return [
                'success' => false,
                'data' => [],
                'message' => 'Failed to retrieve consultations',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getConsultationById(int $id): array
    {
        try {
            $consultation = $this->consultationRepository->findByIdWithRelations($id, [
                'facility', 'patient', 'visit', 'requestingStaff', 'consultantStaff'
            ]);

            if (!$consultation) {
                return [
                    'success' => false,
                    'data' => null,
                    'message' => 'Consultation not found',
                    'error' => 'Consultation not found',
                ];
            }

            return [
                'success' => true,
                'data' => $consultation,
                'message' => 'Consultation retrieved successfully',
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get consultation by ID', [
                'error' => $e->getMessage(),
                'id' => $id,
            ]);

            return [
                'success' => false,
                'data' => null,
                'message' => 'Failed to retrieve consultation',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getPatientConsultations(int $patientId, array $filters = [], int $perPage = 20): array
    {
        try {
            $consultations = $this->consultationRepository->getPaginatedByPatient($patientId, $filters, $perPage);

            return [
                'success' => true,
                'data' => $consultations,
                'message' => 'Patient consultations retrieved successfully',
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get patient consultations', [
                'error' => $e->getMessage(),
                'patient_id' => $patientId,
            ]);

            return [
                'success' => false,
                'data' => [],
                'message' => 'Failed to retrieve patient consultations',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getVisitConsultations(int $visitId): array
    {
        try {
            $consultations = $this->consultationRepository->getByVisit($visitId);

            return [
                'success' => true,
                'data' => $consultations,
                'message' => 'Visit consultations retrieved successfully',
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get visit consultations', [
                'error' => $e->getMessage(),
                'visit_id' => $visitId,
            ]);

            return [
                'success' => false,
                'data' => [],
                'message' => 'Failed to retrieve visit consultations',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function createConsultation(array $data, int $requestedByStaffId): array
    {
        DB::beginTransaction();

        try {
            // Validate the data
            $validatedData = $this->validateConsultationData($data);

            // Add requesting staff
            $validatedData['requesting_staff_id'] = $requestedByStaffId;

            // Set requested_at if not provided
            if (!isset($validatedData['requested_at'])) {
                $validatedData['requested_at'] = now();
            }

            $consultation = $this->consultationRepository->create($validatedData);

            DB::commit();

            Log::info('Consultation created successfully', [
                'consultation_id' => $consultation->id,
                'patient_id' => $consultation->patient_id,
                'requested_by' => $requestedByStaffId,
            ]);

            return [
                'success' => true,
                'data' => $consultation->fresh(),
                'message' => 'Consultation request created successfully',
            ];
        } catch (ValidationException $e) {
            DB::rollBack();
            return [
                'success' => false,
                'data' => null,
                'message' => 'Validation failed',
                'error' => $e->errors(),
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create consultation', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);

            return [
                'success' => false,
                'data' => null,
                'message' => 'Failed to create consultation',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function updateConsultation(int $id, array $data, int $updatedByStaffId): array
    {
        DB::beginTransaction();

        try {
            $consultation = $this->consultationRepository->findById($id);

            if (!$consultation) {
                return [
                    'success' => false,
                    'data' => null,
                    'message' => 'Consultation not found',
                    'error' => 'Consultation not found',
                ];
            }

            // Only pending consultations can be updated directly(Allow for now)
            if ($consultation->isCompleted()) {
                return [
                    'success' => false,
                    'data' => null,
                    'message' => 'Completed consultations can not be updated',
                    'error' => 'Consultation already completed.',
                ];
            }

            $validatedData = $this->validateConsultationData($data, $consultation);

            $updated = $this->consultationRepository->update($consultation, $validatedData);

            if (!$updated) {
                throw new \Exception('Failed to update consultation');
            }

            DB::commit();

            Log::info('Consultation updated successfully', [
                'consultation_id' => $consultation->id,
                'updated_by' => $updatedByStaffId,
            ]);

            return [
                'success' => true,
                'data' => $consultation->fresh(),
                'message' => 'Consultation updated successfully',
            ];
        } catch (ValidationException $e) {
            DB::rollBack();
            return [
                'success' => false,
                'data' => null,
                'message' => 'Validation failed',
                'error' => $e->errors(),
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update consultation', [
                'error' => $e->getMessage(),
                'id' => $id,
            ]);

            return [
                'success' => false,
                'data' => null,
                'message' => 'Failed to update consultation',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function deleteConsultation(int $id): array
    {
        DB::beginTransaction();

        try {
            $consultation = $this->consultationRepository->findById($id);

            if (!$consultation) {
                return [
                    'success' => false,
                    'message' => 'Consultation not found',
                    'error' => 'Consultation not found',
                ];
            }

            $deleted = $this->consultationRepository->delete($consultation);

            if (!$deleted) {
                throw new \Exception('Failed to delete consultation');
            }

            DB::commit();

            Log::info('Consultation deleted successfully', [
                'consultation_id' => $id,
            ]);

            return [
                'success' => true,
                'message' => 'Consultation deleted successfully',
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to delete consultation', [
                'error' => $e->getMessage(),
                'id' => $id,
            ]);

            return [
                'success' => false,
                'message' => 'Failed to delete consultation',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function acceptConsultation(int $id, int $consultantStaffId): array
    {
        DB::beginTransaction();

        try {
            $consultation = $this->consultationRepository->findById($id);

            if (!$consultation) {
                return [
                    'success' => false,
                    'data' => null,
                    'message' => 'Consultation not found',
                    'error' => 'Consultation not found',
                ];
            }

            if (!$consultation->isPending()) {
                return [
                    'success' => false,
                    'data' => null,
                    'message' => 'Only pending consultations can be accepted',
                    'error' => 'Consultation is not pending',
                ];
            }

            $accepted = $consultation->accept($consultantStaffId);

            if (!$accepted) {
                throw new \Exception('Failed to accept consultation');
            }

            DB::commit();

            Log::info('Consultation accepted successfully', [
                'consultation_id' => $id,
                'consultant_id' => $consultantStaffId,
            ]);

            return [
                'success' => true,
                'data' => $consultation->fresh(),
                'message' => 'Consultation accepted successfully',
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to accept consultation', [
                'error' => $e->getMessage(),
                'id' => $id,
            ]);

            return [
                'success' => false,
                'data' => null,
                'message' => 'Failed to accept consultation',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function declineConsultation(int $id, ?string $reason = null): array
    {
        DB::beginTransaction();

        try {
            $consultation = $this->consultationRepository->findById($id);

            if (!$consultation) {
                return [
                    'success' => false,
                    'data' => null,
                    'message' => 'Consultation not found',
                    'error' => 'Consultation not found',
                ];
            }

            if (!$consultation->isPending()) {
                return [
                    'success' => false,
                    'data' => null,
                    'message' => 'Only pending consultations can be declined',
                    'error' => 'Consultation is not pending',
                ];
            }

            $declined = $consultation->decline($reason);

            if (!$declined) {
                throw new \Exception('Failed to decline consultation');
            }

            DB::commit();

            Log::info('Consultation declined', [
                'consultation_id' => $id,
                'reason' => $reason,
            ]);

            return [
                'success' => true,
                'data' => $consultation->fresh(),
                'message' => 'Consultation declined',
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to decline consultation', [
                'error' => $e->getMessage(),
                'id' => $id,
            ]);

            return [
                'success' => false,
                'data' => null,
                'message' => 'Failed to decline consultation',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function completeConsultation(int $id, ?array $findings = null, ?array $recommendations = null): array
    {
        DB::beginTransaction();

        try {
            $consultation = $this->consultationRepository->findById($id);

            if (!$consultation) {
                return [
                    'success' => false,
                    'data' => null,
                    'message' => 'Consultation not found',
                    'error' => 'Consultation not found',
                ];
            }

            if (!$consultation->isAccepted() && !$consultation->isPending()) {
                return [
                    'success' => false,
                    'data' => null,
                    'message' => 'Only accepted or pending consultations can be completed',
                    'error' => 'Invalid status for completion',
                ];
            }

            $completed = $consultation->complete($findings, $recommendations);

            if (!$completed) {
                throw new \Exception('Failed to complete consultation');
            }

            DB::commit();

            Log::info('Consultation completed successfully', [
                'consultation_id' => $id,
            ]);

            return [
                'success' => true,
                'data' => $consultation->fresh(),
                'message' => 'Consultation completed successfully',
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to complete consultation', [
                'error' => $e->getMessage(),
                'id' => $id,
            ]);

            return [
                'success' => false,
                'data' => null,
                'message' => 'Failed to complete consultation',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function cancelConsultation(int $id, ?string $reason = null): array
    {
        DB::beginTransaction();

        try {
            $consultation = $this->consultationRepository->findById($id);

            if (!$consultation) {
                return [
                    'success' => false,
                    'data' => null,
                    'message' => 'Consultation not found',
                    'error' => 'Consultation not found',
                ];
            }

            if ($consultation->isCompleted() || $consultation->isCancelled()) {
                return [
                    'success' => false,
                    'data' => null,
                    'message' => 'Completed or cancelled consultations cannot be cancelled again',
                    'error' => 'Invalid status for cancellation',
                ];
            }

            $cancelled = $consultation->cancel($reason);

            if (!$cancelled) {
                throw new \Exception('Failed to cancel consultation');
            }

            DB::commit();

            Log::info('Consultation cancelled', [
                'consultation_id' => $id,
                'reason' => $reason,
            ]);

            return [
                'success' => true,
                'data' => $consultation->fresh(),
                'message' => 'Consultation cancelled',
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to cancel consultation', [
                'error' => $e->getMessage(),
                'id' => $id,
            ]);

            return [
                'success' => false,
                'data' => null,
                'message' => 'Failed to cancel consultation',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function scheduleConsultation(int $id, string $scheduledFor, ?string $location = null, ?int $durationMinutes = null): array
    {
        DB::beginTransaction();

        try {
            $consultation = $this->consultationRepository->findById($id);

            if (!$consultation) {
                return [
                    'success' => false,
                    'data' => null,
                    'message' => 'Consultation not found',
                    'error' => 'Consultation not found',
                ];
            }

            if (!$consultation->isAccepted() && !$consultation->isPending()) {
                return [
                    'success' => false,
                    'data' => null,
                    'message' => 'Only accepted or pending consultations can be scheduled',
                    'error' => 'Invalid status for scheduling',
                ];
            }

            $scheduled = $consultation->schedule($scheduledFor, $location, $durationMinutes);

            if (!$scheduled) {
                throw new \Exception('Failed to schedule consultation');
            }

            DB::commit();

            Log::info('Consultation scheduled successfully', [
                'consultation_id' => $id,
                'scheduled_for' => $scheduledFor,
                'location' => $location,
            ]);

            return [
                'success' => true,
                'data' => $consultation->fresh(),
                'message' => 'Consultation scheduled successfully',
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to schedule consultation', [
                'error' => $e->getMessage(),
                'id' => $id,
            ]);

            return [
                'success' => false,
                'data' => null,
                'message' => 'Failed to schedule consultation',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getPendingConsultations(?int $facilityId = null, int $limit = 50): array
    {
        try {
            $consultations = $this->consultationRepository->getPendingConsultations($facilityId, $limit);

            return [
                'success' => true,
                'data' => $consultations,
                'message' => 'Pending consultations retrieved successfully',
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get pending consultations', [
                'error' => $e->getMessage(),
                'facility_id' => $facilityId,
            ]);

            return [
                'success' => false,
                'data' => [],
                'message' => 'Failed to retrieve pending consultations',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getUrgentConsultations(?int $facilityId = null, int $limit = 50): array
    {
        try {
            $consultations = $this->consultationRepository->getUrgentConsultations($facilityId, $limit);

            return [
                'success' => true,
                'data' => $consultations,
                'message' => 'Urgent consultations retrieved successfully',
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get urgent consultations', [
                'error' => $e->getMessage(),
                'facility_id' => $facilityId,
            ]);

            return [
                'success' => false,
                'data' => [],
                'message' => 'Failed to retrieve urgent consultations',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getOverdueConsultations(?int $facilityId = null, int $limit = 50): array
    {
        try {
            $consultations = $this->consultationRepository->getOverdueConsultations($facilityId, $limit);

            return [
                'success' => true,
                'data' => $consultations,
                'message' => 'Overdue consultations retrieved successfully',
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get overdue consultations', [
                'error' => $e->getMessage(),
                'facility_id' => $facilityId,
            ]);

            return [
                'success' => false,
                'data' => [],
                'message' => 'Failed to retrieve overdue consultations',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getConsultationStatistics(int $facilityId, string $startDate, string $endDate): array
    {
        try {
            $stats = $this->consultationRepository->getConsultationStatistics($facilityId, $startDate, $endDate);

            return [
                'success' => true,
                'data' => $stats,
                'message' => 'Consultation statistics retrieved successfully',
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get consultation statistics', [
                'error' => $e->getMessage(),
                'facility_id' => $facilityId,
            ]);

            return [
                'success' => false,
                'data' => [],
                'message' => 'Failed to retrieve consultation statistics',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getConsultationCountByStatus(int $facilityId): array
    {
        try {
            $counts = $this->consultationRepository->getCountByStatus($facilityId);

            return [
                'success' => true,
                'data' => $counts,
                'message' => 'Consultation counts retrieved successfully',
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get consultation counts', [
                'error' => $e->getMessage(),
                'facility_id' => $facilityId,
            ]);

            return [
                'success' => false,
                'data' => [],
                'message' => 'Failed to retrieve consultation counts',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getConsultationsBySpecialty(string $specialty, ?int $facilityId = null, int $limit = 50): array
    {
        try {
            $consultations = $this->consultationRepository->getBySpecialty($specialty, $facilityId, $limit);

            return [
                'success' => true,
                'data' => $consultations,
                'message' => 'Consultations by specialty retrieved successfully',
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get consultations by specialty', [
                'error' => $e->getMessage(),
                'specialty' => $specialty,
                'facility_id' => $facilityId,
            ]);

            return [
                'success' => false,
                'data' => [],
                'message' => 'Failed to retrieve consultations by specialty',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Validate consultation data.
     *
     * @param array $data
     * @param Consultation|null $consultation
     * @return array
     * @throws ValidationException
     */
    private function validateConsultationData(array $data, ?Consultation $consultation = null): array
    {
        $rules = [
            'facility_id' => 'required|exists:facilities,id',
            'visit_id' => 'required|exists:visits,id',
            'patient_id' => 'required|exists:patients,id',
            'specialty_required' => 'required|string|max:200',
            'consultation_type' => 'nullable|in:in_person,telemedicine,urgent,elective,emergency',
            'priority' => 'nullable|in:routine,urgent,emergent',
            'clinical_question' => 'required|string',
            'background_information' => 'nullable|string',
            'attached_documents' => 'nullable|array',
            'findings' => 'nullable|string',
            'recommendations' => 'nullable|string',
            'recommended_orders' => 'nullable|array',
            'consultant_notes' => 'nullable|string',
            'scheduled_for' => 'nullable|date',
            'duration_minutes' => 'nullable|integer|min:5|max:480',
            'location' => 'nullable|string|max:200',
            'requires_followup' => 'nullable|boolean',
            'followup_by' => 'nullable|date|after:scheduled_for',
            'followup_instructions' => 'nullable|string',
            'custom_fields' => 'nullable|array',
        ];

        // For updates, make fields sometimes required
        if ($consultation) {
            foreach ($rules as $field => $rule) {
                if (strpos($rule, 'required') === 0) {
                    $rules[$field] = 'sometimes|' . substr($rule, 9);
                }
            }
        }

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }
}