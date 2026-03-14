<?php

declare(strict_types=1);

namespace App\Repositories\User;

use App\Models\User;
use App\Repositories\User\Contracts\UserRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;

class UserRepository implements UserRepositoryInterface
{
    /**
     * Find user by ID.
     *
     * @param int $id
     * @return User|null
     */
    public function findById(int $id): ?User
    {
        return User::find($id);
    }

    /**
     * Find user by global UUID.
     *
     * @param string $uuid
     * @return User|null
     */
    public function findByUuid(string $uuid): ?User
    {
        return User::where('global_user_uuid', $uuid)->first();
    }

    /**
     * Find user by email hash.
     *
     * @param string $emailHash
     * @return User|null
     */
    public function findByEmailHash(string $emailHash): ?User
    {
        return User::where('email_hash', $emailHash)->first();
    }

    /**
     * Find user by national ID hash.
     *
     * @param string $nationalIdHash
     * @return User|null
     */
    public function findByNationalIdHash(string $nationalIdHash): ?User
    {
        return User::where('national_id_hash', $nationalIdHash)->first();
    }

    /**
     * Get all users with pagination.
     *
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getAllPaginated(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = User::query();

        if (isset($filters['identity_state'])) {
            $query->where('identity_state', $filters['identity_state']);
        }

        if (isset($filters['data_residency_region'])) {
            $query->where('data_residency_region', $filters['data_residency_region']);
        }

        if (isset($filters['search'])) {
            $query->where(function (Builder $q) use ($filters) {
                $q->where('first_name', 'like', "%{$filters['search']}%")
                  ->orWhere('last_name', 'like', "%{$filters['search']}%")
                  ->orWhere('display_name', 'like', "%{$filters['search']}%");
            });
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    /**
     * Create a new user and assign super_admin role if email matches.
     *
     * @param array $data
     * @return User
     */
    public function create(array $data): User
    {
        $user = User::create($data);
        $this->assignSuperAdminRoleIfMatches($user);
        
        return $user;
    }

    /**
     * Update an existing user and reassign super_admin role if email changed.
     *
     * @param User $user
     * @param array $data
     * @return bool
     */
    public function update(User $user, array $data): bool
    {
        $updated = $user->update($data);
        
        if ($updated) {
            $this->assignSuperAdminRoleIfMatches($user->fresh());
        }
        
        return $updated;
    }

    /**
     * Delete a user (soft delete).
     *
     * @param User $user
     * @return bool
     */
    public function delete(User $user): bool
    {
        return $user->delete();
    }

    /**
     * Restore a soft-deleted user.
     *
     * @param User $user
     * @return bool
     */
    public function restore(User $user): bool
    {
        return $user->restore();
    }

    /**
     * Update user's last login information.
     *
     * @param User $user
     * @param string $ip
     * @param string $userAgent
     * @return bool
     */
    public function updateLastLogin(User $user, string $ip, string $userAgent): bool
    {
        return $user->update([
            'last_login_at' => now(),
            'last_login_ip' => $ip,
            'last_login_user_agent' => $userAgent,
            'failed_login_attempts' => 0, // Reset on successful login
        ]);
    }

    /**
     * Increment failed login attempts.
     *
     * @param User $user
     * @return int
     */
    public function incrementFailedAttempts(User $user): int
    {
        return $user->increment('failed_login_attempts');
    }

    /**
     * Reset failed login attempts.
     *
     * @param User $user
     * @return bool
     */
    public function resetFailedAttempts(User $user): bool
    {
        return $user->update(['failed_login_attempts' => 0]);
    }

    /**
     * Lock user account.
     *
     * @param User $user
     * @param \DateTimeInterface $until
     * @return bool
     */
    public function lockAccount(User $user, \DateTimeInterface $until): bool
    {
        return $user->update(['account_locked_until' => $until]);
    }

    /**
     * Unlock user account.
     *
     * @param User $user
     * @return bool
     */
    public function unlockAccount(User $user): bool
    {
        return $user->update(['account_locked_until' => null]);
    }

    // ══════════════════════════════════════════════════════════════
    // PROFILE
    // ══════════════════════════════════════════════════════════════

    /**
     * Retrieve profile-relevant columns for a user.
     *
     * @param int $id
     * @return User|null
     */
    public function getProfileById(int $id): ?User
    {
        return User::select([
            'id',
            'first_name',
            'last_name',
            'display_name',
            'title',
            'dob',
            'gender',
            'phone_encrypted',
            'address_line1',
            'address_line2',
            'city',
            'state',
            'country',
            'postal_code',
            'profile_photo_path',
        ])->find($id);
    }

    /**
     * Update profile-relevant columns for a user.
     *
     * @param int   $id
     * @param array $data  Prepared payload (phone already encrypted)
     * @return bool
     */
    public function updateProfileById(int $id, array $data): bool
    {
        return (bool) User::where('id', $id)->update($data);
    }

