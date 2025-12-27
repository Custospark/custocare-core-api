<?php

namespace App\Services\PatientConsent;

use App\Models\PatientConsent;
use App\Repositories\Contracts\PatientConsentRepositoryInterface;
use App\Services\Contracts\PatientConsentServiceInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class PatientConsentService implements PatientConsentServiceInterface
{
    /**
     * Repository instance.
     *
     * @var PatientConsentRepositoryInterface
     */
    protected PatientConsentRepositoryInterface $repository;

    /**
     * Create a new service instance.
     *
     * @param PatientConsentRepositoryInterface $repository
     */
    public function __construct(PatientConsentRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Get consent by UUID.
     *
     * @param string $uuid
     * @return array
     */
    public function getConsentByUuid(string $uuid): array
    {
        try {
            $consent = $this->repository->findByUuid($uuid);
            
            if (!$consent) {
                return [
                    'success' => false,
                    'message' => 'Consent not found',
                    'data' => null,
                    'status' => 404
                ];
            }

            return [
                'success' => true,
                'message' => 'Consent retrieved successfully',
                'data' => $consent,
                'status' => 200
            ];
        } catch (\Exception $e) {
            Log::error('Error retrieving consent', [
                'uuid' => $uuid,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'An error occurred while retrieving consent',
                'data' => null,
                'status' => 500
            ];
        }
    }

    /**
     * Get patient consents.
     *
     * @param int $patientId
     * @param array $filters
     * @return array
     */
    public function getPatientConsents(int $patientId, array $filters = []): array
    {
        try {
            $consents = $this->repository->getByPatient($patientId, $filters);

            return [
                'success' => true,
                'message' => 'Patient consents retrieved successfully',
                'data' => $consents,
                'status' => 200
            ];
        } catch (\Exception $e) {
            Log::error('Error retrieving patient consents', [
                'patient_id' => $patientId,
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'An error occurred while retrieving patient consents',
                'data' => [],
                'status' => 500
            ];
        }
    }

    /**
     * Create a new consent.
     *
     * @param array $data
     * @return array
     */
    public function createConsent(array $data): array
    {
        try {
            // Validate required fields
            $validator = Validator::make($data, [
                'patient_id' => 'required|exists:patients,id',
                'consent_type' => 'required|in:' . implode(',', array_keys(PatientConsent::getConsentTypes())),
                'legal_basis' => 'required|in:' . implode(',', array_keys(PatientConsent::getLegalBasisOptions())),
                'granted_at' => 'required|date',
                'effective_from' => 'required|date|after_or_equal:granted_at',
                'consent_form_version' => 'required|string|max:20',
                'consent_document_hash' => 'required|string|size:64', // SHA-256
                'patient_signature_hash' => 'required|string|size:128',
            ]);

            if ($validator->fails()) {
                return [
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()->toArray(),
                    'status' => 422
                ];
            }

            // Check for existing active consent of same type
            $existingConsent = $this->repository->findActiveConsent($data['patient_id'], $data['consent_type']);
            
            if ($existingConsent) {
                return [
                    'success' => false,
                    'message' => 'Patient already has an active consent of this type',
                    'data' => $existingConsent,
                    'status' => 409
                ];
            }

            // Ensure granted_at is not in the future
            if (strtotime($data['granted_at']) > time()) {
                return [
                    'success' => false,
                    'message' => 'Granted date cannot be in the future',
                    'errors' => ['granted_at' => ['Granted date cannot be in the future']],
                    'status' => 422
                ];
            }

            // Set default status
            $data['status'] = 'active';

            // Create the consent
            $consent = $this->repository->create($data);

            return [
                'success' => true,
                'message' => 'Consent created successfully',
                'data' => $consent,
                'status' => 201
            ];
        } catch (\Exception $e) {
            Log::error('Error creating consent', [
                'data' => $data,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'An error occurred while creating consent',
                'data' => null,
                'status' => 500
            ];
        }
    }

    /**
     * Update a consent.
     *
     * @param string $uuid
     * @param array $data
     * @return array
     */
    public function updateConsent(string $uuid, array $data): array
    {
        try {
            $consent = $this->repository->findByUuid($uuid);
            
            if (!$consent) {
                return [
                    'success' => false,
                    'message' => 'Consent not found',
                    'data' => null,
                    'status' => 404
                ];
            }

            // Cannot update revoked or superseded consents
            if ($consent->isRevoked() || $consent->status === 'superseded') {
                return [
                    'success' => false,
                    'message' => 'Cannot update a revoked or superseded consent',
                    'data' => null,
                    'status' => 403
                ];
            }

            // Validate update data
            $validator = Validator::make($data, [
                'expires_at' => 'nullable|date|after:effective_from',
                'scope_limitations' => 'nullable|string|max:1000',
                'revocation_reason' => 'nullable|string|max:500',
                'metadata' => 'nullable|array',
            ]);

            if ($validator->fails()) {
                return [
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()->toArray(),
                    'status' => 422
                ];
            }

            // Prevent certain fields from being updated
            unset($data['consent_uuid'], $data['patient_id'], $data['consent_type'], 
                  $data['granted_at'], $data['effective_from'], $data['patient_signature_hash'],
                  $data['consent_document_hash']);

            $updated = $this->repository->update($consent, $data);

            if (!$updated) {
                return [
                    'success' => false,
                    'message' => 'Failed to update consent',
                    'data' => null,
                    'status' => 500
                ];
            }

            return [
                'success' => true,
                'message' => 'Consent updated successfully',
                'data' => $consent->fresh(),
                'status' => 200
            ];
        } catch (\Exception $e) {
            Log::error('Error updating consent', [
                'uuid' => $uuid,
                'data' => $data,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'An error occurred while updating consent',
                'data' => null,
                'status' => 500
            ];
        }
    }

    /**
     * Revoke a consent.
     *
     * @param string $uuid
     * @param array $revocationData
     * @return array
     */
    public function revokeConsent(string $uuid, array $revocationData): array
    {
        try {
            $consent = $this->repository->findByUuid($uuid);
            
            if (!$consent) {
                return [
                    'success' => false,
                    'message' => 'Consent not found',
                    'data' => null,
                    'status' => 404
                ];
            }

            // Check if already revoked
            if ($consent->isRevoked()) {
                return [
                    'success' => false,
                    'message' => 'Consent is already revoked',
                    'data' => $consent,
                    'status' => 409
                ];
            }

            // Validate revocation data
            $validator = Validator::make($revocationData, [
                'revocation_reason' => 'required|string|max:500',
                'revoked_by_staff_id' => 'required|exists:staff,id',
            ]);

            if ($validator->fails()) {
                return [
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()->toArray(),
                    'status' => 422
                ];
            }

            $revoked = $this->repository->revoke($consent, $revocationData);

            if (!$revoked) {
                return [
                    'success' => false,
                    'message' => 'Failed to revoke consent',
                    'data' => null,
                    'status' => 500
                ];
            }

            return [
                'success' => true,
                'message' => 'Consent revoked successfully',
                'data' => $consent->fresh(),
                'status' => 200
            ];
        } catch (\Exception $e) {
            Log::error('Error revoking consent', [
                'uuid' => $uuid,
                'revocation_data' => $revocationData,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'An error occurred while revoking consent',
                'data' => null,
                'status' => 500
            ];
        }
    }

    /**
     * Validate consent for specific action.
     *
     * @param int $patientId
     * @param string $consentType
     * @param array $scopeCheck
     * @return array
     */
    public function validateConsent(int $patientId, string $consentType, array $scopeCheck = []): array
    {
        try {
            $consent = $this->repository->findActiveConsent($patientId, $consentType);
            
            if (!$consent) {
                return [
                    'success' => false,
                    'message' => 'No active consent found for this type',
                    'valid' => false,
                    'consent' => null,
                    'status' => 404
                ];
            }

            // Validate scope if provided
            $scopeValid = true;
            if (!empty($scopeCheck)) {
                $scopeValid = $this->repository->validateScope($consent, $scopeCheck);
            }

            if (!$scopeValid) {
                return [
                    'success' => false,
                    'message' => 'Consent does not cover the requested scope',
                    'valid' => false,
                    'consent' => $consent,
                    'status' => 403
                ];
            }

            return [
                'success' => true,
                'message' => 'Consent is valid',
                'valid' => true,
                'consent' => $consent,
                'status' => 200
            ];
        } catch (\Exception $e) {
            Log::error('Error validating consent', [
                'patient_id' => $patientId,
                'consent_type' => $consentType,
                'scope_check' => $scopeCheck,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'An error occurred while validating consent',
                'valid' => false,
                'consent' => null,
                'status' => 500
            ];
        }
    }

    /**
     * Get expiring consents.
     *
     * @param int $daysThreshold
     * @return array
     */
    public function getExpiringConsents(int $daysThreshold = 30): array
    {
        try {
            $consents = $this->repository->getExpiringConsents($daysThreshold);

            return [
                'success' => true,
                'message' => 'Expiring consents retrieved successfully',
                'data' => $consents,
                'count' => $consents->count(),
                'status' => 200
            ];
        } catch (\Exception $e) {
            Log::error('Error retrieving expiring consents', [
                'days_threshold' => $daysThreshold,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'An error occurred while retrieving expiring consents',
                'data' => [],
                'count' => 0,
                'status' => 500
            ];
        }
    }

    /**
     * Get consent statistics.
     *
     * @param array $filters
     * @return array
     */
    public function getStatistics(array $filters = []): array
    {
        try {
            $statistics = $this->repository->getStatistics($filters);

            return [
                'success' => true,
                'message' => 'Consent statistics retrieved successfully',
                'data' => $statistics,
                'status' => 200
            ];
        } catch (\Exception $e) {
            Log::error('Error retrieving consent statistics', [
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'An error occurred while retrieving consent statistics',
                'data' => [],
                'status' => 500
            ];
        }
    }

    /**
     * Check if patient has valid consent for type.
     *
     * @param int $patientId
     * @param string $consentType
     * @return bool
     */
    public function hasValidConsent(int $patientId, string $consentType): bool
    {
        try {
            $consent = $this->repository->findActiveConsent($patientId, $consentType);
            return $consent !== null;
        } catch (\Exception $e) {
            Log::error('Error checking valid consent', [
                'patient_id' => $patientId,
                'consent_type' => $consentType,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get consent types with descriptions.
     *
     * @return array
     */
    public function getConsentTypes(): array
    {
        return PatientConsent::getConsentTypes();
    }

    /**
     * Get legal basis options.
     *
     * @return array
     */
    public function getLegalBasisOptions(): array
    {
        return PatientConsent::getLegalBasisOptions();
    }
}