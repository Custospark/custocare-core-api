<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\UserMessageContact;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface UserMessageContactRepositoryInterface
{
    public function paginateForOwner(int $ownerUserId, ?string $search, int $perPage): LengthAwarePaginator;

    public function findForOwner(int $ownerUserId, int $id): ?UserMessageContact;

    public function findByOwnerEmailHash(int $ownerUserId, string $emailHash): ?UserMessageContact;

    public function findByOwnerPhoneHash(int $ownerUserId, string $phoneHash): ?UserMessageContact;

    public function create(array $data): UserMessageContact;

    public function update(UserMessageContact $contact, array $data): UserMessageContact;

    public function delete(UserMessageContact $contact): void;
}
