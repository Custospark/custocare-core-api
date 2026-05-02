<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Diagnosis;
use App\Repositories\Contracts\DiagnosisRepositoryInterface;
use App\Services\Contracts\DiagnosisServiceInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class DiagnosisService implements DiagnosisServiceInterface
{
    /**
     * @var DiagnosisRepositoryInterface
     */
    protected DiagnosisRepositoryInterface $diagnosisRepository;

    /**
     * Constructor.
     *
     * @param DiagnosisRepositoryInterface $diagnosisRepository
     */
    public function __construct(DiagnosisRepositoryInterface $diagnosisRepository)
    {
        $this->diagnosisRepository = $diagnosisRepository;
    }

    /**
     * {@inheritdoc}
     */
    public function getAllDiagnoses(array $filters = [], int $perPage = 20): array
    {
        try {
            $diagnoses = $this->diagnosisRepository->getAllPaginated($filters, $perPage);

            return [
                'success' => true,
                'data' => $diagnoses,
                'message' => 'Diagnoses retrieved successfully',
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get diagnoses', [
                'error' => $e->getMessage(),
                'filters' => $filters,
            ]);

            return [
                'success' => false,
                'data' => [],
                'message' => 'Failed to retrieve diagnoses',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getDiagnosisById(int $id): array
    {
        try {
            $diagnosis = $this->diagnosisRepository->findByIdWithRelations($id, ['facility', 'patient', 'staff', 'visit', 'verifier']);

            if (!$diagnosis) {
                return [
                    'success' => false,
                    'data' => null,
                    'message' => 'Diagnosis not found',
                    'error' => 'Diagnosis not found',
                ];
            }

            return [
                'success' => true,
                'data' => $diagnosis,
                'message' => 'Diagnosis retrieved successfully',
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get diagnosis by ID', [
                'error' => $e->getMessage(),
                'id' => $id,
            ]);

            return [
                'success' => false,
                'data' => null,
                'message' => 'Failed to retrieve diagnosis',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getPatientDiagnoses(int $patientId, array $filters = []): array
    {
        try {
            $diagnoses = $this->diagnosisRepository->getByPatient($patientId, $filters);

            return [
                'success' => true,
                'data' => $diagnoses,
                'message' => 'Patient diagnoses retrieved successfully',
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get patient diagnoses', [
                'error' => $e->getMessage(),
                'patient_id' => $patientId,
            ]);

            return [
                'success' => false,
                'data' => [],
                'message' => 'Failed to retrieve patient diagnoses',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getActivePatientDiagnoses(int $patientId): array
    {
        try {
            $diagnoses = $this->diagnosisRepository->getActiveByPatient($patientId);

            return [
                'success' => true,
                'data' => $diagnoses,
                'message' => 'Active patient diagnoses retrieved successfully',
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get active patient diagnoses', [
                'error' => $e->getMessage(),
                'patient_id' => $patientId,
            ]);

            return [
                'success' => false,
                'data' => [],
                'message' => 'Failed to retrieve active diagnoses',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getPrimaryPatientDiagnoses(int $patientId): array
    {
        try {
            $diagnoses = $this->diagnosisRepository->getPrimaryByPatient($patientId);

            return [
                'success' => true,
                'data' => $diagnoses,
                'message' => 'Primary diagnoses retrieved successfully',
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get primary diagnoses', [
                'error' => $e->getMessage(),
                'patient_id' => $patientId,
            ]);

            return [
                'success' => false,
                'data' => [],
                'message' => 'Failed to retrieve primary diagnoses',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getVisitDiagnoses(int $visitId): array
    {
        try {
            $diagnoses = $this->diagnosisRepository->getByVisit($visitId);

            return [
                'success' => true,
                'data' => $diagnoses,
                'message' => 'Visit diagnoses retrieved successfully',
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get visit diagnoses', [
                'error' => $e->getMessage(),
                'visit_id' => $visitId,
            ]);

            return [
                'success' => false,
                'data' => [],
                'message' => 'Failed to retrieve visit diagnoses',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function createDiagnosis(array $data, int $createdByStaffId): array
    {
        DB::beginTransaction();

        try {
            // Validate the data
            $validatedData = $this->validateDiagnosisData($data);

            // Add staff_id
            $validatedData['staff_id'] = $createdByStaffId;

            // Check uniqueness for the visit
            if (!$this->isDiagnosisUniqueForVisit(
                $validatedData['visit_id'],
                $validatedData['diagnosis_code'],
                $validatedData['diagnosis_type']
            )) {
                return [
                    'success' => false,
                    'data' => null,
                    'message' => 'A diagnosis with this code and type already exists for this visit',
                    'error' => 'Duplicate diagnosis',
                ];
            }

            $diagnosis = $this->diagnosisRepository->create($validatedData);

            DB::commit();

            Log::info('Diagnosis created successfully', [
                'diagnosis_id' => $diagnosis->id,
                'patient_id' => $diagnosis->patient_id,
                'diagnosis_code' => $diagnosis->diagnosis_code,
                'staff_id' => $createdByStaffId,
            ]);

            return [
                'success' => true,
                'data' => $diagnosis,
                'message' => 'Diagnosis created successfully',
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
            Log::error('Failed to create diagnosis', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);

            return [
                'success' => false,
                'data' => null,
                'message' => 'Failed to create diagnosis',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function updateDiagnosis(int $id, array $data, int $updatedByStaffId): array
    {
        DB::beginTransaction();

        try {
            $diagnosis = $this->diagnosisRepository->findById($id);

            if (!$diagnosis) {
                return [
                    'success' => false,
                    'data' => null,
                    'message' => 'Diagnosis not found',
                    'error' => 'Diagnosis not found',
                ];
            }

            // Cannot update verified or disputed diagnoses
            if ($diagnosis->isVerified()) {
                return [
                    'success' => false,
                    'data' => null,
                    'message' => 'Verified diagnoses cannot be edited. Consider creating an amendment.',
                    'error' => 'Cannot edit verified diagnosis',
                ];
            }

            $validatedData = $this->validateDiagnosisData($data, $diagnosis);

            // Check uniqueness if diagnosis_code or type changed
            if ((isset($validatedData['diagnosis_code']) && $validatedData['diagnosis_code'] !== $diagnosis->diagnosis_code) ||
                (isset($validatedData['diagnosis_type']) && $validatedData['diagnosis_type'] !== $diagnosis->diagnosis_type)) {
                
                if (!$this->isDiagnosisUniqueForVisit(
                    $validatedData['visit_id'] ?? $diagnosis->visit_id,
                    $validatedData['diagnosis_code'] ?? $diagnosis->diagnosis_code,
                    $validatedData['diagnosis_type'] ?? $diagnosis->diagnosis_type,
                    $id
                )) {
                    return [
                        'success' => false,
                        'data' => null,
                        'message' => 'A diagnosis with this code and type already exists for this visit',
                        'error' => 'Duplicate diagnosis',
                    ];
                }
            }

            $updated = $this->diagnosisRepository->update($diagnosis, $validatedData);

            if (!$updated) {
                throw new \Exception('Failed to update diagnosis');
            }

            DB::commit();

            Log::info('Diagnosis updated successfully', [
                'diagnosis_id' => $diagnosis->id,
                'updated_by' => $updatedByStaffId,
            ]);

            return [
                'success' => true,
                'data' => $diagnosis->fresh(),
                'message' => 'Diagnosis updated successfully',
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
            Log::error('Failed to update diagnosis', [
                'error' => $e->getMessage(),
                'id' => $id,
            ]);

            return [
                'success' => false,
                'data' => null,
                'message' => 'Failed to update diagnosis',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function deleteDiagnosis(int $id): array
    {
        DB::beginTransaction();

        try {
            $diagnosis = $this->diagnosisRepository->findById($id);

            if (!$diagnosis) {
                return [
                    'success' => false,
                    'message' => 'Diagnosis not found',
                    'error' => 'Diagnosis not found',
                ];
            }

            $deleted = $this->diagnosisRepository->delete($diagnosis);

            if (!$deleted) {
                throw new \Exception('Failed to delete diagnosis');
            }

            DB::commit();

            Log::info('Diagnosis deleted successfully', [
                'diagnosis_id' => $id,
            ]);

            return [
                'success' => true,
                'message' => 'Diagnosis deleted successfully',
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to delete diagnosis', [
                'error' => $e->getMessage(),
                'id' => $id,
            ]);

            return [
                'success' => false,
                'message' => 'Failed to delete diagnosis',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function restoreDiagnosis(int $id): array
    {
        DB::beginTransaction();

        try {
            $restored = $this->diagnosisRepository->restore($id);

            if (!$restored) {
                return [
                    'success' => false,
                    'data' => null,
                    'message' => 'Diagnosis not found or cannot be restored',
                    'error' => 'Restore failed',
                ];
            }

            DB::commit();

            $diagnosis = $this->diagnosisRepository->findById($id);

            Log::info('Diagnosis restored successfully', [
                'diagnosis_id' => $id,
            ]);

            return [
                'success' => true,
                'data' => $diagnosis,
                'message' => 'Diagnosis restored successfully',
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to restore diagnosis', [
                'error' => $e->getMessage(),
                'id' => $id,
            ]);

            return [
                'success' => false,
                'data' => null,
                'message' => 'Failed to restore diagnosis',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function forceDeleteDiagnosis(int $id): array
    {
        DB::beginTransaction();

        try {
            $deleted = $this->diagnosisRepository->forceDelete($id);

            if (!$deleted) {
                return [
                    'success' => false,
                    'message' => 'Diagnosis not found',
                    'error' => 'Diagnosis not found',
                ];
            }

            DB::commit();

            Log::info('Diagnosis force deleted successfully', [
                'diagnosis_id' => $id,
            ]);

            return [
                'success' => true,
                'message' => 'Diagnosis permanently deleted successfully',
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to force delete diagnosis', [
                'error' => $e->getMessage(),
                'id' => $id,
            ]);

            return [
                'success' => false,
                'message' => 'Failed to permanently delete diagnosis',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function verifyDiagnosis(int $id, int $verifiedByStaffId): array
    {
        DB::beginTransaction();

        try {
            $diagnosis = $this->diagnosisRepository->findById($id);

            if (!$diagnosis) {
                return [
                    'success' => false,
                    'data' => null,
                    'message' => 'Diagnosis not found',
                    'error' => 'Diagnosis not found',
                ];
            }

            if ($diagnosis->isVerified()) {
                return [
                    'success' => false,
                    'data' => null,
                    'message' => 'Diagnosis is already verified',
                    'error' => 'Already verified',
                ];
            }

            $updated = $this->diagnosisRepository->updateVerificationStatus($diagnosis, 'verified', $verifiedByStaffId);

            if (!$updated) {
                throw new \Exception('Failed to verify diagnosis');
            }

            DB::commit();

            Log::info('Diagnosis verified successfully', [
                'diagnosis_id' => $id,
                'verified_by' => $verifiedByStaffId,
            ]);

            return [
                'success' => true,
                'data' => $diagnosis->fresh(),
                'message' => 'Diagnosis verified successfully',
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to verify diagnosis', [
                'error' => $e->getMessage(),
                'id' => $id,
            ]);

            return [
                'success' => false,
                'data' => null,
                'message' => 'Failed to verify diagnosis',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function disputeDiagnosis(int $id, ?string $reason = null): array
    {
        DB::beginTransaction();

        try {
            $diagnosis = $this->diagnosisRepository->findById($id);

            if (!$diagnosis) {
                return [
                    'success' => false,
                    'data' => null,
                    'message' => 'Diagnosis not found',
                    'error' => 'Diagnosis not found',
                ];
            }

            $updated = $this->diagnosisRepository->updateVerificationStatus($diagnosis, 'disputed');

            if (!$updated) {
                throw new \Exception('Failed to dispute diagnosis');
            }

            // Add dispute reason if provided
            if ($reason) {
                $diagnosis->update(['dispute_reason' => $reason]);
            }

            DB::commit();

            Log::info('Diagnosis marked as disputed', [
                'diagnosis_id' => $id,
                'reason' => $reason,
            ]);

            return [
                'success' => true,
                'data' => $diagnosis->fresh(),
                'message' => 'Diagnosis marked as disputed',
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to dispute diagnosis', [
                'error' => $e->getMessage(),
                'id' => $id,
            ]);

            return [
                'success' => false,
                'data' => null,
                'message' => 'Failed to dispute diagnosis',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function resolveDiagnosis(int $id, ?string $resolutionNotes = null): array
    {
        DB::beginTransaction();

        try {
            $diagnosis = $this->diagnosisRepository->findById($id);

            if (!$diagnosis) {
                return [
                    'success' => false,
                    'data' => null,
                    'message' => 'Diagnosis not found',
                    'error' => 'Diagnosis not found',
                ];
            }

            if ($diagnosis->isResolved()) {
                return [
                    'success' => false,
                    'data' => null,
                    'message' => 'Diagnosis is already resolved',
                    'error' => 'Already resolved',
                ];
            }

            $resolved = $diagnosis->resolve($resolutionNotes);

            if (!$resolved) {
                throw new \Exception('Failed to resolve diagnosis');
            }

            DB::commit();

            Log::info('Diagnosis resolved successfully', [
                'diagnosis_id' => $id,
            ]);

            return [
                'success' => true,
                'data' => $diagnosis->fresh(),
                'message' => 'Diagnosis resolved successfully',
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to resolve diagnosis', [
                'error' => $e->getMessage(),
                'id' => $id,
            ]);

            return [
                'success' => false,
                'data' => null,
                'message' => 'Failed to resolve diagnosis',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function reactivateDiagnosis(int $id): array
    {
        DB::beginTransaction();

        try {
            $diagnosis = $this->diagnosisRepository->findById($id);

            if (!$diagnosis) {
                return [
                    'success' => false,
                    'data' => null,
                    'message' => 'Diagnosis not found',
                    'error' => 'Diagnosis not found',
                ];
            }

            if (!$diagnosis->isResolved()) {
                return [
                    'success' => false,
                    'data' => null,
                    'message' => 'Only resolved diagnoses can be reactivated',
                    'error' => 'Not resolved',
                ];
            }

            $reactivated = $diagnosis->reactivate();

            if (!$reactivated) {
                throw new \Exception('Failed to reactivate diagnosis');
            }

            DB::commit();

            Log::info('Diagnosis reactivated successfully', [
                'diagnosis_id' => $id,
            ]);

            return [
                'success' => true,
                'data' => $diagnosis->fresh(),
                'message' => 'Diagnosis reactivated successfully',
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to reactivate diagnosis', [
                'error' => $e->getMessage(),
                'id' => $id,
            ]);

            return [
                'success' => false,
                'data' => null,
                'message' => 'Failed to reactivate diagnosis',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getPatientDiagnosisStatistics(int $patientId): array
    {
        try {
            $countByType = $this->diagnosisRepository->getCountByType($patientId);
            $activeDiagnoses = $this->diagnosisRepository->getActiveByPatient($patientId);

            return [
                'success' => true,
                'data' => [
                    'count_by_type' => $countByType,
                    'active_count' => $activeDiagnoses->count(),
                    'active_diagnoses' => $activeDiagnoses->map(function ($d) {
                        return [
                            'id' => $d->id,
                            'code' => $d->diagnosis_code,
                            'description' => $d->diagnosis_description,
                            'type' => $d->diagnosis_type,
                            'certainty' => $d->certainty,
                        ];
                    }),
                ],
                'message' => 'Statistics retrieved successfully',
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get diagnosis statistics', [
                'error' => $e->getMessage(),
                'patient_id' => $patientId,
            ]);

            return [
                'success' => false,
                'data' => [],
                'message' => 'Failed to retrieve statistics',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getMostCommonDiagnoses(int $facilityId, int $limit = 10): array
    {
        try {
            $diagnoses = $this->diagnosisRepository->getMostCommonDiagnoses($facilityId, $limit);

            return [
                'success' => true,
                'data' => $diagnoses,
                'message' => 'Most common diagnoses retrieved successfully',
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get most common diagnoses', [
                'error' => $e->getMessage(),
                'facility_id' => $facilityId,
            ]);

            return [
                'success' => false,
                'data' => [],
                'message' => 'Failed to retrieve most common diagnoses',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function searchDiagnoses(string $searchTerm, ?int $facilityId = null, int $limit = 20): array
    {
        try {
            $diagnoses = $this->diagnosisRepository->searchDiagnoses($searchTerm, $facilityId, $limit);

            return [
                'success' => true,
                'data' => $diagnoses,
                'message' => 'Search completed successfully',
            ];
        } catch (\Exception $e) {
            Log::error('Failed to search diagnoses', [
                'error' => $e->getMessage(),
                'search_term' => $searchTerm,
            ]);

            return [
                'success' => false,
                'data' => [],
                'message' => 'Failed to search diagnoses',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function suggestIcdCodes(string $searchTerm, int $limit = 10): array
    {
        // This would typically query an ICD database or external service
        // For now, return a placeholder response
        try {
            $diagnoses = $this->diagnosisRepository->searchDiagnoses($searchTerm, null, $limit);

            return [
                'success' => true,
                'data' => $diagnoses->map(function ($d) {
                    return [
                        'code' => $d->diagnosis_code,
                        'description' => $d->diagnosis_description,
                    ];
                }),
                'message' => 'ICD code suggestions retrieved',
            ];
        } catch (\Exception $e) {
            Log::error('Failed to suggest ICD codes', [
                'error' => $e->getMessage(),
                'search_term' => $searchTerm,
            ]);

            return [
                'success' => false,
                'data' => [],
                'message' => 'Failed to get suggestions',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function isDiagnosisUniqueForVisit(int $visitId, string $diagnosisCode, string $diagnosisType, ?int $excludeId = null): bool
    {
        $query = Diagnosis::where('visit_id', $visitId)
            ->where('diagnosis_code', $diagnosisCode)
            ->where('diagnosis_type', $diagnosisType);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return !$query->exists();
    }

    /**
     * Validate diagnosis data.
     *
     * @param array $data
     * @param Diagnosis|null $diagnosis
     * @return array
     * @throws ValidationException
     */
    private function validateDiagnosisData(array $data, ?Diagnosis $diagnosis = null): array
    {
        $rules = [
            'facility_id' => 'required|exists:facilities,id',
            'visit_id' => 'required|exists:visits,id',
            'patient_id' => 'required|exists:patients,id',
            'diagnosis_code' => 'required|string|max:50',
            'diagnosis_description' => 'required|string|max:500',
            'diagnosis_type' => 'nullable|in:primary,secondary,differential,admitting,discharge,provisional',
            'certainty' => 'nullable|in:confirmed,probable,possible,rule_out,suspected,uncertain',
            'clinical_status' => 'nullable|in:active,inactive,resolved,remission,chronic',
            'clinical_notes' => 'nullable|string',
            'onset_date' => 'nullable|date',
            'abatement_date' => 'nullable|date|after_or_equal:onset_date',
            'supporting_evidence' => 'nullable|array',
            'diagnostic_criteria_met' => 'nullable|string',
            'custom_fields' => 'nullable|array',
            'coding_metadata' => 'nullable|array',
        ];

        // For updates, make fields sometimes required
        if ($diagnosis) {
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