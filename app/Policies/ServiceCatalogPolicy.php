<?php

namespace App\Policies;

use App\Models\ServiceCatalog;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Auth\Access\Response;

class ServiceCatalogPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function viewAny(User $user): Response|bool
    {
        // Allow all authenticated users to view service catalogs
        // Adjust based on your application's requirements
        return $user->hasPermissionTo('view service catalogs') 
            ? Response::allow()
            : Response::deny('You do not have permission to view service catalogs.');
    }

    /**
     * Determine whether the user can view the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\ServiceCatalog  $serviceCatalog
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function view(User $user, ServiceCatalog $serviceCatalog): Response|bool
    {
        // Allow viewing unless the service is inactive or deprecated and user doesn't have special permission
        if (in_array($serviceCatalog->status, ['inactive', 'deprecated']) && 
            !$user->hasPermissionTo('view inactive services')) {
            return Response::deny('You do not have permission to view this service catalog.');
        }

        return $user->hasPermissionTo('view service catalogs') 
            ? Response::allow()
            : Response::deny('You do not have permission to view this service catalog.');
    }

    /**
     * Determine whether the user can create models.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function create(User $user): Response|bool
    {
        // Only users with specific roles can create service catalogs
        return $user->hasPermissionTo('create service catalogs') 
            ? Response::allow()
            : Response::deny('You do not have permission to create service catalogs.');
    }

    /**
     * Determine whether the user can update the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\ServiceCatalog  $serviceCatalog
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function update(User $user, ServiceCatalog $serviceCatalog): Response|bool
    {
        // Prevent updating deprecated services
        if ($serviceCatalog->status === 'deprecated') {
            return Response::deny('Cannot update deprecated service catalogs.');
        }

        // Only users with specific roles can update service catalogs
        return $user->hasPermissionTo('update service catalogs') 
            ? Response::allow()
            : Response::deny('You do not have permission to update service catalogs.');
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\ServiceCatalog  $serviceCatalog
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function delete(User $user, ServiceCatalog $serviceCatalog): Response|bool
    {
        // Only allow deletion of services that are not in use
        // You would need to implement the isInUse() method based on your business logic
        if ($this->isServiceInUse($serviceCatalog)) {
            return Response::deny('Cannot delete service catalog that is currently in use.');
        }

        // Only users with specific roles can delete service catalogs
        return $user->hasPermissionTo('delete service catalogs') 
            ? Response::allow()
            : Response::deny('You do not have permission to delete service catalogs.');
    }

    /**
     * Determine whether the user can restore the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\ServiceCatalog  $serviceCatalog
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function restore(User $user, ServiceCatalog $serviceCatalog): Response|bool
    {
        // Only users with specific roles can restore service catalogs
        return $user->hasPermissionTo('restore service catalogs') 
            ? Response::allow()
            : Response::deny('You do not have permission to restore service catalogs.');
    }

    /**
     * Determine whether the user can permanently delete the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\ServiceCatalog  $serviceCatalog
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function forceDelete(User $user, ServiceCatalog $serviceCatalog): Response|bool
    {
        // Only users with admin roles can force delete
        return $user->hasRole('admin') && $user->hasPermissionTo('force delete service catalogs') 
            ? Response::allow()
            : Response::deny('You do not have permission to permanently delete service catalogs.');
    }

    /**
     * Determine whether the user can update the service code.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\ServiceCatalog  $serviceCatalog
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function updateServiceCode(User $user, ServiceCatalog $serviceCatalog): Response|bool
    {
        // Only specific roles can update service codes (usually administrators)
        return $user->hasPermissionTo('update service codes') 
            ? Response::allow()
            : Response::deny('You do not have permission to update service codes.');
    }

    /**
     * Determine whether the user can change the status of a service catalog.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\ServiceCatalog  $serviceCatalog
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function changeStatus(User $user, ServiceCatalog $serviceCatalog): Response|bool
    {
        // Only specific roles can change service status
        return $user->hasPermissionTo('change service status') 
            ? Response::allow()
            : Response::deny('You do not have permission to change service status.');
    }

    /**
     * Check if a service catalog is currently in use.
     * This is a placeholder method - implement based on your business logic.
     *
     * @param  \App\Models\ServiceCatalog  $serviceCatalog
     * @return bool
     */
    private function isServiceInUse(ServiceCatalog $serviceCatalog): bool
    {
        // Implement logic to check if the service is referenced in appointments,
        // billing records, treatment plans, etc.
        // Example:
        // return Appointment::where('service_catalog_id', $serviceCatalog->id)->exists()
        //     || BillingRecord::where('service_code', $serviceCatalog->service_code)->exists();
        
        return false; // Placeholder - implement actual logic
    }
}