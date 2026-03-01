<?php

namespace App\Services\Contracts;

use App\Models\Facility;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;


/**
 * Interface FacilityServiceInterface
 * 
 * Contract for Facility business logic layer.
 * Defines all business operations for Facility entity.
 */
interface FacilityServiceInterface
{
    /**
     * Get all facilities with optional filters.
     *
     * @param array $filters
     * @return Collection
     */
    public function getAllFacilities(array $filters = []): Collection;

    /**
     * Get paginated facilities with optional filters.
     *
     * @param int $perPage
     * @param array $filters
     * @return LengthAwarePaginator
     */
    public function getPaginatedFacilities(int $perPage = 15, array $filters = []): LengthAwarePaginator;

    /**
     * Get facility by ID.
     *
     * @param int $id
     * @return Facility|null
     */
    public function getFacilityById(int $id): ?Facility;

    /**
     * Get facility by UUID.
     *
     * @param string $uuid
     * @return Facility|null
     */
    public function getFacilityByUuid(string $uuid): ?Facility;

    /**
     * Get facility by code.
     *
     * @param string $facilityCode
     * @return Facility|null
     */
    public function getFacilityByCode(string $facilityCode): ?Facility;

    /**
     * Create a new facility.
     *
     * @param array $data
     * @param int $createdByStaffId
     * @return array
     */
    public function createFacility(array $data, int $createdByStaffId): ?Facility;

    /**
     * Facility Creation by at User onboarding and automatic role Assignment by system as Facility Administrator.
     */
    public function createFacilityByAdmin(array $data, int $createdByStaffId): ?Facility;

    /**
     * Update an existing facility.
     *
     * @param int $id
     * @param array $data
     * @param int $updatedByStaffId
     * @return array
     */
    public function updateFacility(int $id, array $data, int $updatedByStaffId): array;

    /**
     * Delete a facility (soft delete).
     *
     * @param int $id
     * @return array
     */
    public function deleteFacility(int $id): array;

    /**
     * Force delete a facility.
     *
     * @param int $id
     * @return array
     */
    public function forceDeleteFacility(int $id): array;

    /**
     * Restore a soft-deleted facility.
     *
     * @param int $id
     * @return array
     */
    public function restoreFacility(int $id): array;

    /**
     * Validate facility data.
     *
     * @param array $data
     * @param int|null $excludeId
     * @return array
     */
    public function validateFacilityData(array $data, ?int $excludeId = null): array;

    /**
     * Get facilities by location.
     *
     * @param string $countryCode
     * @param string|null $stateProvince
     * @param string|null $city
     * @return Collection
     */
    public function getFacilitiesByLocation(string $countryCode, ?string $stateProvince = null, ?string $city = null): Collection;

    /**
     * Get facilities by type and status.
     *
     * @param string $type
     * @param string $status
     * @return Collection
     */
    public function getFacilitiesByTypeAndStatus(string $type, string $status): Collection;

    /**
     * Search facilities by name or code.
     *
     * @param string $query
     * @param int $limit
     * @return Collection
     */
    public function searchFacilities(string $query, int $limit = 10): Collection;

    /**
     * Get facilities with emergency departments.
     *
     * @param array $filters
     * @return Collection
     */
    public function getFacilitiesWithEmergencyDepartments(array $filters = []): Collection;

    /**
     * Update facility metrics.
     *
     * @param int $id
     * @param array $metrics
     * @return array
     */
    public function updateFacilityMetrics(int $id, array $metrics): array;

    /**
     * Check facility operational status.
     *
     * @param int $id
     * @return array
     */
    public function checkFacilityOperationalStatus(int $id): array;

    /**
     * Retrieve all editable settings fields for the given facility.
     *
     * The return value mirrors the logical grouping defined in the settings
     * specification so the client can render each section independently.
     *
     * Boolean fields are explicitly cast to avoid returning 0/1 integers.
     * The Branding group includes a computed `facility_logo_url` alongside
     * the raw `facility_logo_path` for convenience.
     *
     * @param Facility $facility
     * @return array<string, array<string, mixed>>
     */
    public function getFacilitySettings(Facility $facility): array;

    /**
     * Update one or more editable settings fields for the given facility.
     *
     * Only keys that are both present in $data AND present in the explicit
     * $allowed whitelist are persisted. Any extra keys (e.g. facility_uuid,
     * facility_code, shard columns, audit stamps) are silently discarded even
     * if somehow included in $data, providing a defence-in-depth guard on top
     * of the request-level validation.
     *
     * `facility_logo_path` is deliberately absent from $allowed — logo writes
     * must go through uploadFacilityLogo() so the old file is always cleaned up.
     *
     * Returns a fresh Facility instance reflecting the saved state.
     *
     * @param Facility $facility
     * @param array<string, mixed> $data Validated payload from UpdateFacilitySettingsRequest
     * @return Facility
     */
    public function updateFacilitySettings(Facility $facility, array $data): Facility;

    /**
     * Upload (or replace) the logo image for the given facility.
     *
     * Workflow:
     *   1. If a previous logo path is stored, delete the old file from the
     *      public disk so storage does not accumulate orphaned images.
     *   2. Store the uploaded file under `facility-logos/{facility->id}/`
     *      on the public disk, letting Laravel generate a unique filename.
     *   3. Persist the resulting relative path to `facility_logo_path` and
     *      save the model.
     *   4. Return the relative path to the caller (the controller appends
     *      the public URL for the response).
     *
     * @param Facility $facility
     * @param UploadedFile $logo
     * @return string Relative storage path (e.g. "facility-logos/7/abc123.jpg")
     *
     * @throws \Exception Re-thrown after logging so the controller can surface it.
     */
    public function uploadFacilityLogo(Facility $facility, UploadedFile $logo): string;
}