<?php

namespace App\Repositories\StaffCredential;

use App\Models\StaffCredential;
use App\Repositories\Contracts\StaffCredentialRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StaffCredentialRepository implements StaffCredentialRepositoryInterface
{
    /**
     * Base query with relationships
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function baseQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return StaffCredential::with(['staff', 'verifiedBy', 'createdBy', 'supersededBy']);
    }

    /**
     * {@inheritdoc}
     */
    public function findByUuid(string $uuid): ?StaffCredential
    {
        try {
            return $this->baseQuery()->where('credential_uuid', $uuid)->first();
        } catch (\Exception $e) {
            Log::error('Failed to find staff credential by UUID', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getByStaffId(int $staffId, array $filters = []): Collection
    {
        try {
            $query = $this->baseQuery()->where('staff_id', $staffId);

            // Apply filters
            if (!empty($filters['credential_type'])) {
                $query->where('credential_type', $filters['credential_type']);
            }

            if (!empty($filters['verification_status'])) {
                $query->where('verification_status', $filters['verification_status']);
            }

            if (isset($filters['is_current'])) {
                $query->where('is_current', (bool) $filters['is_current']);
            }

            if (!empty($filters['valid_from'])) {
                $query->where('valid_from', '>=', $filters['valid_from']);
            }

            if (!empty($filters['valid_to'])) {
                $query->where('valid_to', '<=', $filters['valid_to']);
            }

            // Order by most recent first
            $query->orderBy('created_at', 'desc');

            return $query->get();
        } catch (\Exception $e) {
            Log::error('Failed to get staff credentials by staff ID', [
                'staff_id' => $staffId,
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);
            return new Collection();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getCurrentByStaffId(int $staffId): Collection
    {
        try {
            return $this->baseQuery()
                ->where('staff_id', $staffId)
                ->where('is_current', true)
                ->orderBy('credential_type')
                ->get();
        } catch (\Exception $e) {
            Log::error('Failed to get current staff credentials', [
                'staff_id' => $staffId,
                'error' => $e->getMessage()
            ]);
            return new Collection();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getByType(string $type, array $filters = []): LengthAwarePaginator
    {
        try {
            $query = $this->baseQuery()->where('credential_type', $type);

            if (!empty($filters['verification_status'])) {
                $query->where('verification_status', $filters['verification_status']);
            }

            if (isset($filters['is_current'])) {
                $query->where('is_current', (bool) $filters['is_current']);
            }

            return $query->paginate($filters['per_page'] ?? 15);
        } catch (\Exception $e) {
            Log::error('Failed to get credentials by type', [
                'type' => $type,
                'error' => $e->getMessage()
            ]);
            return new \Illuminate\Pagination\LengthAwarePaginator([], 0, 15);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getExpiringSoon(int $days = 30): Collection
    {
        try {
            return $this->baseQuery()
                ->where('valid_to', '<=', now()->addDays($days))
                ->where('valid_to', '>', now())
                ->where('is_current', true)
                ->where('verification_status', 'verified')
                ->orderBy('valid_to')
                ->get();
        } catch (\Exception $e) {
            Log::error('Failed to get expiring credentials', [
                'days' => $days,
                'error' => $e->getMessage()
            ]);
            return new Collection();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getExpired(): Collection
    {
        try {
            return $this->baseQuery()
                ->where('valid_to', '<', now())
                ->where('is_current', true)
                ->where('verification_status', 'verified')
                ->orderBy('valid_to', 'desc')
                ->get();
        } catch (\Exception $e) {
            Log::error('Failed to get expired credentials', [
                'error' => $e->getMessage()
            ]);
            return new Collection();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function create(array $data): StaffCredential
    {
        try {
            return DB::transaction(function () use ($data) {
                // Ensure credential_uuid is set
                if (empty($data['credential_uuid'])) {
                    $data['credential_uuid'] = (string) \Illuminate\Support\Str::uuid();
                }

                // Set snapshot time
                if (empty($data['snapshot_taken_at'])) {
                    $data['snapshot_taken_at'] = now();
                }

                // Create the credential
                $credential = StaffCredential::create($data);

                // Load relationships
                $credential->load(['staff', 'createdBy']);

                return $credential;
            });
        } catch (\Exception $e) {
            Log::error('Failed to create staff credential', [
                'data' => $data,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function update(string $uuid, array $data): StaffCredential
    {
        try {
            return DB::transaction(function () use ($uuid, $data) {
                $credential = $this->findByUuid($uuid);
                
                if (!$credential) {
                    throw new ModelNotFoundException("Credential with UUID {$uuid} not found");
                }

                // Remove fields that shouldn't be updated directly
                unset(
                    $data['credential_uuid'],
                    $data['staff_id'],
                    $data['snapshot_taken_at']
                );

                $credential->update($data);
                $credential->refresh();
                $credential->load(['staff', 'verifiedBy', 'createdBy']);

                return $credential;
            });
        } catch (ModelNotFoundException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Failed to update staff credential', [
                'uuid' => $uuid,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $uuid): bool
    {
        try {
            $credential = $this->findByUuid($uuid);
            
            if (!$credential) {
                return false;
            }

            // Cannot delete current verified credentials
            if ($credential->is_current && $credential->verification_status === 'verified') {
                throw new \RuntimeException('Cannot delete current verified credential. Supersede it first.');
            }

            return $credential->delete();
        } catch (\Exception $e) {
            Log::error('Failed to delete staff credential', [
                'uuid' => $uuid,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function restore(string $uuid): bool
    {
        try {
            $credential = StaffCredential::withTrashed()->where('credential_uuid', $uuid)->first();
            
            if (!$credential) {
                return false;
            }

            return $credential->restore();
        } catch (\Exception $e) {
            Log::error('Failed to restore staff credential', [
                'uuid' => $uuid,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function forceDelete(string $uuid): bool
    {
        try {
            $credential = StaffCredential::withTrashed()->where('credential_uuid', $uuid)->first();
            
            if (!$credential) {
                return false;
            }

            return $credential->forceDelete();
        } catch (\Exception $e) {
            Log::error('Failed to force delete staff credential', [
                'uuid' => $uuid,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function verify(string $uuid, array $verificationData): StaffCredential
    {
        try {
            return DB::transaction(function () use ($uuid, $verificationData) {
                $credential = $this->findByUuid($uuid);
                
                if (!$credential) {
                    throw new ModelNotFoundException("Credential with UUID {$uuid} not found");
                }

                $updateData = [
                    'verification_status' => 'verified',
                    'verified_at' => now(),
                    'verified_by_staff_id' => $verificationData['verified_by_staff_id'] ?? null,
                    'verification_method' => $verificationData['verification_method'] ?? null,
                    'verification_notes' => $verificationData['verification_notes'] ?? null,
                ];

                // If verification method not provided, set default
                if (empty($updateData['verification_method'])) {
                    $updateData['verification_method'] = 'document_review';
                }

                $credential->update($updateData);
                $credential->refresh();
                $credential->load(['staff', 'verifiedBy']);

                return $credential;
            });
        } catch (ModelNotFoundException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Failed to verify staff credential', [
                'uuid' => $uuid,
                'verification_data' => $verificationData,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function supersede(string $uuid, array $newCredentialData): array
    {
        try {
            return DB::transaction(function () use ($uuid, $newCredentialData) {
                $oldCredential = $this->findByUuid($uuid);
                
                if (!$oldCredential) {
                    throw new ModelNotFoundException("Credential with UUID {$uuid} not found");
                }

                // Mark old credential as superseded
                $oldCredential->update([
                    'is_current' => false,
                    'superseded_by_credential_id' => null, // Will be set after new credential creation
                ]);

                // Create new credential with reference to old one
                $newCredentialData['staff_id'] = $oldCredential->staff_id;
                $newCredentialData['credential_uuid'] = (string) \Illuminate\Support\Str::uuid();
                $newCredentialData['snapshot_taken_at'] = now();
                $newCredentialData['is_current'] = true;
                
                // Copy relevant fields if not provided
                if (empty($newCredentialData['credential_type'])) {
                    $newCredentialData['credential_type'] = $oldCredential->credential_type;
                }
                
                if (empty($newCredentialData['credential_name'])) {
                    $newCredentialData['credential_name'] = $oldCredential->credential_name;
                }

                $newCredential = StaffCredential::create($newCredentialData);

                // Update old credential with reference to new one
                $oldCredential->update(['superseded_by_credential_id' => $newCredential->id]);

                // Load relationships
                $oldCredential->load(['supersededBy']);
                $newCredential->load(['staff', 'createdBy']);

                return [
                    'old_credential' => $oldCredential,
                    'new_credential' => $newCredential,
                ];
            });
        } catch (ModelNotFoundException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Failed to supersede staff credential', [
                'uuid' => $uuid,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function search(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        try {
            $query = $this->baseQuery();

            // Apply filters
            if (!empty($filters['staff_id'])) {
                $query->where('staff_id', $filters['staff_id']);
            }

            if (!empty($filters['credential_type'])) {
                $query->where('credential_type', $filters['credential_type']);
            }

            if (!empty($filters['verification_status'])) {
                $query->where('verification_status', $filters['verification_status']);
            }

            if (isset($filters['is_current'])) {
                $query->where('is_current', (bool) $filters['is_current']);
            }

            if (!empty($filters['valid_from_start'])) {
                $query->where('valid_from', '>=', $filters['valid_from_start']);
            }

            if (!empty($filters['valid_from_end'])) {
                $query->where('valid_from', '<=', $filters['valid_from_end']);
            }

            if (!empty($filters['valid_to_start'])) {
                $query->where('valid_to', '>=', $filters['valid_to_start']);
            }

            if (!empty($filters['valid_to_end'])) {
                $query->where('valid_to', '<=', $filters['valid_to_end']);
            }

            if (!empty($filters['issued_date_start'])) {
                $query->where('issued_date', '>=', $filters['issued_date_start']);
            }

            if (!empty($filters['issued_date_end'])) {
                $query->where('issued_date', '<=', $filters['issued_date_end']);
            }

            // Search by credential name or issuing authority
            if (!empty($filters['search'])) {
                $searchTerm = $filters['search'];
                $query->where(function ($q) use ($searchTerm) {
                    $q->where('credential_name', 'like', "%{$searchTerm}%")
                      ->orWhere('issuing_authority', 'like', "%{$searchTerm}%")
                      ->orWhere('issuing_state_country', 'like', "%{$searchTerm}%");
                });
            }

            // Order by
            $orderBy = $filters['order_by'] ?? 'created_at';
            $orderDirection = $filters['order_direction'] ?? 'desc';
            $query->orderBy($orderBy, $orderDirection);

            return $query->paginate($perPage);
        } catch (\Exception $e) {
            Log::error('Failed to search staff credentials', [
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);
            return new \Illuminate\Pagination\LengthAwarePaginator([], 0, $perPage);
        }
    }
}