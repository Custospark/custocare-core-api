<?php

namespace App\Policies;

use App\Models\PatientVisitSummaryView;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class PatientVisitSummaryViewPolicy
{
    /**
     * Determine whether the user can view any models.
     *
     * @param User $user
     * @return Response
     */
    public function viewAny(User $user): Response
    {
        return $user->hasPermission('view_patient_summaries')
            ? Response::allow()
            : Response::deny('You do not have permission to view patient visit summaries.');
    }

    /**
     * Determine whether the user can view the model.
     *
     * @param User $user
     * @param PatientVisitSummaryView $patientVisitSummaryView
     * @return Response
     */
    public function view(User $user, PatientVisitSummaryView $patientVisitSummaryView): Response
    {
        // Users can view if they have permission or if they are the patient's provider
        return $user->hasPermission('view_patient_summaries') ||
               $user->id === $patientVisitSummaryView->primary_care_provider_id ||
               $user->hasRole('care_coordinator')
            ? Response::allow()
            : Response::deny('You do not have permission to view this patient visit summary.');
    }

    /**
     * Determine whether the user can create models.
     *
     * @param User $user
     * @return Response
     */
    public function create(User $user): Response
    {
        return $user->hasPermission('create_patient_summaries')
            ? Response::allow()
            : Response::deny('You do not have permission to create patient visit summaries.');
    }

    /**
     * Determine whether the user can update the model.
     *
     * @param User $user
     * @param PatientVisitSummaryView $patientVisitSummaryView
     * @return Response
     */
    public function update(User $user, PatientVisitSummaryView $patientVisitSummaryView): Response
    {
        return $user->hasPermission('update_patient_summaries') ||
               $user->hasRole('system_admin') ||
               $user->hasRole('data_sync')
            ? Response::allow()
            : Response::deny('You do not have permission to update this patient visit summary.');
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @param User $user
     * @param PatientVisitSummaryView $patientVisitSummaryView
     * @return Response
     */
    public function delete(User $user, PatientVisitSummaryView $patientVisitSummaryView): Response
    {
        return $user->hasPermission('delete_patient_summaries') ||
               $user->hasRole('system_admin')
            ? Response::allow()
            : Response::deny('You do not have permission to delete this patient visit summary.');
    }

    /**
     * Determine whether the user can refresh the model.
     *
     * @param User $user
     * @return Response
     */
    public function refresh(User $user): Response
    {
        return $user->hasPermission('refresh_patient_summaries') ||
               $user->hasRole('care_coordinator') ||
               $user->hasRole('system_admin')
            ? Response::allow()
            : Response::deny('You do not have permission to refresh patient visit summaries.');
    }

    /**
     * Determine whether the user can batch refresh models.
     *
     * @param User $user
     * @return Response
     */
    public function batchRefresh(User $user): Response
    {
        return $user->hasPermission('batch_refresh_patient_summaries') ||
               $user->hasRole('system_admin') ||
               $user->hasRole('data_sync')
            ? Response::allow()
            : Response::deny('You do not have permission to batch refresh patient visit summaries.');
    }

    /**
     * Determine whether the user can view care coordination insights.
     *
     * @param User $user
     * @return Response
     */
    public function viewInsights(User $user): Response
    {
        return $user->hasPermission('view_care_coordination_insights') ||
               $user->hasRole('care_coordinator') ||
               $user->hasRole('system_admin') ||
               $user->hasRole('practice_manager')
            ? Response::allow()
            : Response::deny('You do not have permission to view care coordination insights.');
    }

    /**
     * Determine whether the user can view health metrics trends.
     *
     * @param User $user
     * @param PatientVisitSummaryView $patientVisitSummaryView
     * @return Response
     */
    public function viewHealthMetrics(User $user, PatientVisitSummaryView $patientVisitSummaryView): Response
    {
        // Only providers and care coordinators can view health metrics
        return $user->hasRole('provider') ||
               $user->hasRole('care_coordinator') ||
               $user->hasRole('system_admin') ||
               $user->id === $patientVisitSummaryView->primary_care_provider_id
            ? Response::allow()
            : Response::deny('You do not have permission to view health metrics trends.');
    }

    /**
     * Determine whether the user can view financial information.
     *
     * @param User $user
     * @param PatientVisitSummaryView $patientVisitSummaryView
     * @return Response
     */
    public function viewFinancialInfo(User $user, PatientVisitSummaryView $patientVisitSummaryView): Response
    {
        // Only billing staff and administrators can view financial information
        return $user->hasRole('billing') ||
               $user->hasRole('practice_manager') ||
               $user->hasRole('system_admin')
            ? Response::allow()
            : Response::deny('You do not have permission to view financial information.');
    }
}