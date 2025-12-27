<?php

namespace App\Policies;

use App\Models\User;
use App\Models\AiAssessment;
use Illuminate\Auth\Access\HandlesAuthorization;

class AiAssessmentPolicy
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
        return $user->hasPermission('view ai_assessments') || 
               $user->hasRole(['clinician', 'admin', 'ai_specialist']);
    }

    /**
     * Determine whether the user can view the model.
     *
     * @param User $user
     * @param AiAssessment $aiAssessment
     * @return bool
     */
    public function view(User $user, AiAssessment $aiAssessment): bool
    {
        // Users can view if they have permission or belong to the same facility
        if ($user->hasPermission('view ai_assessments')) {
            return true;
        }

        // Clinicians can view assessments for their patients
        if ($user->hasRole('clinician')) {
            return $user->facility_id === $aiAssessment->facility_id;
        }

        // AI specialists can view assessments they created or need to review
        if ($user->hasRole('ai_specialist')) {
            return $user->facility_id === $aiAssessment->facility_id ||
                   $aiAssessment->reviewed_by_staff_id === $user->id;
        }

        return false;
    }

    /**
     * Determine whether the user can create models.
     *
     * @param User $user
     * @return bool
     */
    public function create(User $user): bool
    {
        return $user->hasPermission('create ai_assessments') || 
               $user->hasRole(['ai_specialist', 'admin']);
    }

    /**
     * Determine whether the user can update the model.
     *
     * @param User $user
     * @param AiAssessment $aiAssessment
     * @return bool
     */
    public function update(User $user, AiAssessment $aiAssessment): bool
    {
        // Admin can update any assessment
        if ($user->hasRole('admin')) {
            return true;
        }

        // Only allow updates if assessment is not locked
        if ($aiAssessment->clinical_outcome_recorded) {
            return false;
        }

        // AI specialists can update assessments they created or need to review
        if ($user->hasRole('ai_specialist')) {
            return $user->facility_id === $aiAssessment->facility_id &&
                   !$aiAssessment->human_review_status->isCompleted();
        }

        // Reviewers can update during review process
        if ($aiAssessment->human_review_status->value === 'pending_review' && 
            $user->hasPermission('review ai_assessments')) {
            return $user->facility_id === $aiAssessment->facility_id;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @param User $user
     * @param AiAssessment $aiAssessment
     * @return bool
     */
    public function delete(User $user, AiAssessment $aiAssessment): bool
    {
        // Only admin can delete, and only if no clinical outcome recorded
        if ($user->hasRole('admin') && !$aiAssessment->clinical_outcome_recorded) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can restore the model.
     *
     * @param User $user
     * @param AiAssessment $aiAssessment
     * @return bool
     */
    public function restore(User $user, AiAssessment $aiAssessment): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can permanently delete the model.
     *
     * @param User $user
     * @param AiAssessment $aiAssessment
     * @return bool
     */
    public function forceDelete(User $user, AiAssessment $aiAssessment): bool
    {
        // Only super admin can permanently delete, and only after retention period
        return $user->hasRole('super_admin') && 
               $aiAssessment->deleted_at->diffInDays(now()) > 365;
    }

    /**
     * Determine whether the user can review the model.
     *
     * @param User $user
     * @param AiAssessment $aiAssessment
     * @return bool
     */
    public function review(User $user, AiAssessment $aiAssessment): bool
    {
        // User must have review permission and belong to same facility
        if (!$user->hasPermission('review ai_assessments')) {
            return false;
        }

        // Cannot review if already completed
        if ($aiAssessment->human_review_status->isCompleted()) {
            return false;
        }

        // Must belong to same facility
        return $user->facility_id === $aiAssessment->facility_id;
    }

    /**
     * Determine whether the user can record clinical outcome.
     *
     * @param User $user
     * @param AiAssessment $aiAssessment
     * @return bool
     */
    public function recordOutcome(User $user, AiAssessment $aiAssessment): bool
    {
        // Only clinicians and admin can record outcomes
        if (!$user->hasRole(['clinician', 'admin'])) {
            return false;
        }

        // Cannot record outcome if already recorded
        if ($aiAssessment->clinical_outcome_recorded) {
            return false;
        }

        // Must belong to same facility
        return $user->facility_id === $aiAssessment->facility_id;
    }

    /**
     * Determine whether the user can flag adverse events.
     *
     * @param User $user
     * @param AiAssessment $aiAssessment
     * @return bool
     */
    public function flagAdverseEvent(User $user, AiAssessment $aiAssessment): bool
    {
        // Clinicians, safety officers, and admin can flag adverse events
        if (!$user->hasRole(['clinician', 'safety_officer', 'admin'])) {
            return false;
        }

        // Cannot flag if already flagged
        if ($aiAssessment->adverse_event_flagged) {
            return false;
        }

        // Must belong to same facility
        return $user->facility_id === $aiAssessment->facility_id;
    }

    /**
     * Determine whether the user can export assessments.
     *
     * @param User $user
     * @return bool
     */
    public function export(User $user): bool
    {
        return $user->hasPermission('export ai_assessments') || 
               $user->hasRole(['admin', 'researcher']);
    }

    /**
     * Determine whether the user can view statistics.
     *
     * @param User $user
     * @return bool
     */
    public function viewStatistics(User $user): bool
    {
        return $user->hasPermission('view ai_statistics') || 
               $user->hasRole(['admin', 'ai_specialist', 'clinical_director']);
    }
}