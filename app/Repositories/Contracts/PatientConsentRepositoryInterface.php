<?php

namespace App\Repositories\Contracts;

use App\Models\PatientConsent;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface PatientConsentRepositoryInterface
{
    /**
     * Find a consent by its UUID.
     *
     * @param string $uuid
     * @return PatientConsent|null
     */
    public function findByUuid(string $uuid): ?PatientConsent;

    /**
     * Find active consent by patient and type.
     *
     * @param int $patientId
     * @param string $consentType
     * @return PatientConsent|null
     */
    public function findActiveConsent(int $patientId, string $consentType): ?PatientConsent;

    /**
     * Get all consents for a patient.
     *
     * @param int $patientId
     * @param array $filters
     * @return LengthAwarePaginator
     */
    public function getByPatient(int $patientId, array $filters = []): LengthAwarePaginator;

    /**
     * Get expiring consents.
     *
     * @param int $daysThreshold
     * @return Collection
     */
    public function getExpiringConsents(int $daysThreshold = 30): Collection;

    /**
     * Get revoked consents.
     *
     * @param array $filters
     * @return LengthAwarePaginator
     */
    public function getRevokedConsents(array $filters = []): LengthAwarePaginator;

    /**
     * Create a new consent.
     *
     * @param array $data
     * @return PatientConsent
     */
    public function create(array $data): PatientConsent;

    /**
     * Update a consent.
     *
     * @param PatientConsent $consent
     * @param array $data
     * @return bool
     */
    public function update(PatientConsent $consent, array $data): bool;

    /**
     * Revoke a consent.
     *
     * @param PatientConsent $consent
     * @param array $revocationData
     * @return bool
     */
    public function revoke(PatientConsent $consent, array $revocationData): bool;

    /**
     * Supersede a consent with a new one.
     *
     * @param PatientConsent $consent
     * @param array $newConsentData
     * @return PatientConsent
     */
    public function supersede(PatientConsent $consent, array $newConsentData): PatientConsent;

    /**
     * Check if consent is valid for specific scope.
     *
     * @param PatientConsent $consent
     * @param array $scopeCheck
     * @return bool
     */
    public function validateScope(PatientConsent $consent, array $scopeCheck): bool;

    /**
     * Get consent statistics.
     *
     * @param array $filters
     * @return array
     */
    public function getStatistics(array $filters = []): array;
}