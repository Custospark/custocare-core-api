<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserPolicy
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
        return $user->isIdentityVerified() && $user->identity_state !== 'suspended';
    }

    /**
     * Determine whether the user can view the model.
     *
     * @param User $user
     * @param User $model
     * @return bool
     */
    public function view(User $user, User $model): bool
    {
        return $user->id === $model->id || 
               ($user->isIdentityVerified() && $user->identity_state !== 'suspended');
    }

    /**
     * Determine whether the user can view sensitive data.
     *
     * @param User $user
     * @param User $model
     * @return bool
     */
    public function viewSensitiveData(User $user, User $model): bool
    {
        // Only allow viewing sensitive data for own profile or with specific permission
        return $user->id === $model->id || $user->can('viewAllSensitiveData');
    }

    /**
     * Determine whether the user can create models.
     *
     * @param User $user
     * @return bool
     */
    public function create(User $user): bool
    {
        return $user->isIdentityVerified() && $user->identity_state !== 'suspended';
    }

    /**
     * Determine whether the user can update the model.
     *
     * @param User $user
     * @param User $model
     * @return bool
     */
    public function update(User $user, User $model): bool
    {
        return $user->id === $model->id || 
               ($user->isIdentityVerified() && $user->identity_state !== 'suspended');
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @param User $user
     * @param User $model
     * @return bool
     */
    public function delete(User $user, User $model): bool
    {
        return $user->isIdentityVerified() && 
               $user->identity_state !== 'suspended' &&
               $user->id !== $model->id; // Cannot delete yourself
    }

    /**
     * Determine whether the user can restore the model.
     *
     * @param User $user
     * @param User $model
     * @return bool
     */
    public function restore(User $user, User $model): bool
    {
        return $user->isIdentityVerified() && $user->identity_state !== 'suspended';
    }

    /**
     * Determine whether the user can permanently delete the model.
     *
     * @param User $user
     * @param User $model
     * @return bool
     */
    public function forceDelete(User $user, User $model): bool
    {
        return $user->isIdentityVerified() && $user->identity_state !== 'suspended';
    }

    /**
     * Determine whether the user can verify identity.
     *
     * @param User $user
     * @return bool
     */
    public function verifyIdentity(User $user): bool
    {
        // Only staff with specific permission can verify identities
        return $user->identity_state === 'verified' && 
               $user->hasPermissionTo('verify_identity');
    }

    /**
     * Determine whether the user can update password.
     *
     * @param User $user
     * @param User $model
     * @return bool
     */
    public function updatePassword(User $user, User $model): bool
    {
        return $user->id === $model->id || $user->hasPermissionTo('reset_passwords');
    }

    /**
     * Determine whether the user can manage MFA.
     *
     * @param User $user
     * @param User $model
     * @return bool
     */
    public function manageMfa(User $user, User $model): bool
    {
        return $user->id === $model->id || $user->hasPermissionTo('manage_mfa');
    }
}