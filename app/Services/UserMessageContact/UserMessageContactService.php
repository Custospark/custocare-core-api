<?php

declare(strict_types=1);

namespace App\Services\UserMessageContact;

use App\Models\User;
use App\Models\UserMessageContact;
use App\Repositories\Contracts\UserMessageContactRepositoryInterface;
use App\Services\Contracts\UserMessageContactServiceInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class UserMessageContactService implements UserMessageContactServiceInterface
{
    public function __construct(
        private readonly UserMessageContactRepositoryInterface $repository,
    ) {}

    public function resolveChannel(?string $email, ?string $phone): array
    {
        $emailInput = $email !== null ? strtolower(trim($email)) : '';
        $phoneInput = $this->normalizePhone($phone);

        if ($emailInput !== '' && $phoneInput !== null) {
            throw new InvalidArgumentException('Provide only one of email or phone to resolve.');
        }

        if ($emailInput !== '') {
            $user = User::query()->where('email_hash', hash('sha256', $emailInput))->first();

            return [
                'linked_user_id' => $user?->id,
                'can_message'    => $user !== null,
                'display_name'   => $user?->display_name ?? trim($user?->first_name.' '.$user?->last_name) ?: null,
            ];
        }

        if ($phoneInput !== null) {
            $user = User::query()->where('phone_hash', hash('sha256', $phoneInput))->first();

            return [
                'linked_user_id' => $user?->id,
                'can_message'    => $user !== null,
                'display_name'   => $user?->display_name ?? trim($user?->first_name.' '.$user?->last_name) ?: null,
            ];
        }

        throw new InvalidArgumentException('Provide an email or a phone number to resolve.');
    }

    public function listForOwner(int $ownerUserId, ?string $search, int $perPage): LengthAwarePaginator
    {
        return $this->repository->paginateForOwner($ownerUserId, $search, min(max($perPage, 1), 100));
    }

    public function getForOwner(int $ownerUserId, int $contactId): UserMessageContact
    {
        $contact = $this->repository->findForOwner($ownerUserId, $contactId);
        if (!$contact) {
            throw new NotFoundHttpException('Contact not found.');
        }

        return $contact;
    }

    public function createForOwner(int $ownerUserId, array $data): UserMessageContact
    {
        $payload = $this->buildPersistPayload($ownerUserId, $data, null);

        try {
            return DB::transaction(fn () => $this->repository->create($payload));
        } catch (QueryException $e) {
            if ($this->isDuplicateContact($e)) {
                throw new InvalidArgumentException('A contact with this email or phone already exists in your notebook.');
            }
            throw $e;
        }
    }

    public function updateForOwner(int $ownerUserId, int $contactId, array $data): UserMessageContact
    {
        $contact = $this->getForOwner($ownerUserId, $contactId);
        $payload = $this->buildPersistPayload($ownerUserId, $data, $contact);

        try {
            return $this->repository->update($contact, $payload);
        } catch (QueryException $e) {
            if ($this->isDuplicateContact($e)) {
                throw new InvalidArgumentException('A contact with this email or phone already exists in your notebook.');
            }
            throw $e;
        }
    }

    public function deleteForOwner(int $ownerUserId, int $contactId): void
    {
        $contact = $this->getForOwner($ownerUserId, $contactId);
        $this->repository->delete($contact);
    }

    public function touchLastUsed(int $ownerUserId, int $contactId): UserMessageContact
    {
        $contact = $this->getForOwner($ownerUserId, $contactId);

        return $this->repository->update($contact, ['last_used_at' => now()]);
    }

    public function serializeForOwner(UserMessageContact $contact): array
    {
        return [
            'id'              => $contact->id,
            'display_name'    => $contact->display_name,
            'linked_user_id'  => $contact->linked_user_id,
            'can_message'     => $contact->linked_user_id !== null,
            'email'           => $this->decryptEmail($contact),
            'phone'           => $this->decryptPhone($contact),
            'last_used_at'    => $contact->last_used_at?->toIso8601String(),
            'created_at'      => $contact->created_at?->toIso8601String(),
            'updated_at'      => $contact->updated_at?->toIso8601String(),
            'custocare_user_name' => $this->custocareUserName($contact),
            'linked_user'         => $contact->linkedUser ? [
                'id'           => $contact->linkedUser->id,
                'display_name' => $contact->linkedUser->display_name,
                'first_name'   => $contact->linkedUser->first_name,
                'last_name'    => $contact->linkedUser->last_name,
            ] : null,
        ];
    }

    /**
     * @throws InvalidArgumentException
     */
    private function resolveLinkedUserId(?string $emailPlain, ?string $phonePlain): int
    {
        $emailUserId = null;
        $phoneUserId = null;

        if ($emailPlain !== null) {
            $emailUserId = User::query()
                ->where('email_hash', hash('sha256', $emailPlain))
                ->value('id');

            if (!$emailUserId) {
                throw new InvalidArgumentException('This email is not registered on Custocare.');
            }
        }

        if ($phonePlain !== null) {
            $phoneUserId = User::query()
                ->where('phone_hash', hash('sha256', $phonePlain))
                ->value('id');

            if (!$phoneUserId) {
                throw new InvalidArgumentException('This phone number is not registered on Custocare.');
            }
        }

        if ($emailUserId !== null && $phoneUserId !== null && $emailUserId !== $phoneUserId) {
            throw new InvalidArgumentException('Email and phone must belong to the same Custocare account.');
        }

        return (int) ($emailUserId ?? $phoneUserId);
    }

    private function custocareUserName(UserMessageContact $contact): ?string
    {
        if (!$contact->linkedUser) {
            return null;
        }

        $user = $contact->linkedUser;
        $fromDisplay = trim((string) ($user->display_name ?? ''));
        if ($fromDisplay !== '') {
            return $fromDisplay;
        }

        $fromNames = trim(((string) ($user->first_name ?? '')).' '.((string) ($user->last_name ?? '')));

        return $fromNames !== '' ? $fromNames : null;
    }

    /**
     * @param  array{display_name?: string, email?: string|null, phone?: string|null}  $data
     * @return array<string, mixed>
     */
    private function buildPersistPayload(int $ownerUserId, array $data, ?UserMessageContact $existing): array
    {
        $displayName = trim((string) ($data['display_name'] ?? $existing?->display_name ?? ''));
        if ($displayName === '') {
            throw new InvalidArgumentException('Display name is required.');
        }

        $emailPlain = array_key_exists('email', $data)
            ? ($data['email'] !== null && trim((string) $data['email']) !== '' ? strtolower(trim((string) $data['email'])) : null)
            : ($existing?->email_hash ? $this->decryptEmail($existing) : null);

        $phonePlain = array_key_exists('phone', $data)
            ? ($data['phone'] !== null && trim((string) $data['phone']) !== '' ? $this->normalizePhone((string) $data['phone']) : null)
            : ($existing?->phone_hash ? $this->decryptPhone($existing) : null);

        if ($emailPlain === null && $phonePlain === null) {
            throw new InvalidArgumentException('Provide at least an email or a phone number.');
        }

        $linkedUserId = $this->resolveLinkedUserId($emailPlain, $phonePlain);

        $payload = [
            'owner_user_id'   => $ownerUserId,
            'display_name'    => $displayName,
            'linked_user_id'  => $linkedUserId,
            'email_encrypted' => null,
            'email_hash'      => null,
            'phone_encrypted' => null,
            'phone_hash'      => null,
        ];

        if ($emailPlain !== null) {
            $payload['email_encrypted'] = encrypt($emailPlain);
            $payload['email_hash']      = hash('sha256', $emailPlain);
        }

        if ($phonePlain !== null) {
            $payload['phone_encrypted'] = encrypt($phonePlain);
            $payload['phone_hash']      = hash('sha256', $phonePlain);
        }

        return $payload;
    }

    private function normalizePhone(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }
        $normalized = preg_replace('/(?!^\+)[^\d]/', '', trim($value));

        return $normalized !== '' ? $normalized : null;
    }

    private function decryptEmail(UserMessageContact $contact): ?string
    {
        if (!$contact->email_encrypted) {
            return null;
        }
        try {
            return decrypt($contact->email_encrypted);
        } catch (\Throwable) {
            return null;
        }
    }

    private function decryptPhone(UserMessageContact $contact): ?string
    {
        if (!$contact->phone_encrypted) {
            return null;
        }
        try {
            return decrypt($contact->phone_encrypted);
        } catch (\Throwable) {
            return null;
        }
    }

    private function isDuplicateContact(QueryException $e): bool
    {
        $message = strtolower($e->getMessage());

        return str_contains($message, 'umc_owner_email_unique')
            || str_contains($message, 'umc_owner_phone_unique')
            || str_contains($message, 'duplicate');
    }
}
