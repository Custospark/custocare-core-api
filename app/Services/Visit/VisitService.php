<?php

namespace App\Services\Visit;

use App\Models\Patient;
use App\Models\Staff;
use App\Models\Visit;
use App\Repositories\Contracts\VisitRepositoryInterface;
use App\Services\Contracts\VisitServiceInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Auth as FacadesAuth;
use Illuminate\Support\Str;

/**
 * Visit Service Implementation
 *
 * Contains all business logic for Visit operations
 */
class VisitService implements VisitServiceInterface
{
    /**
     * Visit repository instance
     *
     * @var VisitRepositoryInterface
     */
    protected $visitRepository;

    /**
     * Valid visit types
     *
     * @var array
     */
    protected $validVisitTypes = [
        'outpatient',
        'inpatient',
        'emergency',
        'urgent_care',
        'virtual_telehealth',
        'home_health',
        'observation',
        'day_surgery',
        'consultation',
        'followup',
        'preventive_wellness',
    ];

    /**
     * Valid visit statuses
     *
     * @var array
     */
    protected $validStatuses = [
        'active',
        'completed',
        'cancelled',
        'no_show',
        'in_progress',
    ];

    /**
     * Valid visit phases
     *
     * @var array
     */
    protected $validPhases = [
        'registration',
        'waiting_triage',
        'triage',
        'waiting_provider',
        'consultation',
        'diagnostic_tests',
        'awaiting_results',
        'treatment',
        'procedures',
        'observation',
        'admission_pending',
        'billing',
        'discharge_pending',
        'discharged',
        'left_without_being_seen',
        'left_against_medical_advice',
        'transferred',
        'admitted',
        'expired',
    ];

    /**
     * Constructor
     *
     * @param VisitRepositoryInterface $visitRepository
     */
    public function __construct(VisitRepositoryInterface $visitRepository)
    {
        $this->visitRepository = $visitRepository;
    }

