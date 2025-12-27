<?php

namespace App\Services\Staff;

use App\Models\Staff;
use App\Repositories\Contracts\StaffRepositoryInterface;
use App\Services\Contracts\StaffServiceInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class StaffService implements StaffServiceInterface
{
    /**
     * Staff repository instance.
     */
    protected StaffRepositoryInterface $staffRepository;

    /**
     * Create a new service instance.
     */
    public function __construct(StaffRepositoryInterface $staffRepository)
    {
        $this->staffRepository = $staffRepository;
    }

    /**
     * Get staff by ID.
     */
    public function getStaffById(int $id): ?Staff
    {
        try {
            $staff = $this->staffRepository->find($id);
            
            if (!$staff) {
                Log::warning('Staff not found', ['id' => $id]);
                return null;
            }
            
            // Load relationships
            $staff->load(['user', 'supervisor', 'subordinates', 'createdBy', 'updatedBy']);
            
            return $staff;
        } catch (\Exception $e) {
            Log::error('Error retrieving staff by ID', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * Get staff by UUID.
     */
    public function getStaffByUuid(string $uuid): ?Staff
    {
        try {
            $staff = $this->staffRepository->findByUuid($uuid);
            
            if (!$staff) {
                Log::warning('Staff not found by UUID', ['uuid' => $uuid]);
                return null;
            }
            
            $staff->load(['user', 'supervisor']);
            
            return $staff;
        } catch (\Exception $e) {
            Log::error('Error retrieving staff by UUID', [
                'uuid' => $uuid,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Get all staff with pagination.
     */
    public function getAllStaff(array $filters = [], int $perPage = 20)
    {
        try {
            return $this->staffRepository->all($filters, $perPage);
        } catch (\Exception $e) {
            Log::error('Error retrieving all staff', [
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);
            
            // Return empty paginator instead of throwing exception
            return new \Illuminate\Pagination\LengthAwarePaginator(
                [],
                0,
                $perPage,
                1
            );
        }
    }

    /**
     * Create new staff.
     */
    public function createStaff(array $data): array
    {
        try {
            return DB::transaction(function () use ($data) {
                // Generate UUID
                $data['staff_uuid'] = Str::uuid()->toString();
                
                // Hash license number for duplicate detection
                if (!empty($data['professional_license_number_encrypted'])) {
                    $data['professional_license_number_hash'] = Hash::make(
                        $data['professional_license_number_encrypted']
                    );
                }
                
                // Set audit trail
                $data['created_by_staff_id'] = auth::id();
                $data['updated_by_staff_id'] = auth::id();
                
                // Create staff
                $staff = $this->staffRepository->create($data);
                
                if (!$staff) {
                    return [
                        'success' => false,
                        'message' => 'Failed to create staff record.',
                        'data' => null
                    ];
                }
                
                Log::info('Staff created successfully', [
                    'staff_id' => $staff->id,
                    'employee_id' => $staff->employee_id
                ]);
                
                return [
                    'success' => true,
                    'message' => 'Staff created successfully.',
                    'data' => $staff
                ];
            });
        } catch (\Exception $e) {
            Log::error('Error creating staff', [
                'data_keys' => array_keys($data),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'message' => 'An error occurred while creating staff. Please try again.',
                'data' => null
            ];
        }
    }

    /**
     * Update staff.
     */
    public function updateStaff(int $id, array $data): array
    {
        try {
            // Check if staff exists
            $staff = $this->staffRepository->find($id);
            
            if (!$staff) {
                return [
                    'success' => false,
                    'message' => 'Staff not found.',
                    'data' => null
                ];
            }
            
            // Update audit trail
            $data['updated_by_staff_id'] = auth::id();
            
            // Update license hash if license number changed
            if (isset($data['professional_license_number_encrypted']) && 
                $data['professional_license_number_encrypted'] !== $staff->professional_license_number_encrypted) {
                $data['professional_license_number_hash'] = Hash::make(
                    $data['professional_license_number_encrypted']
                );
            }
            
            $updated = $this->staffRepository->update($id, $data);
            
            if (!$updated) {
                return [
                    'success' => false,
                    'message' => 'Failed to update staff record.',
                    'data' => null
                ];
            }
            
            // Refresh staff data
            $staff->refresh();
            $staff->load(['user', 'supervisor']);
            
            Log::info('Staff updated successfully', [
                'staff_id' => $id,
                'updated_fields' => array_keys($data)
            ]);
            
            return [
                'success' => true,
                'message' => 'Staff updated successfully.',
                'data' => $staff
            ];
        } catch (\Exception $e) {
            Log::error('Error updating staff', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'message' => 'An error occurred while updating staff. Please try again.',
                'data' => null
            ];
        }
    }

    /**
     * Delete staff.
     */
    public function deleteStaff(int $id): array
    {
        try {
            // Check if staff exists
            $staff = $this->staffRepository->find($id);
            
            if (!$staff) {
                return [
                    'success' => false,
                    'message' => 'Staff not found.',
                    'data' => null
                ];
            }
            
            // Prevent deletion of active staff
            if ($staff->employment_status === 'active') {
                return [
                    'success' => false,
                    'message' => 'Cannot delete active staff. Update employment status first.',
                    'data' => null
                ];
            }
            
            $deleted = $this->staffRepository->delete($id);
            
            if (!$deleted) {
                return [
                    'success' => false,
                    'message' => 'Failed to delete staff record.',
                    'data' => null
                ];
            }
            
            Log::info('Staff deleted successfully', ['staff_id' => $id]);
            
            return [
                'success' => true,
                'message' => 'Staff deleted successfully.',
                'data' => null
            ];
        } catch (\Exception $e) {
            Log::error('Error deleting staff', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'An error occurred while deleting staff. Please try again.',
                'data' => null
            ];
        }
    }

    /**
     * Update staff license information.
     */
    public function updateLicenseInfo(int $id, array $licenseData): array
    {
        try {
            // Validate required fields
            if (empty($licenseData['license_number_encrypted']) || 
                empty($licenseData['issuing_state']) || 
                empty($licenseData['expiry_date'])) {
                return [
                    'success' => false,
                    'message' => 'License number, issuing state, and expiry date are required.',
                    'data' => null
                ];
            }
            
            // Check if staff exists
            $staff = $this->staffRepository->find($id);
            
            if (!$staff) {
                return [
                    'success' => false,
                    'message' => 'Staff not found.',
                    'data' => null
                ];
            }
            
            $updated = $this->staffRepository->updateLicenseInfo($id, $licenseData);
            
            if (!$updated) {
                return [
                    'success' => false,
                    'message' => 'Failed to update license information.',
                    'data' => null
                ];
            }
            
            Log::info('Staff license updated', [
                'staff_id' => $id,
                'expiry_date' => $licenseData['expiry_date']
            ]);
            
            return [
                'success' => true,
                'message' => 'License information updated successfully.',
                'data' => $this->staffRepository->find($id)
            ];
        } catch (\Exception $e) {
            Log::error('Error updating staff license', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'An error occurred while updating license information.',
                'data' => null
            ];
        }
    }

    /**
     * Update employment status.
     */
    public function updateEmploymentStatus(int $id, string $status, ?string $reason = null): array
    {
        try {
            $validStatuses = [
                'active', 'on_leave', 'suspended', 'terminated', 'retired', 'credentialing_pending'
            ];
            
            if (!in_array($status, $validStatuses)) {
                return [
                    'success' => false,
                    'message' => 'Invalid employment status.',
                    'data' => null
                ];
            }
            
            // Check if staff exists
            $staff = $this->staffRepository->find($id);
            
            if (!$staff) {
                return [
                    'success' => false,
                    'message' => 'Staff not found.',
                    'data' => null
                ];
            }
            
            $updateData = [
                'employment_status' => $status,
                'updated_by_staff_id' => auth::id()
            ];
            
            // Set termination date if status is terminated
            if ($status === 'terminated') {
                $updateData['termination_date'] = now()->toDateString();
                $updateData['termination_reason'] = $reason;
            }
            
            // Clear termination date if reactivating
            if ($status === 'active' && $staff->employment_status === 'terminated') {
                $updateData['termination_date'] = null;
                $updateData['termination_reason'] = null;
            }
            
            $updated = $this->staffRepository->update($id, $updateData);
            
            if (!$updated) {
                return [
                    'success' => false,
                    'message' => 'Failed to update employment status.',
                    'data' => null
                ];
            }
            
            Log::info('Staff employment status updated', [
                'staff_id' => $id,
                'old_status' => $staff->employment_status,
                'new_status' => $status
            ]);
            
            return [
                'success' => true,
                'message' => 'Employment status updated successfully.',
                'data' => $this->staffRepository->find($id)
            ];
        } catch (\Exception $e) {
            Log::error('Error updating employment status', [
                'id' => $id,
                'status' => $status,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'An error occurred while updating employment status.',
                'data' => null
            ];
        }
    }

    /**
     * Check staff credentials for specific privilege.
     */
    public function checkStaffPrivilege(int $staffId, string $privilege): bool
    {
        try {
            $staff = $this->staffRepository->find($staffId);
            
            if (!$staff || $staff->employment_status !== 'active') {
                return false;
            }
            
            // Check based on privilege type
            switch ($privilege) {
                case 'prescribe_controlled_substances':
                    return $staff->can_order_controlled_substances && 
                           !empty($staff->prescribing_authority) &&
                           !$staff->hasExpiredDEA();
                
                case 'supervise_trainees':
                    return $staff->can_supervise_trainees &&
                           in_array($staff->global_role_level, [
                               'attending_physician',
                               'department_head',
                               'facility_admin'
                           ]);
                
                case 'sign_death_certificates':
                    return $staff->can_sign_death_certificates &&
                           !$staff->hasExpiredLicense();
                
                case 'access_sensitive_data':
                    return in_array($staff->global_role_level, [
                        'super_admin',
                        'facility_admin',
                        'department_head',
                        'attending_physician'
                    ]);
                
                default:
                    return false;
            }
        } catch (\Exception $e) {
            Log::error('Error checking staff privilege', [
                'staff_id' => $staffId,
                'privilege' => $privilege,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get staff with expiring credentials.
     */
    public function getStaffWithExpiringCredentials(int $days = 30): array
    {
        try {
            $expiringLicenses = $this->staffRepository->getWithExpiringLicenses($days);
            $expiringDEA = $this->staffRepository->getWithExpiringDEA($days);
            
            return [
                'success' => true,
                'message' => 'Staff with expiring credentials retrieved successfully.',
                'data' => [
                    'expiring_licenses' => $expiringLicenses,
                    'expiring_dea_registrations' => $expiringDEA,
                    'total_expiring' => $expiringLicenses->count() + $expiringDEA->count()
                ]
            ];
        } catch (\Exception $e) {
            Log::error('Error getting staff with expiring credentials', [
                'days' => $days,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to retrieve staff with expiring credentials.',
                'data' => []
            ];
        }
    }

    /**
     * Validate staff can perform action.
     */
    public function validateStaffAction(int $staffId, string $action): array
    {
        try {
            $staff = $this->staffRepository->find($staffId);
            
            if (!$staff) {
                return [
                    'valid' => false,
                    'message' => 'Staff not found.',
                    'errors' => ['staff_id' => 'Invalid staff ID']
                ];
            }
            
            // Check employment status
            if ($staff->employment_status !== 'active') {
                return [
                    'valid' => false,
                    'message' => 'Staff is not active.',
                    'errors' => ['employment_status' => 'Staff must be active']
                ];
            }
            
            // Check license validity
            if ($staff->hasExpiredLicense()) {
                return [
                    'valid' => false,
                    'message' => 'Staff license has expired.',
                    'errors' => ['license' => 'License expired']
                ];
            }
            
            // Action-specific validations
            $validations = [
                'prescribe_medication' => $staff->canPrescribe(),
                'supervise_others' => $staff->can_supervise_trainees,
                'access_confidential' => $staff->hipaa_training_completed &&
                    $staff->hipaa_training_expiry > now()
            ];
            
            if (isset($validations[$action]) && !$validations[$action]) {
                return [
                    'valid' => false,
                    'message' => 'Staff lacks required credentials for this action.',
                    'errors' => ['credentials' => 'Insufficient credentials']
                ];
            }
            
            return [
                'valid' => true,
                'message' => 'Staff is authorized to perform this action.',
                'errors' => []
            ];
        } catch (\Exception $e) {
            Log::error('Error validating staff action', [
                'staff_id' => $staffId,
                'action' => $action,
                'error' => $e->getMessage()
            ]);
            
            return [
                'valid' => false,
                'message' => 'Error validating staff authorization.',
                'errors' => ['system' => 'Validation error']
            ];
        }
    }

    /**
     * Bulk update staff status.
     */
    public function bulkUpdateStatus(array $staffIds, string $status): array
    {
        try {
            $validStatuses = [
                'active', 'on_leave', 'suspended', 'terminated'
            ];
            
            if (!in_array($status, $validStatuses)) {
                return [
                    'success' => false,
                    'message' => 'Invalid status provided.',
                    'data' => ['updated' => 0, 'failed' => count($staffIds)]
                ];
            }
            
            $updated = 0;
            $failed = 0;
            $results = [];
            
            foreach ($staffIds as $staffId) {
                $result = $this->updateEmploymentStatus($staffId, $status);
                
                if ($result['success']) {
                    $updated++;
                    $results[] = [
                        'staff_id' => $staffId,
                        'status' => 'updated',
                        'message' => $result['message']
                    ];
                } else {
                    $failed++;
                    $results[] = [
                        'staff_id' => $staffId,
                        'status' => 'failed',
                        'message' => $result['message']
                    ];
                }
            }
            
            $message = "Updated {$updated} staff members. {$failed} failed.";
            
            return [
                'success' => $failed === 0,
                'message' => $message,
                'data' => [
                    'updated' => $updated,
                    'failed' => $failed,
                    'results' => $results
                ]
            ];
        } catch (\Exception $e) {
            Log::error('Error in bulk status update', [
                'staff_ids' => $staffIds,
                'status' => $status,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to perform bulk update.',
                'data' => ['updated' => 0, 'failed' => count($staffIds)]
            ];
        }
    }
}