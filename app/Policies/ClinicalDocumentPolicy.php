<?php

namespace App\Policies;

use App\Models\ClinicalDocument;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ClinicalDocumentPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        // Only staff members can view clinical documents
        return $user->hasRole('staff') || $user->hasRole('admin') || $user->hasRole('clinician');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, ClinicalDocument $clinicalDocument): bool
    {
        // Staff can view if they belong to the same facility
        if ($user->hasRole('staff') || $user->hasRole('clinician')) {
            return $user->facility_id === $clinicalDocument->facility_id;
        }
        
        // Admin can view all
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // Only clinical staff and admin can create documents
        return $user->hasRole('clinician') || $user->hasRole('admin');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, ClinicalDocument $clinicalDocument): bool
    {
        // Only the uploader, clinical staff in same facility, or admin can update
        if ($user->hasRole('admin')) {
            return true;
        }
        
        if ($user->hasRole('clinician')) {
            return $user->facility_id === $clinicalDocument->facility_id;
        }
        
        // Uploader can update their own documents
        return $user->id === $clinicalDocument->uploaded_by_staff_id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ClinicalDocument $clinicalDocument): bool
    {
        // Only admin can permanently delete
        // Regular staff can only mark as "entered_in_error"
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, ClinicalDocument $clinicalDocument): bool
    {
        // Only admin can restore
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, ClinicalDocument $clinicalDocument): bool
    {
        // Only admin can force delete
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can download the document.
     */
    public function download(User $user, ClinicalDocument $clinicalDocument): bool
    {
        // Users can download if they can view
        return $this->view($user, $clinicalDocument);
    }

    /**
     * Determine whether the user can update document status.
     */
    public function updateStatus(User $user, ClinicalDocument $clinicalDocument): bool
    {
        // Only clinical staff and admin can update status
        if ($user->hasRole('admin')) {
            return true;
        }
        
        return $user->hasRole('clinician') && 
               $user->facility_id === $clinicalDocument->facility_id;
    }
}