    /**
     * {@inheritDoc}
     */
    public function getAllVisits(array $filters = [], int $perPage = 15): array
    {
        try {
            $visits = $this->visitRepository->getAllPaginated($perPage, $filters);

            return [
                'success' => true,
                'data' => $visits,
                'message' => 'Visits retrieved successfully.',
            ];
        } catch (\Exception $e) {
            Log::error('Failed to retrieve visits', [
                'filters' => $filters,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to retrieve visits. Please try again later.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ];
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getVisitByUuid(string $uuid): array
    {
        try {
            $visit = $this->visitRepository->findByUuid($uuid);

            if (!$visit) {
                return [
                    'success' => false,
                    'message' => 'Visit not found.',
                    'errors' => ['uuid' => 'The specified visit does not exist.'],
                ];
            }

            return [
                'success' => true,
                'data' => $visit,
                'message' => 'Visit retrieved successfully.',
            ];
        } catch (\Exception $e) {
            Log::error('Failed to retrieve visit by UUID', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to retrieve visit. Please try again later.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ];
        }
    }

    
   

    /**
     * {@inheritDoc}
     */
    public function getVisitsByFacility(int $facilityId, array $filters = [], int $perPage = 15): array
    {
        try {
            // Validate facility ID
            if ($facilityId <= 0) {
                return [
                    'success' => false,
                    'message' => 'Invalid facility ID.',
                    'errors' => ['facility_id' => 'The facility ID must be a positive integer.'],
                ];
            }

            $visits = $this->visitRepository->getByFacility($facilityId, $perPage, $filters);

            return [
                'success' => true,
                'data' => $visits,
                'message' => 'Facility visits retrieved successfully.',
            ];
        } catch (\Exception $e) {
            Log::error('Failed to retrieve visits by facility', [
                'facility_id' => $facilityId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to retrieve facility visits. Please try again later.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ];
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getVisitsByPatient(int $patientId, array $filters = [], int $perPage = 15): array
    {
        try {
            // Validate patient ID
            if ($patientId <= 0) {
                return [
                    'success' => false,
                    'message' => 'Invalid patient ID.',
                    'errors' => ['patient_id' => 'The patient ID must be a positive integer.'],
                ];
            }

            $visits = $this->visitRepository->getByPatient($patientId, $perPage, $filters);

            return [
                'success' => true,
                'data' => $visits,
                'message' => 'Patient visits retrieved successfully.',
            ];
        } catch (\Exception $e) {
            Log::error('Failed to retrieve visits by patient', [
                'patient_id' => $patientId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to retrieve patient visits. Please try again later.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ];
        }
    }

    /**
     * {@inheritDoc}
     */
   /**
 * Create a new visit or return existing active visit for a patient
 * 
 * @param array $data Visit data (must include facility_id, patient_id or user_id)
 * @param int $staffId The authenticated user ID (will be used to find staff record)
 * @return array
 */
public function createVisit(array $data, int $staffId): array
{
    try {
        DB::beginTransaction();

        // Find patient - either by patient_id or user_id
        $patient = null;
        if (isset($data['patient_id'])) {
            $patient = Patient::find($data['patient_id']);
        } elseif (isset($data['user_id'])) {
            $patient = Patient::where('user_id', $data['user_id'])->first();
        }

        if (!$patient) {
            DB::rollBack();
            return [
                'success' => false,
                'message' => 'Patient not found.',
            ];
        }

        // Check for existing active or in-progress visit for this patient at this facility
        $existingVisit = Visit::where('patient_id', $patient->id)
            ->where('facility_id', $data['facility_id'])
            ->whereIn('status', ['active', 'in_progress'])
            ->whereNull('deleted_at')
            ->orderBy('created_at', 'desc')
            ->first();

        // If an active visit exists, return it
        if ($existingVisit) {
            DB::commit();
            
            Log::info('Existing active visit found', [
                'visit_id' => $existingVisit->id,
                'visit_uuid' => $existingVisit->visit_uuid,
                'patient_id' => $patient->id,
                'facility_id' => $data['facility_id'],
                'staff_id' => $staffId,
            ]);

            return [
                'success' => true,
                'data' => $existingVisit,
                'message' => 'Existing active visit found.',
                'is_existing' => true,
            ];
        }

        // No existing active visit - create new one
        // Set required fields
        $data['patient_id'] = $patient->id;
        $data['created_by_staff_id'] = $staffId;
        $data['updated_by_staff_id'] = $staffId;
        $data['arrived_at'] = $data['arrived_at'] ?? now();
        $data['current_phase'] = $data['current_phase'] ?? 'registration';
        $data['status'] = $data['status'] ?? 'active';
        $data['visit_uuid'] = $data['visit_uuid'] ?? (string) Str::uuid();

        // Create the visit
        $visit = Visit::create($data);

        DB::commit();

        Log::info('New visit created', [
            'visit_id' => $visit->id,
            'visit_uuid' => $visit->visit_uuid,
            'patient_id' => $patient->id,
            'facility_id' => $data['facility_id'],
            'staff_id' => $staffId,
        ]);

        return [
            'success' => true,
            'data' => $visit,
            'message' => 'Visit created successfully.',
            'is_existing' => false,
        ];

    } catch (\Exception $e) {
        DB::rollBack();

        Log::error('Failed to create visit', [
            'data' => $data,
            'staff_id' => $staffId,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        return [
            'success' => false,
            'message' => 'Failed to create visit. Please try again later.',
            'error' => config('app.debug') ? $e->getMessage() : null,
        ];
    }
}

    /**
     * {@inheritDoc}
     */
    public function updateVisit(string $uuid, array $data, int $staffId): array
    {
        try {
            DB::beginTransaction();

            if (array_key_exists('chief_complaints', $data)) {
            $incoming = $data['chief_complaints'];

            // Make it an array
            $incoming = is_string($incoming) ? json_decode($incoming, true) : $incoming;
            $incoming = is_array($incoming) ? $incoming : [];

            // Flatten: split any string containing newlines into multiple items
            $split = [];
            foreach ($incoming as $item) {
                $item = (string) $item;

                // split on \r\n or \n
                $parts = preg_split("/\r\n|\n/", $item);

                foreach ($parts as $p) {
                    $p = trim($p);
                    if ($p !== '') $split[] = $p;
                }
            }

            // Dedup (case-insensitive)
            $normalize = fn($v) => mb_strtolower(trim((string)$v));
            $splitNorm = [];
            $unique = [];

            foreach ($split as $v) {
                $k = $normalize($v);
                if (!isset($splitNorm[$k])) {
                    $splitNorm[$k] = true;
                    $unique[] = $v;
                }
            }

            $data['chief_complaints'] = $unique;
        }


            // Find the visit
            $visit = $this->visitRepository->findByUuid($uuid);
            if (!$visit) {
                return [
                    'success' => false,
                    'message' => 'Visit not found.',
                    'errors' => ['uuid' => 'The specified visit does not exist.'],
                ];
            }

            // Validate update data
            $validationResult = $this->validateVisitData($data, 'update', $visit);
            if (!$validationResult['success']) {
                return $validationResult;
            }

            // Prevent updates to completed or cancelled visits
            if (in_array($visit->status, ['completed', 'cancelled'])) {
                return [
                    'success' => false,
                    'message' => 'Cannot update a completed or cancelled visit.',
                    'errors' => ['status' => 'Visit is already ' . $visit->status . '.'],
                ];
            }

            // Set audit field
            $data['updated_by_staff_id'] = $staffId;

            // Update the visit
            $updatedVisit = $this->visitRepository->update($visit, $data);

            DB::commit();

            Log::info('Visit updated successfully', [
                'visit_id' => $visit->id,
                'visit_uuid' => $uuid,
                'user_id' => $staffId,
            ]);

            return [
                'success' => true,
                'data' => $updatedVisit,
                'message' => 'Visit updated successfully.',
            ];
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to update visit', [
                'uuid' => $uuid,
                'data' => $data,
                'user_id' => $staffId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to update visit. Please try again later.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ];
        }
    }

    /**
     * {@inheritDoc}
     */
    public function deleteVisit(string $uuid, int $userId): array
    {
        try {
            DB::beginTransaction();

            // Find the visit
            $visit = $this->visitRepository->findByUuid($uuid);
            if (!$visit) {
                return [
                    'success' => false,
                    'message' => 'Visit not found.',
                    'errors' => ['uuid' => 'The specified visit does not exist.'],
                ];
            }

            // Check if visit can be deleted
            if ($visit->isActive()) {
                return [
                    'success' => false,
                    'message' => 'Cannot delete an active visit. Please cancel it first.',
                    'errors' => ['status' => 'Visit is currently active.'],
                ];
            }

            // Perform soft delete
            $deleted = $this->visitRepository->delete($visit);

            if (!$deleted) {
                return [
                    'success' => false,
                    'message' => 'Failed to delete visit.',
                ];
            }

            DB::commit();

            Log::info('Visit deleted successfully', [
                'visit_id' => $visit->id,
                'visit_uuid' => $uuid,
                'user_id' => $userId,
            ]);

            return [
                'success' => true,
                'message' => 'Visit deleted successfully.',
            ];
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to delete visit', [
                'uuid' => $uuid,
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to delete visit. Please try again later.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ];
        }
    }

    /**
     * {@inheritDoc}
     */
    public function restoreVisit(string $uuid, int $userId): array
    {
        try {
            DB::beginTransaction();

            // Find the visit (including trashed)
            $visit = Visit::withTrashed()->where('visit_uuid', $uuid)->first();
            if (!$visit) {
                return [
                    'success' => false,
                    'message' => 'Visit not found.',
                    'errors' => ['uuid' => 'The specified visit does not exist.'],
                ];
            }

            // Check if already restored
            if (!$visit->trashed()) {
                return [
                    'success' => false,
                    'message' => 'Visit is not deleted.',
                    'errors' => ['status' => 'Visit is already active.'],
                ];
            }

            // Restore the visit
            $restored = $this->visitRepository->restore($visit);

            if (!$restored) {
                return [
                    'success' => false,
                    'message' => 'Failed to restore visit.',
                ];
            }

            DB::commit();

            Log::info('Visit restored successfully', [
                'visit_id' => $visit->id,
                'visit_uuid' => $uuid,
                'user_id' => $userId,
            ]);

            return [
                'success' => true,
                'data' => $visit,
                'message' => 'Visit restored successfully.',
            ];
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to restore visit', [
                'uuid' => $uuid,
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to restore visit. Please try again later.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ];
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getActiveVisitsByDepartment(int $departmentId): array
    {
        try {
            // Validate department ID
            if ($departmentId <= 0) {
                return [
                    'success' => false,
                    'message' => 'Invalid department ID.',
                    'errors' => ['department_id' => 'The department ID must be a positive integer.'],
                ];
            }

            $visits = $this->visitRepository->getActiveVisitsByDepartment($departmentId);

            return [
                'success' => true,
                'data' => $visits,
                'message' => 'Active department visits retrieved successfully.',
            ];
        } catch (\Exception $e) {
            Log::error('Failed to retrieve active visits by department', [
                'department_id' => $departmentId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to retrieve active department visits. Please try again later.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ];
        }
    }

    /**
     * {@inheritDoc}
     */
    public function updateVisitPhase(string $uuid, string $phase, array $additionalData = [], ?int $userId = null): array
    {
        try {
            DB::beginTransaction();

            // Find the visit
            $visit = $this->visitRepository->findByUuid($uuid);
            if (!$visit) {
                return [
                    'success' => false,
                    'message' => 'Visit not found.',
                    'errors' => ['uuid' => 'The specified visit does not exist.'],
                ];
            }

            // Validate phase
            if (!in_array($phase, $this->validPhases)) {
                return [
                    'success' => false,
                    'message' => 'Invalid visit phase.',
                    'errors' => ['phase' => 'The specified phase is not valid.'],
                ];
            }

            // Check if visit is active
            if (!$visit->isActive()) {
                return [
                    'success' => false,
                    'message' => 'Cannot update phase of a non-active visit.',
                    'errors' => ['status' => 'Visit is ' . $visit->status . '.'],
                ];
            }

            // Set audit field if user provided
            if ($userId) {
                $additionalData['updated_by_staff_id'] = $userId;
            }

            // Handle phase-specific logic
            if ($phase === 'consultation' && !$visit->clinical_care_started_at) {
                $additionalData['clinical_care_started_at'] = now();
            }

            // Update phase
            $updatedVisit = $this->visitRepository->updatePhase($visit, $phase, $additionalData);

            DB::commit();

            Log::info('Visit phase updated', [
                'visit_id' => $visit->id,
                'visit_uuid' => $uuid,
                'old_phase' => $visit->current_phase,
                'new_phase' => $phase,
                'user_id' => $userId,
            ]);

            return [
                'success' => true,
                'data' => $updatedVisit,
                'message' => 'Visit phase updated successfully.',
            ];
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to update visit phase', [
                'uuid' => $uuid,
                'phase' => $phase,
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to update visit phase. Please try again later.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ];
        }
    }

    /**
     * {@inheritDoc}
     */
    public function updateVisitStatus(string $uuid, string $status, array $additionalData = [], ?int $userId = null): array
    {
        try {
            DB::beginTransaction();

            // Find the visit
            $visit = $this->visitRepository->findByUuid($uuid);
            if (!$visit) {
                return [
                    'success' => false,
                    'message' => 'Visit not found.',
                    'errors' => ['uuid' => 'The specified visit does not exist.'],
                ];
            }

            // Validate status
            if (!in_array($status, $this->validStatuses)) {
                return [
                    'success' => false,
                    'message' => 'Invalid visit status.',
                    'errors' => ['status' => 'The specified status is not valid.'],
                ];
            }

            // Check if status transition is allowed
            if (!$this->isStatusTransitionAllowed($visit->status, $status)) {
                return [
                    'success' => false,
                    'message' => 'Status transition not allowed.',
                    'errors' => ['status' => 'Cannot transition from ' . $visit->status . ' to ' . $status . '.'],
                ];
            }

            // Set audit field if user provided
            if ($userId) {
                $additionalData['updated_by_staff_id'] = $userId;
            }

            // Update status
            $updatedVisit = $this->visitRepository->updateStatus($visit, $status, $additionalData);

            DB::commit();

            Log::info('Visit status updated', [
                'visit_id' => $visit->id,
                'visit_uuid' => $uuid,
                'old_status' => $visit->status,
                'new_status' => $status,
                'user_id' => $userId,
            ]);

            return [
                'success' => true,
                'data' => $updatedVisit,
                'message' => 'Visit status updated successfully.',
            ];
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to update visit status', [
                'uuid' => $uuid,
                'status' => $status,
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to update visit status. Please try again later.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ];
        }
    }

    /**
     * {@inheritDoc}
     */
    public function dischargeVisit(string $uuid, array $dischargeData, int $userId): array
    {
        try {
            DB::beginTransaction();

            // Find the visit
            $visit = $this->visitRepository->findByUuid($uuid);
            if (!$visit) {
                return [
                    'success' => false,
                    'message' => 'Visit not found.',
                    'errors' => ['uuid' => 'The specified visit does not exist.'],
                ];
            }

            // Check if visit can be discharged
            if (!$visit->isActive()) {
                return [
                    'success' => false,
                    'message' => 'Cannot discharge a non-active visit.',
                    'errors' => ['status' => 'Visit is ' . $visit->status . '.'],
                ];
            }

            // Validate discharge data
            $validationResult = $this->validateDischargeData($dischargeData);
            if (!$validationResult['success']) {
                return $validationResult;
            }

            // Set discharge by
            $dischargeData['discharged_by_staff_id'] = $userId;
            $dischargeData['updated_by_staff_id'] = $userId;

            // Discharge the visit
            $dischargedVisit = $this->visitRepository->discharge($visit, $dischargeData);

            DB::commit();

            Log::info('Visit discharged', [
                'visit_id' => $visit->id,
                'visit_uuid' => $uuid,
                'user_id' => $userId,
                'discharge_disposition' => $dischargeData['discharge_disposition'] ?? null,
            ]);

            return [
                'success' => true,
                'data' => $dischargedVisit,
                'message' => 'Visit discharged successfully.',
            ];
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to discharge visit', [
                'uuid' => $uuid,
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to discharge visit. Please try again later.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ];
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getLongWaitingVisits(int $minutesThreshold, ?int $facilityId = null): array
    {
        try {
            // Validate threshold
            if ($minutesThreshold <= 0) {
                return [
                    'success' => false,
                    'message' => 'Invalid threshold value.',
                    'errors' => ['minutes_threshold' => 'Threshold must be greater than 0.'],
                ];
            }

            $visits = $this->visitRepository->getLongWaitingVisits($minutesThreshold, $facilityId);

            return [
                'success' => true,
                'data' => $visits,
                'message' => 'Long waiting visits retrieved successfully.',
            ];
        } catch (\Exception $e) {
            Log::error('Failed to retrieve long waiting visits', [
                'threshold' => $minutesThreshold,
                'facility_id' => $facilityId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to retrieve long waiting visits. Please try again later.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ];
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getVisitStatistics(?int $facilityId = null, ?string $dateRange = null): array
    {
        try {
            // Calculate date range if not provided
            if (!$dateRange) {
                $startDate = now()->startOfDay();
                $endDate = now()->endOfDay();
            } else {
                // Parse date range (simplified - in production would parse various formats)
                $startDate = now()->sub($dateRange)->startOfDay();
                $endDate = now()->endOfDay();
            }

            $query = Visit::query();

            if ($facilityId) {
                $query->where('facility_id', $facilityId);
            }

            if ($dateRange) {
                $query->whereBetween('arrived_at', [$startDate, $endDate]);
            }

            $totalVisits = $query->count();
            $activeVisits = $query->clone()->where('status', 'active')->count();
            $completedVisits = $query->clone()->where('status', 'completed')->count();
            $cancelledVisits = $query->clone()->where('status', 'cancelled')->count();

            // Average waiting time for completed visits
            $avgWaitingTime = $query->clone()
                ->where('status', 'completed')
                ->whereNotNull('waiting_since')
                ->whereNotNull('clinical_care_started_at')
                ->avg(DB::raw('TIMESTAMPDIFF(MINUTE, waiting_since, clinical_care_started_at)'));

            // Visit type distribution
            $visitTypeDistribution = $query->clone()
                ->select('visit_type', DB::raw('count(*) as count'))
                ->groupBy('visit_type')
                ->get()
                ->pluck('count', 'visit_type')
                ->toArray();

            // Phase distribution for active visits
            $phaseDistribution = $query->clone()
                ->where('status', 'active')
                ->select('current_phase', DB::raw('count(*) as count'))
                ->groupBy('current_phase')
                ->get()
                ->pluck('count', 'current_phase')
                ->toArray();

            $statistics = [
                'total' => $totalVisits,
                'active' => $activeVisits,
                'completed' => $completedVisits,
                'cancelled' => $cancelledVisits,
                'avg_waiting_time_minutes' => round($avgWaitingTime ?? 0, 2),
                'visit_type_distribution' => $visitTypeDistribution,
                'phase_distribution' => $phaseDistribution,
                'date_range' => [
                    'start' => $startDate->toDateTimeString(),
                    'end' => $endDate->toDateTimeString(),
                ],
            ];

            return [
                'success' => true,
                'data' => $statistics,
                'message' => 'Visit statistics retrieved successfully.',
            ];
        } catch (\Exception $e) {
            Log::error('Failed to retrieve visit statistics', [
                'facility_id' => $facilityId,
                'date_range' => $dateRange,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to retrieve visit statistics. Please try again later.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ];
        }
    }

    /**
     * {@inheritDoc}
     */
    public function startClinicalCare(string $uuid, int $userId): array
    {
        return $this->updateVisitPhase($uuid, 'consultation', [
            'clinical_care_started_at' => now(),
            'updated_by_staff_id' => $userId,
        ], $userId);
    }

    /**
     * {@inheritDoc}
     */
    public function endClinicalCare(string $uuid, int $userId): array
    {
        return $this->updateVisitPhase($uuid, 'treatment', [
            'clinical_care_ended_at' => now(),
            'updated_by_staff_id' => $userId,
        ], $userId);
    }

    /**
     * {@inheritDoc}
     */
    public function cancelVisit(string $uuid, string $reason, int $userId): array
    {
        return $this->updateVisitStatus($uuid, 'cancelled', [
            'cancellation_reason' => $reason,
            'cancelled_at' => now(),
            'updated_by_staff_id' => $userId,
        ], $userId);
    }

    /**
     * {@inheritDoc}
     */
    public function registerVisit(string $uuid, array $registrationData, int $userId): array
    {
        return $this->updateVisit($uuid, array_merge($registrationData, [
            'registered_at' => $registrationData['registered_at'] ?? now(),
            'updated_by_staff_id' => $userId,
        ]), $userId);
    }

    /**
     * Validate visit data for create/update operations
     *
     * @param array $data
     * @param string $operation
     * @param Visit|null $existingVisit
     * @return array
     */
    private function validateVisitData(array $data, string $operation = 'create', ?Visit $existingVisit = null): array
    {
        $errors = [];

        // Required fields for create
        if ($operation === 'create') {
            $requiredFields = ['facility_id', 'patient_id', 'visit_type', 'arrived_at'];
            foreach ($requiredFields as $field) {
                if (empty($data[$field])) {
                    $errors[$field] = "The $field field is required.";
                }
            }
        }

        // Validate facility_id
        if (isset($data['facility_id']) && $data['facility_id'] <= 0) {
            $errors['facility_id'] = 'The facility ID must be a positive integer.';
        }

        // Validate patient_id
        if (isset($data['patient_id']) && $data['patient_id'] <= 0) {
            $errors['patient_id'] = 'The patient ID must be a positive integer.';
        }

        // Validate visit_type
        if (isset($data['visit_type']) && !in_array($data['visit_type'], $this->validVisitTypes)) {
            $errors['visit_type'] = 'The specified visit type is not valid.';
        }

        // Validate acuity_score
        if (isset($data['acuity_score']) && ($data['acuity_score'] < 1 || $data['acuity_score'] > 5)) {
            $errors['acuity_score'] = 'Acuity score must be between 1 and 5.';
        }

        // Validate arrived_at
        if (isset($data['arrived_at'])) {
            try {
                $arrivedAt = \Carbon\Carbon::parse($data['arrived_at']);
                if ($arrivedAt->isFuture()) {
                    $errors['arrived_at'] = 'Arrival time cannot be in the future.';
                }
            } catch (\Exception $e) {
                $errors['arrived_at'] = 'Invalid arrival time format.';
            }
        }

        // Validate scheduled_time if provided
        if (isset($data['scheduled_time'])) {
            try {
                $scheduledTime = \Carbon\Carbon::parse($data['scheduled_time']);
                if ($scheduledTime->isFuture() && isset($data['arrived_at'])) {
                    $arrivedAt = \Carbon\Carbon::parse($data['arrived_at']);
                    if ($arrivedAt->gt($scheduledTime)) {
                        $errors['scheduled_time'] = 'Scheduled time cannot be before arrival time.';
                    }
                }
            } catch (\Exception $e) {
                $errors['scheduled_time'] = 'Invalid scheduled time format.';
            }
        }

        // Validate JSON fields
        $jsonFields = ['chief_complaints', 'symptoms_on_arrival', 'vital_signs_summary', 'diagnosis_codes', 'procedure_codes', 'medications_administered', 'safety_alerts', 'metadata'];
        foreach ($jsonFields as $field) {
            if (isset($data[$field])) {
                if (!is_array($data[$field]) && !$this->isValidJsonString($data[$field])) {
                    $errors[$field] = "The $field field must be a valid JSON array.";
                }
            }
        }

        // Validate numeric fields
        $numericFields = ['estimated_total_charges', 'patient_estimated_responsibility'];
        foreach ($numericFields as $field) {
            if (isset($data[$field]) && !is_numeric($data[$field])) {
                $errors[$field] = "The $field field must be a number.";
            }
        }

        if (!empty($errors)) {
            return [
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $errors,
            ];
        }

        return ['success' => true];
    }

    /**
     * Validate discharge data
     *
     * @param array $data
     * @return array
     */
    private function validateDischargeData(array $data): array
    {
        $errors = [];

        // Validate discharge_disposition
        $validDispositions = [
            'home',
            'admitted_to_hospital',
            'transferred_to_facility',
            'left_ama',
            'left_without_seen',
            'expired',
            'hospice',
            'skilled_nursing_facility',
            'rehabilitation_facility',
            'psychiatric_facility',
            'law_enforcement_custody',
        ];

        if (empty($data['discharge_disposition'])) {
            $errors['discharge_disposition'] = 'The discharge disposition field is required.';
        } elseif (!in_array($data['discharge_disposition'], $validDispositions)) {
            $errors['discharge_disposition'] = 'The specified discharge disposition is not valid.';
        }

        // Validate discharged_by_staff_id if provided
        if (isset($data['discharged_by_staff_id']) && $data['discharged_by_staff_id'] <= 0) {
            $errors['discharged_by_staff_id'] = 'The discharged by staff ID must be a positive integer.';
        }

        // Validate followup_scheduled_at if provided
        if (isset($data['followup_scheduled_at'])) {
            try {
                $followupTime = \Carbon\Carbon::parse($data['followup_scheduled_at']);
                if ($followupTime->isPast()) {
                    $errors['followup_scheduled_at'] = 'Follow-up scheduled time cannot be in the past.';
                }
            } catch (\Exception $e) {
                $errors['followup_scheduled_at'] = 'Invalid follow-up scheduled time format.';
            }
        }

        // Validate discharge_medications if provided
        if (isset($data['discharge_medications']) && !is_array($data['discharge_medications'])) {
            $errors['discharge_medications'] = 'The discharge medications field must be a valid JSON array.';
        }

        if (!empty($errors)) {
            return [
                'success' => false,
                'message' => 'Discharge validation failed.',
                'errors' => $errors,
            ];
        }

        return ['success' => true];
    }

    /**
     * Check if status transition is allowed
     *
     * @param string $currentStatus
     * @param string $newStatus
     * @return bool
     */
    private function isStatusTransitionAllowed(string $currentStatus, string $newStatus): bool
    {
        $allowedTransitions = [
            'active' => ['completed', 'cancelled', 'no_show', 'in_progress'],
            'in_progress' => ['active', 'completed', 'cancelled'],
            'completed' => [], // Cannot transition from completed
            'cancelled' => [], // Cannot transition from cancelled
            'no_show' => ['active'], // Can reactivate a no-show
        ];

        return in_array($newStatus, $allowedTransitions[$currentStatus] ?? []);
    }

    /**
     * Check if a string is valid JSON
     *
     * @param mixed $string
     * @return bool
     */
    private function isValidJsonString($string): bool
    {
        if (!is_string($string)) {
            return false;
        }

        json_decode($string);
        return json_last_error() === JSON_ERROR_NONE;
    }
}