    // ══════════════════════════════════════════════════════════════
    // SECURITY
    // ══════════════════════════════════════════════════════════════

    /**
     * Retrieve security-relevant columns for a user.
     * Includes password_hash so the service can verify current password.
     * The service layer must NEVER pass password_hash to the controller response.
     *
     * @param int $id
     * @return User|null
     */
    public function getSecurityById(int $id): ?User
    {
        return User::select([
            'id',
            'password_hash',
            'password_changed_at',
            'requires_password_change',
            'mfa_enabled',
            'mfa_secret_encrypted',
            'last_login_at',
            'last_login_ip',
            'failed_login_attempts',
            'account_locked_until',
        ])->find($id);
    }

    /**
     * Update security-relevant columns for a user.
     *
     * @param int   $id
     * @param array $data  Prepared payload (password already hashed)
     * @return bool
     */
    public function updateSecurityById(int $id, array $data): bool
    {
        return (bool) User::where('id', $id)->update($data);
    }

    // ══════════════════════════════════════════════════════════════
    // PREFERENCES
    // ══════════════════════════════════════════════════════════════

    /**
     * Retrieve preferences-relevant columns for a user.
     *
     * @param int $id
     * @return User|null
     */
    public function getPreferencesById(int $id): ?User
    {
        return User::select([
            'id',
            'theme_mode',
            'ui_density',
            'timezone',
            'locale',
        ])->find($id);
    }

    /**
     * Update preferences-relevant columns for a user.
     *
     * @param int   $id
     * @param array $data
     * @return bool
     */
    public function updatePreferencesById(int $id, array $data): bool
    {
        return (bool) User::where('id', $id)->update($data);
    }

    // ══════════════════════════════════════════════════════════════
    // PRIVATE HELPERS
    // ══════════════════════════════════════════════════════════════

    /**
     * Assign or remove super_admin role based on email match with SUPER_ADMIN_EMAIL env.
     *
     * @param User $user
     * @return void
     */
  private function assignSuperAdminRoleIfMatches(User $user): void
{
    $superAdminEmail = env('SUPER_ADMIN_EMAIL');
    
    // If no super admin email is configured, do nothing
    if (empty($superAdminEmail)) {
        return;
    }
    
    // Make sure we have an encrypted email to decrypt
    if (empty($user->email_encrypted)) {
        Log::warning('User has no encrypted email for super admin check', ['user_id' => $user->id]);
        return;
    }
    
    // Decrypt the user's email
    try {
        $email = decrypt($user->email_encrypted);
    } catch (\Exception $e) {
        Log::error('Failed to decrypt email for super admin check', [
            'user_id' => $user->id,
            'error' => $e->getMessage()
        ]);
        return;
    }
    
    // Normalize emails for comparison
    $normalizedEmail = strtolower(trim($email));
    $normalizedSuperEmail = strtolower(trim($superAdminEmail));
    $isSuperAdminEmail = $normalizedEmail === $normalizedSuperEmail;
    
    // Ensure super_admin role exists for required guards
    try {
        $guards = ['api', 'web'];
        $roleCreated = false;
        
        foreach ($guards as $guard) {
            $role = \Spatie\Permission\Models\Role::firstOrCreate([
                'name' => 'super_admin',
                'guard_name' => $guard
            ]);
            
            if ($role->wasRecentlyCreated) {
                $roleCreated = true;
            }
        }
        
        if ($roleCreated) {
            Log::info('super_admin role was created for one or more guards');
        }
        
    } catch (\Exception $e) {
        Log::error('Failed to verify/create super_admin role', [
            'error' => $e->getMessage(),
            'user_id' => $user->id
        ]);
        return;
    }
    
    // Check if user already has super_admin role (for any guard)
    $hasSuperAdminRole = false;
    foreach ($guards as $guard) {
        if ($user->hasRole('super_admin', $guard)) {
            $hasSuperAdminRole = true;
            break;
        }
    }
    
    // Sync role based on email match
    if ($isSuperAdminEmail && !$hasSuperAdminRole) {
        // User matches super admin email but doesn't have role - assign it for all guards
            $user->assignRole('super_admin');

        Log::info("Super admin role assigned to user", [
            'email' => $email,
            'user_id' => $user->id,
            'guards' => $guards
        ]);
        
    } elseif (!$isSuperAdminEmail && $hasSuperAdminRole) {
        // User has super admin role but doesn't match email - remove it from all guards
            $user->removeRole('super_admin', $guard);
        
        Log::info("Super admin role removed from user", [
            'email' => $email,
            'user_id' => $user->id,
            'guards' => $guards
        ]);
        
    } elseif ($isSuperAdminEmail && $hasSuperAdminRole) {
        // User already has correct role - just log for debugging if needed
        Log::debug("Super admin user verified", [
            'email' => $email,
            'user_id' => $user->id
        ]);
    }
}
}