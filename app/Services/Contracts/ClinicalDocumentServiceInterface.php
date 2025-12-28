<?php

namespace App\Services\Contracts;

use App\Models\ClinicalDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ClinicalDocumentServiceInterface
{
    /**
     * Get all clinical documents with pagination and filters
     *
     * @param array $filters
     * @param int $perPage
     * @return array
     */
    public function getAllDocuments(array $filters = [], int $perPage = 20): array;

    /**
     * Get clinical document by ID
     *
     * @param int $id
     * @return array
     */
    public function getDocumentById(int $id): array;

    /**
     * Get clinical document by UUID
     *
     * @param string $uuid
     * @return array
     */
    public function getDocumentByUuid(string $uuid): array;

    /**
     * Get documents by patient ID
     *
     * @param int $patientId
     * @param array $filters
     * @param int $perPage
     * @return array
     */
    public function getDocumentsByPatient(int $patientId, array $filters = [], int $perPage = 20): array;

    /**
     * Create a new clinical document with file upload
     *
     * @param array $data
     * @param UploadedFile $file
     * @return array
     */
    public function createDocument(array $data, UploadedFile $file): array;

    /**
     * Update an existing clinical document
     *
     * @param int $id
     * @param array $data
     * @return array
     */
    public function updateDocument(int $id, array $data): array;

    /**
     * Delete a clinical document (soft delete)
     *
     * @param int $id
     * @return array
     */
    public function deleteDocument(int $id): array;

    /**
     * Update document status
     *
     * @param int $id
     * @param string $status
     * @return array
     */
    public function updateDocumentStatus(int $id, string $status): array;

    /**
     * Download document file
     *
     * @param int $id
     * @return array
     */
    public function downloadDocument(int $id): array;

    /**
     * Verify document integrity using SHA-256 hash
     *
     * @param int $id
     * @return array
     */
    public function verifyDocumentIntegrity(int $id): array;

    /**
     * Get document statistics
     *
     * @param int|null $facilityId
     * @return array
     */
    public function getStatistics(?int $facilityId = null): array;
}