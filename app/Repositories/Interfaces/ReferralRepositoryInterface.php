<?php

namespace App\Repositories\Interfaces;

use App\Models\Referral;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ReferralRepositoryInterface
{
    /**
     * Get all referrals with pagination.
     *
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getAllReferrals(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    /**
     * Get referral by ID.
     *
     * @param int $id
     * @return Referral
     */
    public function getReferralById(int $id): Referral;

    /**
     * Get referral by UUID.
     *
     * @param string $uuid
     * @return Referral
     */
    public function getReferralByUuid(string $uuid): Referral;

    /**
     * Create a new referral.
     *
     * @param array $data
     * @return Referral
     */
    public function createReferral(array $data): Referral;

    /**
     * Update an existing referral.
     *
     * @param int $id
     * @param array $data
     * @return Referral
     */
    public function updateReferral(int $id, array $data): Referral;

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
     * @return Referral
     */
    public function acceptReferral(int $id, int $receivingStaffId): Referral;

    /**
     * Reject a referral.
     *
     * @param int $id
     * @param string|null $reason
     * @return Referral
     */
    public function rejectReferral(int $id, ?string $reason = null): Referral;

    /**
     * Complete a referral.
     *
     * @param int $id
     * @return Referral
     */
    public function completeReferral(int $id): Referral;

    /**
     * Cancel a referral.
     *
     * @param int $id
     * @param string|null $reason
     * @return Referral
     */
    public function cancelReferral(int $id, ?string $reason = null): Referral;

    /**
     * Get referrals for a specific patient.
     *
     * @param int $patientId
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getReferralsForPatient(int $patientId, array $filters = [], int $perPage = 15): LengthAwarePaginator;

    /**
     * Get referrals involving a facility (both source and destination).
     *
     * @param int $facilityId
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getReferralsForFacility(int $facilityId, array $filters = [], int $perPage = 15): LengthAwarePaginator;

    /**
     * Get referrals originating from a specific facility.
     *
     * @param int $facilityId
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getReferralsFromFacility(int $facilityId, array $filters = [], int $perPage = 15): LengthAwarePaginator;

    /**
     * Get referrals destined for a specific facility.
     *
     * @param int $facilityId
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getReferralsToFacility(int $facilityId, array $filters = [], int $perPage = 15): LengthAwarePaginator;

    /**
     * Get pending referrals.
     *
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getPendingReferrals(array $filters = [], int $perPage = 15): LengthAwarePaginator;
}