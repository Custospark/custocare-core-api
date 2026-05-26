<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\Models\UserMessageContact;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface UserMessageContactServiceInterface
{
    /**
     * @return array{linked_user_id: int|null, can_message: bool, display_name: string|null}
     */
    public function resolveChannel(?string $email, ?string $phone): array;

    public function listForOwner(int $ownerUserId, ?string $search, int $perPage): LengthAwarePaginator;

    public function getForOwner(int $ownerUserId, int $contactId): UserMessageContact;

    /**
     * @param  array{display_name: string, email?: string|null, phone?: string|null}  $data
     */
    public function createForOwner(int $ownerUserId, array $data): UserMessageContact;

    /**
     * @param  array{display_name?: string, email?: string|null, phone?: string|null}  $data
     */
    public function updateForOwner(int $ownerUserId, int $contactId, array $data): UserMessageContact;

    public function deleteForOwner(int $ownerUserId, int $contactId): void;

    public function touchLastUsed(int $ownerUserId, int $contactId): UserMessageContact;

    /**
     * @return array<string, mixed>
     */
    public function serializeForOwner(UserMessageContact $contact): array;
}
