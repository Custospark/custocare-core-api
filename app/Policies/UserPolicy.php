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
        return $user->hasPermission('users.view');
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
        return $user->hasPermission('users.view') 
            || $user->id === $model->id;
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
        return $user->hasPermission('users.view_sensitive');
    }

    /**
     * Determine whether the user can create models.
     *
     * @param User $user
     * @return bool
     */
    public function create(User $user): bool
    {
        return $user->hasPermission('users.create');
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
        return $user->hasPermission('users.update') 
            || $user->id === $model->id;
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
        return $user->hasPermission('users.delete') 
            && $user->id !== $model->id;
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
        return $user->hasPermission('users.restore');
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
        return $user->hasPermission('users.force_delete');
    }

    /**
     * Determine whether the user can verify identity.
     *
     * @param User $user
     * @param User $model
     * @return bool
     */
    public function verifyIdentity(User $user, User $model): bool
    {
        return $user->hasPermission('users.verify_identity');
    }

    /**
     * Determine whether the user can suspend.
     *
     * @param User $user
     * @param User $model
     * @return bool
     */
    public function suspend(User $user, User $model): bool
    {
        return $user->hasPermission('users.suspend') 
            && $user->id !== $model->id;
    }

    /**
     * Determine whether the user can restore from suspension.
     *
     * @param User $user
     * @param User $model
     * @return bool
     */
    public function restoreFromSuspension(User $user, User $model): bool
    {
        return $user->hasPermission('users.restore_suspended');
    }

    /**
     * Determine whether the user can archive.
     *
     * @param User $user
     * @param User $model
     * @return bool
     */
    public function archive(User $user, User $model): bool
    {
        return $user->hasPermission('users.archive');
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
        return $user->hasPermission('users.update_password') 
            || $user->id === $model->id;
    }

    /**
     * Determine whether the user can enable MFA.
     *
     * @param User $user
     * @param User $model
     * @return bool
     */
    public function enableMfa(User $user, User $model): bool
    {
        return $user->hasPermission('users.enable_mfa') 
            || $user->id === $model->id;
    }
}