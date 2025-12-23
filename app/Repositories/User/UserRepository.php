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
     * Constructor.
     *
     * @param User $model
     */
    public function __construct(
        private readonly User $model
    ) {}

    /**
     * Find a user by ID.
     *
     * @param int $id
     * @return User|null
     */
    public function find(int $id): ?User
    {
        return $this->model->find($id);
    }

    /**
     * Find a user by global UUID.
     *
     * @param string $uuid
     * @return User|null
     */
    public function findByUuid(string $uuid): ?User
    {
        return $this->model->where('global_user_uuid', $uuid)->first();
    }

    /**
     * Find a user by national ID hash.
     *
     * @param string $nationalIdHash
     * @return User|null
     */
    public function findByNationalIdHash(string $nationalIdHash): ?User
    {
        return $this->model->where('national_id_hash', $nationalIdHash)->first();
    }

    /**
     * Find users by identity state.
     *
     * @param string $identityState
     * @param array $relations
     * @return Collection
     */
    public function findByIdentityState(string $identityState, array $relations = []): Collection
    {
        $query = $this->model->where('identity_state', $identityState);

        if (!empty($relations)) {
            $query->with($relations);
        }

        return $query->get();
    }

    /**
     * Get all users with pagination.
     *
     * @param int $perPage
     * @param array $filters
     * @return LengthAwarePaginator
     */
    public function paginate(int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        $query = $this->model->newQuery();

        // Apply filters
        if (isset($filters['identity_state'])) {
            $query->where('identity_state', $filters['identity_state']);
        }

        if (isset($filters['data_residency_region'])) {
            $query->where('data_residency_region', $filters['data_residency_region']);
        }

        if (isset($filters['national_id_country_code'])) {
            $query->where('national_id_country_code', $filters['national_id_country_code']);
        }

        if (isset($filters['search'])) {
            $query->where(function (Builder $q) use ($filters) {
                $q->where('global_user_uuid', 'like', "%{$filters['search']}%")
                  ->orWhere('email_hash', 'like', "%{$filters['search']}%")
                  ->orWhere('phone_hash', 'like', "%{$filters['search']}%");
            });
        }

        return $query->paginate($perPage);
    }

    /**
     * Create a new user.
     *
     * @param array $data
     * @return User
     */
    public function create(array $data): User
    {
        return DB::transaction(function () use ($data) {
            return $this->model->create($data);
        });
    }

    /**
     * Update an existing user.
     *
     * @param User $user
     * @param array $data
     * @return User
     */
    public function update(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data) {
            $user->update($data);
            return $user->fresh();
        });
    }

    /**
     * Delete a user (soft delete).
     *
     * @param User $user
     * @return bool
     */
    public function delete(User $user): bool
    {
        return DB::transaction(function () use ($user) {
            return $user->delete();
        });
    }

    /**
     * Restore a soft-deleted user.
     *
     * @param User $user
     * @return bool
     */
    public function restore(User $user): bool
    {
        return DB::transaction(function () use ($user) {
            return $user->restore();
        });
    }

    /**
     * Permanently delete a user.
     *
     * @param User $user
     * @return bool
     */
    public function forceDelete(User $user): bool
    {
        return DB::transaction(function () use ($user) {
            return $user->forceDelete();
        });
    }

    /**
     * Update user's identity verification status.
     *
     * @param User $user
     * @param array $verificationData
     * @return User
     */
    public function updateIdentityVerification(User $user, array $verificationData): User
    {
        return DB::transaction(function () use ($user, $verificationData) {
            $user->update(array_merge($verificationData, [
                'identity_verified_at' => now(),
            ]));
            return $user->fresh();
        });
    }

    /**
     * Get users by data residency region.
     *
     * @param string $region
     * @return Collection
     */
    public function getByDataResidencyRegion(string $region): Collection
    {
        return $this->model->where('data_residency_region', $region)->get();
    }

    /**
     * Check if email hash exists.
     *
     * @param string $emailHash
     * @return bool
     */
    public function emailHashExists(string $emailHash): bool
    {
        return $this->model->where('email_hash', $emailHash)->exists();
    }

    /**
     * Check if phone hash exists.
     *
     * @param string $phoneHash
     * @return bool
     */
    public function phoneHashExists(string $phoneHash): bool
    {
        return $this->model->where('phone_hash', $phoneHash)->exists();
    }
}