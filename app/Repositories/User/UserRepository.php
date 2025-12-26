<?php

declare(strict_types=1);

namespace App\Repositories\User;

use App\Models\User;
use App\Repositories\User\Contracts\UserRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

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
     * Create a new user.
     *
     * @param array $data
     * @return User
     */
    public function create(array $data): User
    {
        return User::create($data);
    }

    /**
     * Update an existing user.
     *
     * @param User $user
     * @param array $data
     * @return bool
     */
    public function update(User $user, array $data): bool
    {
        return $user->update($data);
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
     * @return bool
     */
    public function incrementFailedAttempts(User $user): bool
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
}