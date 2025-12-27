<?php

namespace App\Policies;

use App\Models\Facility;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Class FacilityPolicy
 * 
 * Authorization policy for Facility model.
 * Defines who can perform which actions on Facility resources.
 */
class FacilityPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     *
     * @param User $user
     * @return bool
     */
    public function viewAny(User $user): bool
    {
        // All authenticated users can view facilities (reference data)
        return $user !== null;
    }

    /**
     * Determine whether the user can view the model.
     *
     * @param User $user
     * @param Facility $facility
     * @return bool
     */
    public function view(User $user, Facility $facility): bool
    {
        // All authenticated users can view individual facilities
        return $user !== null;
    }

    /**
     * Determine whether the user can create models.
     *
     * @param User $user
     * @return bool
     */
    public function create(User $user): bool
    {
        // Only system administrators and facility managers can create facilities
        return $user->hasAnyRole(['system_administrator', 'facility_manager', 'regional_director']);
    }

    /**
     * Determine whether the user can update the model.
     *
     * @param User $user
     * @param Facility $facility
     * @return bool
     */
    public function update(User $user, Facility $facility): bool
    {
        // System administrators can update any facility
        if ($user->hasRole('system_administrator')) {
            return true;
        }
        
        // Regional directors can update facilities in their region
        if ($user->hasRole('regional_director')) {
            return $this->isFacilityInUserRegion($user, $facility);
        }
        
        // Facility managers can only update their assigned facility
        if ($user->hasRole('facility_manager')) {
            return $this->isUserFacilityManager($user, $facility);
        }
        
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @param User $user
     * @param Facility $facility
     * @return bool
     */
    public function delete(User $user, Facility $facility): bool
    {
        // Only system administrators can delete facilities
        return $user->hasRole('system_administrator');
    }

    /**
     * Determine whether the user can restore the model.
     *
     * @param User $user
     * @param Facility $facility
     * @return bool
     */
    public function restore(User $user, Facility $facility): bool
    {
        // Only system administrators can restore deleted facilities
        return $user->hasRole('system_administrator');
    }

    /**
     * Determine whether the user can permanently delete the model.
     *
     * @param User $user
     * @param Facility $facility
     * @return bool
     */
    public function forceDelete(User $user, Facility $facility): bool
    {
        // Only system administrators can permanently delete facilities
        return $user->hasRole('system_administrator');
    }

    /**
     * Determine whether the user can update facility metrics.
     *
     * @param User $user
     * @param Facility $facility
     * @return bool
     */
    public function updateMetrics(User $user, Facility $facility): bool
    {
        // System administrators, regional directors, and facility managers can update metrics
        return $user->hasAnyRole(['system_administrator', 'regional_director', 'facility_manager']) 
            && ($user->hasRole('system_administrator') 
                || $this->isFacilityInUserRegion($user, $facility) 
                || $this->isUserFacilityManager($user, $facility));
    }

    /**
     * Determine whether the user can view operational status.
     *
     * @param User $user
     * @param Facility $facility
     * @return bool
     */
    public function viewOperationalStatus(User $user, Facility $facility): bool
    {
        // All authenticated users can view operational status
        return $user !== null;
    }

    /**
     * Determine whether the user can update operational status.
     *
     * @param User $user
     * @param Facility $facility
     * @return bool
     */
    public function updateOperationalStatus(User $user, Facility $facility): bool
    {
        // Only system administrators and regional directors can update operational status
        return $user->hasAnyRole(['system_administrator', 'regional_director'])
            && ($user->hasRole('system_administrator') 
                || $this->isFacilityInUserRegion($user, $facility));
    }

    /**
     * Check if facility is in user's assigned region.
     *
     * @param User $user
     * @param Facility $facility
     * @return bool
     */
    private function isFacilityInUserRegion(User $user, Facility $facility): bool
    {
        // Assuming user has a 'region' attribute or relationship
        if ($user->region) {
            return $facility->data_residency_region === $user->region;
        }
        
        return false;
    }

    /**
     * Check if user is manager of the facility.
     *
     * @param User $user
     * @param Facility $facility
     * @return bool
     */
    private function isUserFacilityManager(User $user, Facility $facility): bool
    {
        // Assuming user has a 'managed_facility_id' attribute
        return $user->managed_facility_id === $facility->id;
    }
}