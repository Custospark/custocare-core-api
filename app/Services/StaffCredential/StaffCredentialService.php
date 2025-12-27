<?php

namespace App\Services\StaffCredential;

use App\Models\StaffCredential;
use App\Repositories\Contracts\StaffCredentialRepositoryInterface;
use App\Services\Contracts\StaffCredentialServiceInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class StaffCredentialService implements StaffCredentialServiceInterface
{
    /**
     * Repository instance
     *
     * @var StaffCredentialRepositoryInterface
     */
    protected StaffCredentialRepositoryInterface $repository;

    /**
     * Constructor
     *
     * @param StaffCredentialRepositoryInterface $repository
     */
    public function __construct(StaffCredentialRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * {@inheritdoc}
     */
    public function getCredential(string $uuid): array
    {
        try {
            $credential = $this->repository->findByUuid($uuid);
            
            if (!$credential) {
                return [
                    'success' => false,
                    'message' => 'Credential not found',
                    'data' => null,
                    'status' => 404
                ];
            }

            return [
                'success' => true,
                'message' => 'Credential retrieved successfully',
                'data' => $credential,
                'status' => 200
            ];
        } catch (\Exception $e) {
            Log::error('Failed to retrieve credential', [
                'uuid' => $uuid,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to retrieve credential. Please try again later.',
                'data' => null,
                'status' => 500
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getStaffCredentials(int $staffId, array $filters = []): array
    {
        try {
            $credentials = $this->repository->getByStaffId($staffId, $filters);

            return [
                'success' => true,
                'message' => 'Staff credentials retrieved successfully',
                'data' => $credentials,
                'status' => 200
            ];
        } catch (\Exception $e) {
            Log::error('Failed to retrieve staff credentials', [
                'staff_id' => $staffId,
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to retrieve staff credentials. Please try again later.',
                'data' => null,
                'status' => 500
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function createCredential(array $data): array
    {
        try {
            // Validate data
            $validationErrors = $this->validateCredentialData($data);
            if (!empty($validationErrors)) {
                return [
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validationErrors,
                    'status' => 422
                ];
            }

            // Set default values
            $data['credential_uuid'] = $data['credential_uuid'] ?? (string) \Illuminate\Support\Str::uuid();
            $data['snapshot_taken_at'] = $data['snapshot_taken_at'] ?? now();
            $data['is_current'] = $data['is_current'] ?? true;

            // Validate date logic
            if ($data['valid_to'] && $data['valid_from'] > $data['valid_to']) {
                return [
                    'success' => false,
                    'message' => 'Valid from date cannot be after valid to date',
                    'errors' => ['valid_from' => ['Valid from date cannot be after valid to date']],
                    'status' => 422
                ];
            }

            if ($data['issued_date'] && $data['valid_from'] && $data['issued_date'] > $data['valid_from']) {
                return [
                    'success' => false,
                    'message' => 'Issued date cannot be after valid from date',
                    'errors' => ['issued_date' => ['Issued date cannot be after valid from date']],
                    'status' => 422
                ];
            }

            // Create credential
            $credential = $this->repository->create($data);

            return [
                'success' => true,
                'message' => 'Credential created successfully',
                'data' => $credential,
                'status' => 201
            ];
        } catch (\Exception $e) {
            Log::error('Failed to create credential', [
                'data' => $this->sanitizeLogData($data),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to create credential. Please try again later.',
                'data' => null,
                'status' => 500
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function updateCredential(string $uuid, array $data): array
    {
        try {
            // Validate data
            $validationErrors = $this->validateCredentialData($data, true);
            if (!empty($validationErrors)) {
                return [
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validationErrors,
                    'status' => 422
                ];
            }

            // Get current credential
            $currentCredential = $this->repository->findByUuid($uuid);
            if (!$currentCredential) {
                return [
                    'success' => false,
                    'message' => 'Credential not found',
                    'data' => null,
                    'status' => 404
                ];
            }

            // Check if we need to supersede instead of update
            if ($currentCredential->is_current && 
                $currentCredential->verification_status === 'verified' &&
                $this->isSignificantUpdate($currentCredential, $data)) {
                return [
                    'success' => false,
                    'message' => 'Cannot update current verified credential directly. Please supersede it.',
                    'data' => null,
                    'status' => 422
                ];
            }

            // Update credential
            $updatedCredential = $this->repository->update($uuid, $data);

            return [
                'success' => true,
                'message' => 'Credential updated successfully',
                'data' => $updatedCredential,
                'status' => 200
            ];
        } catch (ModelNotFoundException $e) {
            return [
                'success' => false,
                'message' => 'Credential not found',
                'data' => null,
                'status' => 404
            ];
        } catch (\Exception $e) {
            Log::error('Failed to update credential', [
                'uuid' => $uuid,
                'data' => $this->sanitizeLogData($data),
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to update credential. Please try again later.',
                'data' => null,
                'status' => 500
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function deleteCredential(string $uuid): array
    {
        try {
            $credential = $this->repository->findByUuid($uuid);
            
            if (!$credential) {
                return [
                    'success' => false,
                    'message' => 'Credential not found',
                    'data' => null,
                    'status' => 404
                ];
            }

            // Business rule: Cannot delete current verified credentials
            if ($credential->is_current && $credential->verification_status === 'verified') {
                return [
                    'success' => false,
                    'message' => 'Cannot delete current verified credential. Please supersede it first.',
                    'data' => null,
                    'status' => 422
                ];
            }

            $deleted = $this->repository->delete($uuid);
            
            if (!$deleted) {
                return [
                    'success' => false,
                    'message' => 'Failed to delete credential',
                    'data' => null,
                    'status' => 500
                ];
            }

            return [
                'success' => true,
                'message' => 'Credential deleted successfully',
                'data' => null,
                'status' => 200
            ];
        } catch (\Exception $e) {
            Log::error('Failed to delete credential', [
                'uuid' => $uuid,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to delete credential. Please try again later.',
                'data' => null,
                'status' => 500
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function verifyCredential(string $uuid, array $verificationData, int $verifyingStaffId): array
    {
        try {
            // Add verifying staff ID to verification data
            $verificationData['verified_by_staff_id'] = $verifyingStaffId;

            // Validate verification data
            $validator = Validator::make($verificationData, [
                'verified_by_staff_id' => 'required|integer|exists:staff,id',
                'verification_method' => 'required|string|in:primary_source,database_check,document_review',
                'verification_notes' => 'nullable|string|max:1000',
            ]);

            if ($validator->fails()) {
                return [
                    'success' => false,
                    'message' => 'Verification data validation failed',
                    'errors' => $validator->errors()->toArray(),
                    'status' => 422
                ];
            }

            // Verify credential
            $verifiedCredential = $this->repository->verify($uuid, $verificationData);

            return [
                'success' => true,
                'message' => 'Credential verified successfully',
                'data' => $verifiedCredential,
                'status' => 200
            ];
        } catch (ModelNotFoundException $e) {
            return [
                'success' => false,
                'message' => 'Credential not found',
                'data' => null,
                'status' => 404
            ];
        } catch (\Exception $e) {
            Log::error('Failed to verify credential', [
                'uuid' => $uuid,
                'verifying_staff_id' => $verifyingStaffId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to verify credential. Please try again later.',
                'data' => null,
                'status' => 500
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function supersedeCredential(string $uuid, array $newCredentialData, int $createdByStaffId): array
    {
        try {
            // Add created by staff ID
            $newCredentialData['created_by_staff_id'] = $createdByStaffId;

            // Validate new credential data
            $validationErrors = $this->validateCredentialData($newCredentialData);
            if (!empty($validationErrors)) {
                return [
                    'success' => false,
                    'message' => 'New credential validation failed',
                    'errors' => $validationErrors,
                    'status' => 422
                ];
            }

            // Supersede credential
            $result = $this->repository->supersede($uuid, $newCredentialData);

            return [
                'success' => true,
                'message' => 'Credential superseded successfully',
                'data' => $result,
                'status' => 201
            ];
        } catch (ModelNotFoundException $e) {
            return [
                'success' => false,
                'message' => 'Credential not found',
                'data' => null,
                'status' => 404
            ];
        } catch (\Exception $e) {
            Log::error('Failed to supersede credential', [
                'uuid' => $uuid,
                'created_by_staff_id' => $createdByStaffId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to supersede credential. Please try again later.',
                'data' => null,
                'status' => 500
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getExpiringCredentials(int $days = 30): array
    {
        try {
            $credentials = $this->repository->getExpiringSoon($days);

            return [
                'success' => true,
                'message' => 'Expiring credentials retrieved successfully',
                'data' => $credentials,
                'count' => $credentials->count(),
                'status' => 200
            ];
        } catch (\Exception $e) {
            Log::error('Failed to retrieve expiring credentials', [
                'days' => $days,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to retrieve expiring credentials. Please try again later.',
                'data' => null,
                'status' => 500
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getExpiredCredentials(): array
    {
        try {
            $credentials = $this->repository->getExpired();

            return [
                'success' => true,
                'message' => 'Expired credentials retrieved successfully',
                'data' => $credentials,
                'count' => $credentials->count(),
                'status' => 200
            ];
        } catch (\Exception $e) {
            Log::error('Failed to retrieve expired credentials', [
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to retrieve expired credentials. Please try again later.',
                'data' => null,
                'status' => 500
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function searchCredentials(array $filters, int $perPage = 15): array
    {
        try {
            $paginator = $this->repository->search($filters, $perPage);

            return [
                'success' => true,
                'message' => 'Credentials search completed successfully',
                'data' => $paginator->items(),
                'pagination' => [
                    'total' => $paginator->total(),
                    'per_page' => $paginator->perPage(),
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                ],
                'status' => 200
            ];
        } catch (\Exception $e) {
            Log::error('Failed to search credentials', [
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to search credentials. Please try again later.',
                'data' => null,
                'status' => 500
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function validateCredentialData(array $data, bool $isUpdate = false): array
    {
        $rules = [
            'staff_id' => 'required|integer|exists:staff,id',
            'credential_type' => ['required', 'string', Rule::in(StaffCredential::getCredentialTypes())],
            'credential_name' => 'required|string|max:200',
            'credential_number_encrypted' => 'nullable|string|max:512',
            'credential_number_hash' => 'nullable|string|max:128',
            'issuing_authority' => 'required|string|max:200',
            'issuing_authority_contact' => 'nullable|string|max:200',
            'issuing_state_country' => 'nullable|string|max:100',
            'issued_date' => 'required|date',
            'valid_from' => 'required|date',
            'valid_to' => 'nullable|date',
            'requires_renewal' => 'boolean',
            'renewal_reminder_date' => 'nullable|date',
            'verification_status' => ['required', 'string', Rule::in(StaffCredential::getVerificationStatuses())],
            'verified_at' => 'nullable|date',
            'verified_by_staff_id' => 'nullable|integer|exists:staff,id',
            'verification_method' => 'nullable|string|max:100',
            'verification_notes' => 'nullable|string',
            'credential_document_hash' => 'required|string|max:128',
            'document_storage_path' => 'nullable|string|max:512',
            'document_mime_type' => 'nullable|string|max:100',
            'document_size_bytes' => 'nullable|integer|min:0',
            'snapshot_taken_at' => 'nullable|date',
            'is_current' => 'boolean',
            'superseded_by_credential_id' => 'nullable|integer|exists:staff_credentials,id',
            'created_by_staff_id' => 'nullable|integer|exists:staff,id',
            'metadata' => 'nullable|array',
        ];

        // Remove required for update if field is not being updated
        if ($isUpdate) {
            foreach ($rules as $field => $rule) {
                if (is_string($rule) && strpos($rule, 'required') === 0) {
                    $rules[$field] = str_replace('required|', '', $rule);
                } elseif (is_array($rule)) {
                    // Handle array rules
                    $rules[$field] = array_filter($rule, function ($r) {
                        return $r !== 'required';
                    });
                }
            }
        }

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            return $validator->errors()->toArray();
        }

        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function getStatistics(?int $staffId = null): array
    {
        try {
            $query = StaffCredential::query();
            
            if ($staffId) {
                $query->where('staff_id', $staffId);
            }

            $total = $query->count();
            $verified = $query->where('verification_status', 'verified')->count();
            $expired = $query->where('verification_status', 'expired')->count();
            $pending = $query->where('verification_status', 'pending')->count();
            $current = $query->where('is_current', true)->count();

            // Get counts by credential type
            $byType = $query->select('credential_type', DB::raw('count(*) as count'))
                ->groupBy('credential_type')
                ->pluck('count', 'credential_type')
                ->toArray();

            // Get expiring soon count
            $expiringSoon = $query->where('valid_to', '<=', now()->addDays(30))
                ->where('valid_to', '>', now())
                ->where('is_current', true)
                ->where('verification_status', 'verified')
                ->count();

            return [
                'success' => true,
                'message' => 'Statistics retrieved successfully',
                'data' => [
                    'total' => $total,
                    'verified' => $verified,
                    'expired' => $expired,
                    'pending' => $pending,
                    'current' => $current,
                    'expiring_soon' => $expiringSoon,
                    'by_type' => $byType,
                ],
                'status' => 200
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get credential statistics', [
                'staff_id' => $staffId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to retrieve statistics. Please try again later.',
                'data' => null,
                'status' => 500
            ];
        }
    }

    /**
     * Check if update is significant (requires superseding)
     *
     * @param StaffCredential $credential
     * @param array $data
     * @return bool
     */
    private function isSignificantUpdate(StaffCredential $credential, array $data): bool
    {
        $significantFields = [
            'credential_type',
            'credential_name',
            'credential_number_encrypted',
            'credential_number_hash',
            'issuing_authority',
            'issued_date',
            'valid_from',
            'valid_to',
            'credential_document_hash',
        ];

        foreach ($significantFields as $field) {
            if (array_key_exists($field, $data) && $data[$field] != $credential->$field) {
                return true;
            }
        }

        return false;
    }

    /**
     * Sanitize sensitive data for logging
     *
     * @param array $data
     * @return array
     */
    private function sanitizeLogData(array $data): array
    {
        $sensitiveFields = [
            'credential_number_encrypted',
            'credential_number_hash',
            'credential_document_hash',
            'document_storage_path',
        ];

        foreach ($sensitiveFields as $field) {
            if (isset($data[$field])) {
                $data[$field] = '[REDACTED]';
            }
        }

        return $data;
    }
}