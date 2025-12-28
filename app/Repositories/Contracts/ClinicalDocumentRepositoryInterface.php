<?php

namespace App\Repositories\Contracts;

use App\Models\ClinicalDocument;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface ClinicalDocumentRepositoryInterface
{
    /**
     * Find clinical document by ID
     *
     * @param int $id
     * @return ClinicalDocument|null
     */
    public function find(int $id): ?ClinicalDocument;

    /**
     * Find clinical document by UUID
     *
     * @param string $uuid
     * @return ClinicalDocument|null
     */
    public function findByUuid(string $uuid): ?ClinicalDocument;

    /**
     * Get all clinical documents with optional filters
     *
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getAll(array $filters = [], int $perPage = 20): LengthAwarePaginator;

    /**
     * Get clinical documents by patient ID
     *
     * @param int $patientId
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getByPatientId(int $patientId, array $filters = [], int $perPage = 20): LengthAwarePaginator;

    /**
     * Create a new clinical document
     *
     * @param array $data
     * @return ClinicalDocument
     */
    public function create(array $data): ClinicalDocument;

    /**
     * Update an existing clinical document
     *
     * @param int $id
     * @param array $data
     * @return ClinicalDocument
     */
    public function update(int $id, array $data): ClinicalDocument;

    /**
     * Delete a clinical document (soft delete)
     *
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool;

    /**
     * Force delete a clinical document
     *
     * @param int $id
     * @return bool
     */
    public function forceDelete(int $id): bool;

    /**
     * Restore a soft-deleted clinical document
     *
     * @param int $id
     * @return bool
     */
    public function restore(int $id): bool;

    /**
     * Update document status
     *
     * @param int $id
     * @param string $status
     * @return bool
     */
    public function updateStatus(int $id, string $status): bool;

    /**
     * Check if file hash already exists (prevent duplicate uploads)
     *
     * @param string $fileHash
     * @param int|null $patientId
     * @return bool
     */
    public function fileHashExists(string $fileHash, ?int $patientId = null): bool;
}