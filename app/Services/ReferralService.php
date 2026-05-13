<?php

namespace App\Services;

use App\Models\Referral;
use App\Http\Resources\ReferralResource;
use App\Http\Resources\ReferralCollection;
use App\Services\Interfaces\ReferralServiceInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class ReferralService implements ReferralServiceInterface
{
    /**
     * Get all referrals with pagination.
     */
    public function getAllReferrals(array $filters = [], int $perPage = 15): ReferralCollection
    {
        $query = Referral::query()
            ->with(['patient', 'facility', 'referringStaff', 'receivingStaff', 'createdBy', 'updatedBy'])
            ->whereNull('deleted_at');

        // Apply filters
        $this->applyFilters($query, $filters);

        return new ReferralCollection($query->paginate($perPage));
    }

    /**
     * Get referral by ID.
     */
    public function getReferralById(int $id): ReferralResource
    {
        $referral = Referral::with(['patient', 'facility', 'referringStaff', 'receivingStaff', 'createdBy', 'updatedBy'])
            ->findOrFail($id);

        return new ReferralResource($referral);
    }

    /**
     * Get referral by UUID.
     */
    public function getReferralByUuid(string $uuid): ReferralResource
    {
        $referral = Referral::with(['patient', 'facility', 'referringStaff', 'receivingStaff', 'createdBy', 'updatedBy'])
            ->where('referral_uuid', $uuid)
            ->firstOrFail();

        return new ReferralResource($referral);
    }

    /**
     * Create a new referral.
     */
    public function createReferral(array $data): ReferralResource
    {
        // Set created_by_staff_id if not provided
        if (!isset($data['created_by_staff_id'])) {
            $data['created_by_staff_id'] = Auth::id() ?? 1; // Fallback to system user if not authenticated
        }

        $referral = Referral::create($data);
        $referral->load(['patient', 'facility', 'referringStaff', 'receivingStaff', 'createdBy', 'updatedBy']);

        return new ReferralResource($referral);
    }

    /**
     * Update an existing referral.
     */
    public function updateReferral(int $id, array $data): ReferralResource
    {
        $referral = Referral::findOrFail($id);
        
        // Set updated_by_staff_id if not provided
        if (!isset($data['updated_by_staff_id'])) {
            $data['updated_by_staff_id'] = Auth::id() ?? 1; // Fallback to system user if not authenticated
        }

        $referral->update($data);
        $referral->load(['patient', 'facility', 'referringStaff', 'receivingStaff', 'createdBy', 'updatedBy']);

        return new ReferralResource($referral);
    }

    /**
     * Delete a referral.
     */
    public function deleteReferral(int $id): bool
    {
        $referral = Referral::findOrFail($id);
        return $referral->delete();
    }

    /**
     * Accept a referral.
     */
    public function acceptReferral(int $id, int $receivingStaffId): ReferralResource
    {
        $referral = Referral::findOrFail($id);
        $referral->accept($receivingStaffId);
        $referral->load(['patient', 'facility', 'referringStaff', 'receivingStaff', 'createdBy', 'updatedBy']);

        return new ReferralResource($referral);
    }

    /**
     * Reject a referral.
     */
    public function rejectReferral(int $id, ?string $reason = null): ReferralResource
    {
        $referral = Referral::findOrFail($id);
        $referral->reject($reason);
        $referral->load(['patient', 'facility', 'referringStaff', 'receivingStaff', 'createdBy', 'updatedBy']);

        return new ReferralResource($referral);
    }

    /**
     * Complete a referral.
     */
    public function completeReferral(int $id): ReferralResource
    {
        $referral = Referral::findOrFail($id);
        $referral->complete();
        $referral->load(['patient', 'facility', 'referringStaff', 'receivingStaff', 'createdBy', 'updatedBy']);

        return new ReferralResource($referral);
    }

    /**
     * Cancel a referral.
     */
    public function cancelReferral(int $id, ?string $reason = null): ReferralResource
    {
        $referral = Referral::findOrFail($id);
        $referral->cancel($reason);
        $referral->load(['patient', 'facility', 'referringStaff', 'receivingStaff', 'createdBy', 'updatedBy']);

        return new ReferralResource($referral);
    }

    /**
     * Get referrals for a specific patient.
     */
    public function getReferralsForPatient(int $patientId, array $filters = [], int $perPage = 15): ReferralCollection
    {
        $query = Referral::query()
            ->with(['patient', 'facility', 'referringStaff', 'receivingStaff', 'createdBy', 'updatedBy'])
            ->whereNull('deleted_at')
            ->where('patient_id', $patientId);

        // Apply filters
        $this->applyFilters($query, $filters);

        return new ReferralCollection($query->paginate($perPage));
    }

    /**
     * Get referrals for a specific facility.
     */
    public function getReferralsForFacility(int $facilityId, array $filters = [], int $perPage = 15): ReferralCollection
    {
        $query = Referral::query()
            ->with(['patient', 'facility', 'referringStaff', 'receivingStaff', 'createdBy', 'updatedBy'])
            ->whereNull('deleted_at')
            ->where('facility_id', $facilityId);

        // Apply filters
        $this->applyFilters($query, $filters);

        return new ReferralCollection($query->paginate($perPage));
    }

    /**
     * Get pending referrals.
     */
    public function getPendingReferrals(array $filters = [], int $perPage = 15): ReferralCollection
    {
        $query = Referral::query()
            ->with(['patient', 'facility', 'referringStaff', 'receivingStaff', 'createdBy', 'updatedBy'])
            ->whereNull('deleted_at')
            ->where('status', 'pending');

        // Apply filters
        $this->applyFilters($query, $filters);

        return new ReferralCollection($query->paginate($perPage));
    }

    /**
     * Apply filters to the query.
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }

        if (isset($filters['referral_type'])) {
            $query->where('referral_type', $filters['referral_type']);
        }

        if (isset($filters['referring_staff_id'])) {
            $query->where('referring_staff_id', $filters['referring_staff_id']);
        }

        if (isset($filters['receiving_staff_id'])) {
            $query->where('receiving_staff_id', $filters['receiving_staff_id']);
        }

        if (isset($filters['from_date'])) {
            $query->whereDate('referral_date', '>=', $filters['from_date']);
        }

        if (isset($filters['to_date'])) {
            $query->whereDate('referral_date', '<=', $filters['to_date']);
        }

        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('referral_reason', 'like', "%{$search}%")
                  ->orWhere('clinical_notes', 'like', "%{$search}%")
                  ->orWhereHas('patient', function ($qp) use ($search) {
                      $qp->whereHas('user', function ($qu) use ($search) {
                          $qu->where('first_name', 'like', "%{$search}%")
                             ->orWhere('last_name', 'like', "%{$search}%");
                      });
                  })
                  ->orWhereHas('facility', function ($qp) use ($search) {
                      $qp->where('facility_name', 'like', "%{$search}%");
                  });
            });
        }
    }
}