<?php

namespace App\Services\Contracts;

use App\Models\ClinicalEncounter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;

interface ClinicalEncounterServiceInterface
{
    /**
     * Get all clinical encounters with pagination
     *
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getAllEncounters(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    /**
     * Get clinical encounter by UUID
     *
     * @param string $uuid
     * @return ClinicalEncounter
     */
    public function getEncounterByUuid(string $uuid): ClinicalEncounter;

    /**
     * Create a new clinical encounter
     *
     * @param array $data
     * @return ClinicalEncounter
     */
    public function createEncounter(array $data): ClinicalEncounter;

    /**
     * Update an existing clinical encounter
     *
     * @param string $uuid
     * @param array $data
     * @return ClinicalEncounter
     */
    public function updateEncounter(string $uuid, array $data): ClinicalEncounter;

    /**
     * Delete a clinical encounter
     *
     * @param string $uuid
     * @return bool
     */
    public function deleteEncounter(string $uuid): bool;

    /**
     * Restore a soft-deleted clinical encounter
     *
     * @param string $uuid
     * @return ClinicalEncounter
     */
    public function restoreEncounter(string $uuid): ClinicalEncounter;

    /**
     * Sign/complete a clinical encounter
     *
     * @param string $uuid
     * @param string $signatureHash
     * @return ClinicalEncounter
     */
    public function signEncounter(string $uuid, string $signatureHash): ClinicalEncounter;

    /**
     * Create an amendment to an existing encounter
     *
     * @param string $originalUuid
     * @param array $amendmentData
     * @param string $amendmentReason
     * @return ClinicalEncounter
     */
    public function createAmendment(string $originalUuid, array $amendmentData, string $amendmentReason): ClinicalEncounter;

    /**
     * Get encounters requiring immediate attention
     *
     * @param int $facilityId
     * @return Collection
     */
    public function getEncountersRequiringAttention(int $facilityId): Collection;

    /**
     * Get incomplete documentation encounters
     *
     * @param int $facilityId
     * @param int $daysThreshold
     * @return Collection
     */
    public function getIncompleteDocumentation(int $facilityId, int $daysThreshold = 3): Collection;

    /**
     * Validate clinical encounter data for completeness
     *
     * @param ClinicalEncounter $encounter
     * @return array Validation results
     */
    public function validateEncounterCompleteness(ClinicalEncounter $encounter): array;

    /**
     * Generate billing information for encounter
     *
     * @param ClinicalEncounter $encounter
     * @return array Billing details
     */
    public function generateBillingInformation(ClinicalEncounter $encounter): array;

    /**
     * Export encounter as structured document
     *
     * @param ClinicalEncounter $encounter
     * @param string $format
     * @return mixed
     */
    public function exportEncounter(ClinicalEncounter $encounter, string $format = 'pdf');
}