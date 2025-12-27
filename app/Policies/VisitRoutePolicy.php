<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VisitRoute;
use Illuminate\Auth\Access\HandlesAuthorization;

class VisitRoutePolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('visit_routes.view_any') ||
               $user->hasRole(['administrator', 'facility_manager', 'department_head']);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, VisitRoute $visitRoute): bool
    {
        // Users can view routes in their facility
        if ($user->facility_id && $user->facility_id == $visitRoute->facility_id) {
            return true;
        }
        
        // Staff involved in the route can view it
        if (in_array($user->id, [
            $visitRoute->routing_staff_id,
            $visitRoute->sending_staff_id,
            $visitRoute->receiving_staff_id
        ])) {
            return true;
        }
        
        return $user->hasPermission('visit_routes.view') ||
               $user->hasRole(['administrator', 'facility_manager']);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermission('visit_routes.create') ||
               $user->hasRole(['administrator', 'facility_manager', 'department_head', 'nurse']);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, VisitRoute $visitRoute): bool
    {
        // Only allow updates to active routes by authorized staff
        if (!$visitRoute->isActive()) {
            return false;
        }
        
        // Staff involved in the route can update it
        if (in_array($user->id, [
            $visitRoute->routing_staff_id,
            $visitRoute->sending_staff_id
        ])) {
            return true;
        }
        
        // Department heads can update routes in their department
        if ($user->hasRole('department_head') && 
            $user->department_id == $visitRoute->to_department_id) {
            return true;
        }
        
        return $user->hasPermission('visit_routes.update') ||
               $user->hasRole(['administrator', 'facility_manager']);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, VisitRoute $visitRoute): bool
    {
        // Never allow deletion of completed routes
        if ($visitRoute->isComplete()) {
            return false;
        }
        
        // Only allow deletion by administrators or facility managers
        return $user->hasPermission('visit_routes.delete') ||
               $user->hasRole(['administrator', 'facility_manager']);
    }

    /**
     * Determine whether the user can bulk delete models.
     */
    public function deleteAny(User $user): bool
    {
        return $user->hasPermission('visit_routes.delete_any') ||
               $user->hasRole(['administrator']);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, VisitRoute $visitRoute): bool
    {
        return $user->hasPermission('visit_routes.restore') ||
               $user->hasRole(['administrator']);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, VisitRoute $visitRoute): bool
    {
        return $user->hasPermission('visit_routes.force_delete') ||
               $user->hasRole(['administrator']);
    }

    /**
     * Determine whether the user can acknowledge handoffs.
     */
    public function acknowledgeHandoff(User $user, VisitRoute $visitRoute): bool
    {
        // Only receiving staff or department heads can acknowledge handoffs
        if ($user->id == $visitRoute->receiving_staff_id) {
            return true;
        }
        
        if ($user->hasRole('department_head') && 
            $user->department_id == $visitRoute->to_department_id) {
            return true;
        }
        
        return $user->hasPermission('visit_routes.acknowledge_handoff') ||
               $user->hasRole(['administrator', 'facility_manager']);
    }

    /**
     * Determine whether the user can mark routes as arrived/departed.
     */
    public function updateStatus(User $user, VisitRoute $visitRoute): bool
    {
        // Staff in the destination department can update status
        if ($user->department_id == $visitRoute->to_department_id) {
            return $user->hasPermission('visit_routes.update_status') ||
                   $user->hasRole(['nurse', 'doctor', 'department_head']);
        }
        
        return false;
    }

    /**
     * Determine whether the user can view analytics.
     */
    public function viewAnalytics(User $user): bool
    {
        return $user->hasPermission('visit_routes.view_analytics') ||
               $user->hasRole(['administrator', 'facility_manager', 'department_head']);
    }
}