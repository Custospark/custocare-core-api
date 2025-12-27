<?php

namespace App\Services\Contracts;

use App\Models\PatientConsent;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface PatientConsentServiceInterface
{
    /**
     * Get consent by UUID.
     *
     * @param string $uuid
     * @return array
     */
    public function getConsentByUuid(string $uuid): array;

    /**
     * Get patient consents.
     *
     * @param int $patientId
     * @param array $filters
     * @return array
     */
    public function getPatientConsents(int $patientId, array $filters = []): array;

    /**
     * Create a new consent.
     *
     * @param array $data
     * @return array
     */
    public function createConsent(array $data): array;

    /**
     * Update a consent.
     *
     * @param string $uuid
     * @param array $data
     * @return array
     */
    public function updateConsent(string $uuid, array $data): array;

    /**
     * Revoke a consent.
     *
     * @param string $uuid
     * @param array $revocationData
     * @return array
     */
    public function revokeConsent(string $uuid, array $revocationData): array;

    /**
     * Validate consent for specific action.
     *
     * @param int $patientId
     * @param string $consentType
     * @param array $scopeCheck
     * @return array
     */
    public function validateConsent(int $patientId, string $consentType, array $scopeCheck = []): array;

    /**
     * Get expiring consents.
     *
     * @param int $daysThreshold
     * @return array
     */
    public function getExpiringConsents(int $daysThreshold = 30): array;

    /**
     * Get consent statistics.
     *
     * @param array $filters
     * @return array
     */
    public function getStatistics(array $filters = []): array;

    /**
     * Check if patient has valid consent for type.
     *
     * @param int $patientId
     * @param string $consentType
     * @return bool
     */
    public function hasValidConsent(int $patientId, string $consentType): bool;

    /**
     * Get consent types with descriptions.
     *
     * @return array
     */
    public function getConsentTypes(): array;

    /**
     * Get legal basis options.
     *
     * @return array
     */
    public function getLegalBasisOptions(): array;
}