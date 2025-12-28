<?php

namespace App\Policies;

use App\Models\ServiceVersion;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * ServiceVersionPolicy
 * 
 * Defines authorization rules for ServiceVersion actions.
 * Based on user roles and permissions in a healthcare context.
 */
class ServiceVersionPolicy
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
        // Allow viewing if user has any of these roles
        $allowedRoles = ['admin', 'billing_manager', 'service_manager', 'financial_analyst', 'auditor'];
        
        return in_array($user->role, $allowedRoles) || 
               $user->hasPermission('view_service_versions');
    }

    /**
     * Determine whether the user can view the model.
     *
     * @param User $user
     * @param ServiceVersion $serviceVersion
     * @return bool
     */
    public function view(User $user, ServiceVersion $serviceVersion): bool
    {
        // Allow viewing if user has any of these roles
        $allowedRoles = ['admin', 'billing_manager', 'service_manager', 'financial_analyst', 'auditor'];
        
        $hasRoleOrPermission = in_array($user->role, $allowedRoles) || 
                               $user->hasPermission('view_service_version');
        
        // Additional check for facility-specific access
        if ($serviceVersion->facility_id && $user->facility_id) {
            return $hasRoleOrPermission && $user->facility_id == $serviceVersion->facility_id;
        }
        
        return $hasRoleOrPermission;
    }

    /**
     * Determine whether the user can create models.
     *
     * @param User $user
     * @return bool
     */
    public function create(User $user): bool
    {
        // Only admin, billing_manager, and service_manager can create service versions
        $allowedRoles = ['admin', 'billing_manager', 'service_manager'];
        
        return in_array($user->role, $allowedRoles) || 
               $user->hasPermission('create_service_version');
    }

    /**
     * Determine whether the user can update the model.
     *
     * @param User $user
     * @param ServiceVersion $serviceVersion
     * @return bool
     */
    public function update(User $user, ServiceVersion $serviceVersion): bool
    {
        // Only admin, billing_manager, and service_manager can update service versions
        $allowedRoles = ['admin', 'billing_manager', 'service_manager'];
        
        $hasRoleOrPermission = in_array($user->role, $allowedRoles) || 
                               $user->hasPermission('update_service_version');
        
        // Additional checks
        if (!$hasRoleOrPermission) {
            return false;
        }
        
        // Cannot update if version is not current and user is not admin
        if (!$serviceVersion->is_current && $user->role !== 'admin') {
            return false;
        }
        
        // Facility-specific restriction
        if ($serviceVersion->facility_id && $user->facility_id) {
            return $user->facility_id == $serviceVersion->facility_id;
        }
        
        return true;
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @param User $user
     * @param ServiceVersion $serviceVersion
     * @return bool
     */
    public function delete(User $user, ServiceVersion $serviceVersion): bool
    {
        // Only admin can delete service versions (for audit trail)
        return $user->role === 'admin' || 
               $user->hasPermission('delete_service_version');
    }

    /**
     * Determine whether the user can restore the model.
     *
     * @param User $user
     * @param ServiceVersion $serviceVersion
     * @return bool
     */
    public function restore(User $user, ServiceVersion $serviceVersion): bool
    {
        // Only admin can restore deleted service versions
        return $user->role === 'admin';
    }

    /**
     * Determine whether the user can permanently delete the model.
     *
     * @param User $user
     * @param ServiceVersion $serviceVersion
     * @return bool
     */
    public function forceDelete(User $user, ServiceVersion $serviceVersion): bool
    {
        // No permanent deletion for audit trail
        return false;
    }

    /**
     * Determine whether the user can view the version hash.
     *
     * @param User $user
     * @param ServiceVersion $serviceVersion
     * @return bool
     */
    public function viewVersionHash(User $user, ServiceVersion $serviceVersion): bool
    {
        // Only admin and auditors can view version hash for integrity verification
        return in_array($user->role, ['admin', 'auditor']);
    }

    /**
     * Determine whether the user can set a version as current.
     *
     * @param User $user
     * @param ServiceVersion $serviceVersion
     * @return bool
     */
    public function setAsCurrent(User $user, ServiceVersion $serviceVersion): bool
    {
        // Only admin, billing_manager, and service_manager can set versions as current
        $allowedRoles = ['admin', 'billing_manager', 'service_manager'];
        
        $hasRoleOrPermission = in_array($user->role, $allowedRoles) || 
                               $user->hasPermission('set_current_version');
        
        // Facility-specific restriction
        if ($serviceVersion->facility_id && $user->facility_id) {
            return $hasRoleOrPermission && $user->facility_id == $serviceVersion->facility_id;
        }
        
        return $hasRoleOrPermission;
    }

    /**
     * Determine whether the user can view pricing details.
     *
     * @param User $user
     * @param ServiceVersion $serviceVersion
     * @return bool
     */
    public function viewPricing(User $user, ServiceVersion $serviceVersion): bool
    {
        // Pricing details are sensitive - restrict access
        $allowedRoles = ['admin', 'billing_manager', 'financial_analyst', 'service_manager'];
        
        $hasRoleOrPermission = in_array($user->role, $allowedRoles) || 
                               $user->hasPermission('view_pricing_details');
        
        // Facility-specific restriction
        if ($serviceVersion->facility_id && $user->facility_id) {
            return $hasRoleOrPermission && $user->facility_id == $serviceVersion->facility_id;
        }
        
        return $hasRoleOrPermission;
    }

    /**
     * Determine whether the user can view cost accounting details.
     *
     * @param User $user
     * @param ServiceVersion $serviceVersion
     * @return bool
     */
    public function viewCostAccounting(User $user, ServiceVersion $serviceVersion): bool
    {
        // Cost accounting is highly sensitive - restrict access further
        $allowedRoles = ['admin', 'financial_analyst'];
        
        $hasRoleOrPermission = in_array($user->role, $allowedRoles) || 
                               $user->hasPermission('view_cost_accounting');
        
        // Facility-specific restriction
        if ($serviceVersion->facility_id && $user->facility_id) {
            return $hasRoleOrPermission && $user->facility_id == $serviceVersion->facility_id;
        }
        
        return $hasRoleOrPermission;
    }
}