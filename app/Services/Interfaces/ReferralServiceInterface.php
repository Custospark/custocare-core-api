<?php

namespace App\Services\Interfaces;

use App\Models\Referral;
use App\Http\Resources\ReferralResource;
use Illuminate\Http\Resources\Json\ResourceCollection;

interface ReferralServiceInterface
{
    /**
     * Get all referrals with pagination.
     *
     * @param array $filters
     * @param int $perPage
     * @return ResourceCollection
     */
    public function getAllReferrals(array $filters = [], int $perPage = 15): ResourceCollection;

    /**
     * Get referral by ID.
     *
     * @param int $id
     * @return ReferralResource
     */
    public function getReferralById(int $id): ReferralResource;

    /**
     * Get referral by UUID.
     *
     * @param string $uuid
     * @return ReferralResource
     */
    public function getReferralByUuid(string $uuid): ReferralResource;

    /**
     * Create a new referral.
     *
     * @param array $data
     * @return ReferralResource
     */
    public function createReferral(array $data): ReferralResource;

    /**
     * Update an existing referral.
     *
     * @param int $id
     * @param array $data
     * @return ReferralResource
     */
    public function updateReferral(int $id, array $data): ReferralResource;

    /**
     * Delete a referral.
     *
     * @param int $id
     * @return bool
     */
    public function deleteReferral(int $id): bool;

    /**
     * Accept a referral.
     *
     * @param int $id
     * @param int $receivingStaffId
     * @return ReferralResource
     */
    public function acceptReferral(int $id, int $receivingStaffId): ReferralResource;

    /**
     * Reject a referral.
     *
     * @param int $id
     * @param string|null $reason
     * @return ReferralResource
     */
    public function rejectReferral(int $id, ?string $reason = null): ReferralResource;

    /**
     * Complete a referral.
     *
     * @param int $id
     * @return ReferralResource
     */
    public function completeReferral(int $id): ReferralResource;

    /**
     * Cancel a referral.
     *
     * @param int $id
     * @param string|null $reason
     * @return ReferralResource
     */
    public function cancelReferral(int $id, ?string $reason = null): ReferralResource;

    /**
     * Get referrals for a specific patient.
     *
     * @param int $patientId
     * @param array $filters
     * @param int $perPage
     * @return ResourceCollection
     */
    public function getReferralsForPatient(int $patientId, array $filters = [], int $perPage = 15): ResourceCollection;

    /**
     * Get referrals for a specific facility.
     *
     * @param int $facilityId
     * @param array $filters
     * @param int $perPage
     * @return ResourceCollection
     */
    public function getReferralsForFacility(int $facilityId, array $filters = [], int $perPage = 15): ResourceCollection;

    /**
     * Get pending referrals.
     *
     * @param array $filters
     * @param int $perPage
     * @return ResourceCollection
     */
    public function getPendingReferrals(array $filters = [], int $perPage = 15): ResourceCollection;
}