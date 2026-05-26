<?php

declare(strict_types=1);

namespace App\Repositories\UserMessageContact;

use App\Models\UserMessageContact;
use App\Repositories\Contracts\UserMessageContactRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class UserMessageContactRepository implements UserMessageContactRepositoryInterface
{
    public function paginateForOwner(int $ownerUserId, ?string $search, int $perPage): LengthAwarePaginator
    {
        $query = UserMessageContact::query()
            ->where('owner_user_id', $ownerUserId)
            ->with('linkedUser:id,first_name,last_name,display_name')
            ->orderByDesc('last_used_at')
            ->orderBy('display_name');

        if ($search !== null && trim($search) !== '') {
            $term = '%'.trim($search).'%';
            $query->where(function ($q) use ($term): void {
                $q->where('display_name', 'like', $term)
                    ->orWhereHas('linkedUser', function ($userQ) use ($term): void {
                        $userQ->where('display_name', 'like', $term)
                            ->orWhere('first_name', 'like', $term)
                            ->orWhere('last_name', 'like', $term);
                    });
            });
        }

        return $query->paginate($perPage);
    }

    public function findForOwner(int $ownerUserId, int $id): ?UserMessageContact
    {
        return UserMessageContact::query()
            ->where('owner_user_id', $ownerUserId)
            ->where('id', $id)
            ->with('linkedUser:id,first_name,last_name,display_name')
            ->first();
    }

    public function findByOwnerEmailHash(int $ownerUserId, string $emailHash): ?UserMessageContact
    {
        return UserMessageContact::query()
            ->where('owner_user_id', $ownerUserId)
            ->where('email_hash', $emailHash)
            ->first();
    }

    public function findByOwnerPhoneHash(int $ownerUserId, string $phoneHash): ?UserMessageContact
    {
        return UserMessageContact::query()
            ->where('owner_user_id', $ownerUserId)
            ->where('phone_hash', $phoneHash)
            ->first();
    }

    public function create(array $data): UserMessageContact
    {
        return UserMessageContact::query()->create($data);
    }

    public function update(UserMessageContact $contact, array $data): UserMessageContact
    {
        $contact->update($data);

        return $contact->fresh(['linkedUser:id,first_name,last_name,display_name']);
    }

    public function delete(UserMessageContact $contact): void
    {
        $contact->delete();
    }
}
