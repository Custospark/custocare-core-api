<?php

namespace App\Services\FacilityStaffRole;

use App\Models\FacilityStaffRole;
use App\Repositories\Contracts\FacilityStaffRoleRepositoryInterface;
use App\Services\Contracts\FacilityStaffRoleServiceInterface;
use App\Support\HealthcareIdGenerator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;


class FacilityStaffRoleService implements FacilityStaffRoleServiceInterface
{
    /**
     * Repository instance
     *
     * @var FacilityStaffRoleRepositoryInterface
     */
    protected $repository;

    /**
     * Constructor
     *
     * @param FacilityStaffRoleRepositoryInterface $repository
     */
    public function __construct(FacilityStaffRoleRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Get all role assignments with optional filters
     */
    public function getAllAssignments(array $filters = []): Collection
    {
        try {
            return $this->repository->all($filters);
        } catch (\Exception $e) {
            Log::error('Service: Failed to get all assignments', [
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);
            
            // Return empty collection instead of throwing
            return new Collection();
        }
    }

    /**
     * Get paginated role assignments
     */
    public function getPaginatedAssignments(int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        try {
            return $this->repository->paginate($perPage, $filters);
        } catch (\Exception $e) {
            Log::error('Service: Failed to get paginated assignments', [
                'per_page' => $perPage,
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);
            
            // Return empty paginator with proper structure
            return new LengthAwarePaginator([], 0, $perPage);
        }
    }

    /**
     * Get role assignment by ID
     */
    public function getAssignmentById(int $id): array
    {
        try {
            $assignment = $this->repository->findById($id);
            
            if (!$assignment) {
                return [
                    'success' => false,
                    'message' => 'Role assignment not found',
                    'data' => null
                ];
            }
            
            return [
                'success' => true,
                'message' => 'Role assignment retrieved successfully',
                'data' => $assignment
            ];
        } catch (\Exception $e) {
            Log::error('Service: Failed to get assignment by ID', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to retrieve role assignment',
                'data' => null
            ];
        }
    }

    /**
     * Get role assignment by UUID
     */
    public function getAssignmentByUuid(string $uuid): array
    {
        try {
            $assignment = $this->repository->findByUuid($uuid);
            
            if (!$assignment) {
                return [
                    'success' => false,
                    'message' => 'Role assignment not found',
                    'data' => null
                ];
            }
            
            return [
                'success' => true,
                'message' => 'Role assignment retrieved successfully',
                'data' => $assignment
            ];
        } catch (\Exception $e) {
            Log::error('Service: Failed to get assignment by UUID', [
                'uuid' => $uuid,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to retrieve role assignment',
                'data' => null
            ];
        }
    }

    /**
     * Get assignments for a specific facility
     */
    public function getAssignmentsByFacility(int $facilityId, array $filters = []): Collection
    {
        try {
            return $this->repository->findByFacility($facilityId, $filters);
        } catch (\Exception $e) {
            Log::error('Service: Failed to get assignments by facility', [
                'facility_id' => $facilityId,
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);
            
            return new Collection();
        }
    }

    /**
     * Get assignments for a specific staff member
     */
    public function getAssignmentsByStaff(int $staffId, array $filters = []): Collection
    {
        try {
            return $this->repository->findByStaff($staffId, $filters);
        } catch (\Exception $e) {
            Log::error('Service: Failed to get assignments by staff', [
                'staff_id' => $staffId,
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);
            
            return new Collection();
        }
    }

    /**
     * Create a new role assignment
     */
    public function createAssignment(array $data): ?FacilityStaffRole
    {
        // Ensure UUID
        $data['assignment_uuid'] ??= HealthcareIdGenerator::generateRandomCode();

        // Business validation
        $this->validateAssignmentDataOrFail($data);

        return DB::transaction(function () use ($data) {

            // Prevent duplicate active assignments
            if ($this->repository->duplicateAssignmentExists(
                $data['facility_id'],
                $data['staff_id'],
                $data['role_code'],
                $data['effective_from'],
                $data['exclude_id'] ?? null
            )) {
                throw ValidationException::withMessages([
                    'assignment' => [
                        'An active assignment already exists for this staff member at this facility with the same role.'
                    ]
                ]);
            }

            // Normalize JSON fields
            $data = $this->parseJsonFields($data);

            // Enforce system ownership
            $data['created_by_staff_id'] ??= auth::id();

            return $this->repository->create($data);
        });
    }


    public function validateAssignmentDataOrFail(array $data): void
    {
        if (empty($data['facility_id']) || empty($data['staff_id'])) {
            throw ValidationException::withMessages([
                'assignment' => ['Facility and staff are required']
            ]);
        }
    }

    /**
     * Update an existing role assignment
     */
    public function updateAssignment(int $id, array $data): array
    {
        // Get existing assignment
        $assignment = $this->repository->findById($id);
        
        if (!$assignment) {
            return [
                'success' => false,
                'message' => 'Role assignment not found',
                'data' => null
            ];
        }
        
        // Validate business rules
        $validationResult = $this->validateAssignmentData($data, $id);
        
        if (!$validationResult['success']) {
            return $validationResult;
        }
        
        // Check for duplicate active assignments (excluding current)
        if (isset($data['facility_id'], $data['staff_id'], $data['role_code'], $data['effective_from'])) {
            $duplicateExists = $this->repository->duplicateAssignmentExists(
                $data['facility_id'],
                $data['staff_id'],
                $data['role_code'],
                $data['effective_from'],
                $id
            );
            
            if ($duplicateExists) {
                return [
                    'success' => false,
                    'message' => 'An active assignment already exists for this staff member at this facility with the same role and effective date',
                    'errors' => [
                        'assignment' => ['Duplicate assignment detected']
                    ]
                ];
            }
        }
        
        DB::beginTransaction();
        
        try {
            // Parse JSON fields if they're arrays
            $data = $this->parseJsonFields($data);
            
            // Update the assignment
            $updatedAssignment = $this->repository->update($assignment, $data);
            
            DB::commit();
            
            return [
                'success' => true,
                'message' => 'Role assignment updated successfully',
                'data' => $updatedAssignment
            ];
        } catch (\RuntimeException $e) {
            DB::rollBack();
            
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => [
                    'system' => ['Failed to update assignment']
                ]
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Service: Failed to update assignment', [
                'id' => $id,
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to update role assignment. Please try again.',
                'errors' => [
                    'system' => ['Internal server error']
                ]
            ];
        }
    }

    /**
     * Delete a role assignment
     */
    public function deleteAssignment(int $id): bool
    {
        try {
            $assignment = $this->repository->findById($id);
            
            if (!$assignment) {
                return false;
            }
            
            // Check if assignment is active
            if ($assignment->assignment_status === FacilityStaffRole::STATUS_ACTIVE) {
                // Instead of deleting, mark as terminated
                $this->repository->updateStatus($assignment, FacilityStaffRole::STATUS_TERMINATED, [
                    'effective_to' => now()->format('Y-m-d')
                ]);
                return true;
            }
            
            return $this->repository->delete($assignment);
        } catch (\Exception $e) {
            Log::error('Service: Failed to delete assignment', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Restore a soft-deleted role assignment
     */
    public function restoreAssignment(int $id): bool
    {
        try {
            $assignment = FacilityStaffRole::withTrashed()->find($id);
            
            if (!$assignment || !$assignment->trashed()) {
                return false;
            }
            
            return $this->repository->restore($assignment);
        } catch (\Exception $e) {
            Log::error('Service: Failed to restore assignment', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Update assignment status
     */
    public function updateAssignmentStatus(int $id, string $status, array $additionalData = []): array
    {
        // Validate status
        $validStatuses = [
            FacilityStaffRole::STATUS_ACTIVE,
            FacilityStaffRole::STATUS_ON_LEAVE,
            FacilityStaffRole::STATUS_SUSPENDED,
            FacilityStaffRole::STATUS_TERMINATED
        ];
        
        if (!in_array($status, $validStatuses)) {
            return [
                'success' => false,
                'message' => 'Invalid assignment status',
                'errors' => [
                    'assignment_status' => ['Status must be one of: ' . implode(', ', $validStatuses)]
                ]
            ];
        }
        
        try {
            $assignment = $this->repository->findById($id);
            
            if (!$assignment) {
                return [
                    'success' => false,
                    'message' => 'Role assignment not found',
                    'data' => null
                ];
            }
            
            // Update status
            $updatedAssignment = $this->repository->updateStatus($assignment, $status, $additionalData);
            
            return [
                'success' => true,
                'message' => 'Assignment status updated successfully',
                'data' => $updatedAssignment
            ];
        } catch (\RuntimeException $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => [
                    'system' => ['Failed to update status']
                ]
            ];
        } catch (\Exception $e) {
            Log::error('Service: Failed to update assignment status', [
                'id' => $id,
                'status' => $status,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to update assignment status. Please try again.',
                'errors' => [
                    'system' => ['Internal server error']
                ]
            ];
        }
    }

    /**
     * Get active assignments for a staff member
     */
    public function getActiveAssignmentsForStaff(int $staffId, ?string $date = null): Collection
    {
        try {
            return $this->repository->getActiveAssignmentsForStaff($staffId, $date);
        } catch (\Exception $e) {
            Log::error('Service: Failed to get active assignments for staff', [
                'staff_id' => $staffId,
                'date' => $date,
                'error' => $e->getMessage()
            ]);
            
            return new Collection();
        }
    }

    /**
     * Check for scheduling conflicts
     */
    public function checkScheduleConflicts(int $staffId, array $schedule, ?int $excludeAssignmentId = null): array
    {
        try {
            // Get all active assignments for the staff member
            $assignments = $this->getActiveAssignmentsForStaff($staffId);
            
            $conflicts = [];
            
            foreach ($assignments as $assignment) {
                // Skip the assignment we're excluding
                if ($excludeAssignmentId && $assignment->id === $excludeAssignmentId) {
                    continue;
                }
                
                // Check if assignment has a schedule
                if (!$assignment->shift_schedule) {
                    continue;
                }
                
                // Compare schedules (simplified conflict detection)
                // In a real implementation, this would be more sophisticated
                $assignmentSchedule = $assignment->shift_schedule;
                
                // Check for overlapping days/times
                if ($this->schedulesOverlap($assignmentSchedule, $schedule)) {
                    $conflicts[] = [
                        'assignment_id' => $assignment->id,
                        'facility_id' => $assignment->facility_id,
                        'role_code' => $assignment->role_code,
                        'conflict_type' => 'schedule_overlap'
                    ];
                }
            }
            
            return [
                'success' => true,
                'has_conflicts' => !empty($conflicts),
                'conflicts' => $conflicts,
                'message' => empty($conflicts) 
                    ? 'No schedule conflicts detected' 
                    : 'Schedule conflicts detected'
            ];
        } catch (\Exception $e) {
            Log::error('Service: Failed to check schedule conflicts', [
                'staff_id' => $staffId,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'has_conflicts' => false,
                'conflicts' => [],
                'message' => 'Failed to check schedule conflicts'
            ];
        }
    }

    /**
     * Get expiring assignments
     */
    public function getExpiringAssignments(int $daysAhead = 30): Collection
    {
        try {
            return $this->repository->getExpiringAssignments($daysAhead);
        } catch (\Exception $e) {
            Log::error('Service: Failed to get expiring assignments', [
                'days_ahead' => $daysAhead,
                'error' => $e->getMessage()
            ]);
            
            return new Collection();
        }
    }

    /**
     * Update credentialing information
     */
    public function updateCredentialing(int $id, array $credentialingData): array
    {
        try {
            $assignment = $this->repository->findById($id);
            
            if (!$assignment) {
                return [
                    'success' => false,
                    'message' => 'Role assignment not found',
                    'data' => null
                ];
            }
            
            // Prepare update data
            $updateData = [];
            
            if (isset($credentialingData['credentialing_completed_at'])) {
                $updateData['credentialing_completed_at'] = $credentialingData['credentialing_completed_at'];
            }
            
            if (isset($credentialingData['credentialed_by_staff_id'])) {
                $updateData['credentialed_by_staff_id'] = $credentialingData['credentialed_by_staff_id'];
            }
            
            if (isset($credentialingData['privileging_approved_at'])) {
                $updateData['privileging_approved_at'] = $credentialingData['privileging_approved_at'];
            }
            
            if (isset($credentialingData['next_reappointment_date'])) {
                $updateData['next_reappointment_date'] = $credentialingData['next_reappointment_date'];
            }
            
            // Update the assignment
            $updatedAssignment = $this->repository->update($assignment, $updateData);
            
            return [
                'success' => true,
                'message' => 'Credentialing information updated successfully',
                'data' => $updatedAssignment
            ];
        } catch (\RuntimeException $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => [
                    'system' => ['Failed to update credentialing']
                ]
            ];
        } catch (\Exception $e) {
            Log::error('Service: Failed to update credentialing', [
                'id' => $id,
                'data' => $credentialingData,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to update credentialing information. Please try again.',
                'errors' => [
                    'system' => ['Internal server error']
                ]
            ];
        }
    }

    /**
     * Validate role assignment data
     */
    public function validateAssignmentData(array $data, ?int $excludeId = null): array
    {
        // Basic validation rules
        $rules = [
            'facility_id' => 'required|integer|exists:facilities,id',
            'staff_id' => 'required|integer|exists:staff,id',
            'role_code' => 'required|string|in:' . implode(',', FacilityStaffRole::ROLE_CODES),
            'department_ids' => 'nullable|array',
            'department_ids.*' => 'integer',
            'is_primary_facility' => 'boolean',
            'privileges_bitmask' => 'nullable|array',
            'accessible_patient_populations' => 'nullable|array',
            'prescribing_authority_at_facility' => 'nullable|array',
            'shift_schedule' => 'nullable|array',
            'shift_type' => 'nullable|string|in:day,night,rotating,on_call,flexible',
            'hours_per_week' => 'nullable|integer|min:1|max:168',
            'effective_from' => 'required|date',
            'effective_to' => 'nullable|date|after_or_equal:effective_from',
            'assignment_status' => 'nullable|string|in:active,on_leave,suspended,terminated',
            'facility_satisfaction_score' => 'nullable|numeric|min:0|max:5',
            'created_by_staff_id' => 'nullable|integer|exists:staff,id',
            'credentialed_by_staff_id' => 'nullable|integer|exists:staff,id'
        ];
        
        // Create validator
        $validator = Validator::make($data, $rules);
        
        if ($validator->fails()) {
            return [
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()->toArray()
            ];
        }
        
        // Additional business rule: effective_from cannot be in the past for new assignments
        if (isset($data['effective_from'])) {
            $effectiveFrom = $data['effective_from'];
            
            // If this is a new assignment (no excludeId) and effective_from is in the past
            if (!$excludeId && strtotime($effectiveFrom) < strtotime(now()->format('Y-m-d'))) {
                return [
                    'success' => false,
                    'message' => 'Effective date cannot be in the past for new assignments',
                    'errors' => [
                        'effective_from' => ['Effective date must be today or in the future for new assignments']
                    ]
                ];
            }
        }
        
        // Additional business rule: check if staff exists at facility with same role on same date
        if (isset($data['facility_id'], $data['staff_id'], $data['role_code'], $data['effective_from'])) {
            try {
                $duplicateExists = $this->repository->duplicateAssignmentExists(
                    $data['facility_id'],
                    $data['staff_id'],
                    $data['role_code'],
                    $data['effective_from'],
                    $excludeId
                );
                
                if ($duplicateExists) {
                    return [
                        'success' => false,
                        'message' => 'Duplicate assignment detected',
                        'errors' => [
                            'assignment' => ['An assignment already exists for this staff member at this facility with the same role and effective date']
                        ]
                    ];
                }
            } catch (\Exception $e) {
                // Log but don't fail validation on repository errors
                Log::warning('Failed to check for duplicate assignments during validation', [
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        return [
            'success' => true,
            'message' => 'Validation passed'
        ];
    }

    /**
     * Parse array fields to JSON
     */
    private function parseJsonFields(array $data): array
    {
        $jsonFields = [
            'department_ids',
            'privileges_bitmask',
            'accessible_patient_populations',
            'prescribing_authority_at_facility',
            'shift_schedule',
            'metadata'
        ];
        
        foreach ($jsonFields as $field) {
            if (isset($data[$field]) && is_array($data[$field])) {
                $data[$field] = json_encode($data[$field]);
            }
        }
        
        return $data;
    }

    /**
     * Check if two schedules overlap (simplified)
     */
    private function schedulesOverlap(array $schedule1, array $schedule2): bool
    {
        // This is a simplified implementation
        // In a real system, you would parse actual day/time slots
        
        if (empty($schedule1) || empty($schedule2)) {
            return false;
        }
        
        // Check for overlapping days
        $days1 = $schedule1['days'] ?? [];
        $days2 = $schedule2['days'] ?? [];
        
        if (empty($days1) || empty($days2)) {
            return false;
        }
        
        $overlappingDays = array_intersect($days1, $days2);
        
        if (empty($overlappingDays)) {
            return false;
        }
        
        // Check for overlapping times on overlapping days
        // This is simplified - actual implementation would compare time ranges
        
        return true;
    }
}