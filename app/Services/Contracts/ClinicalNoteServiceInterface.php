<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\Models\ClinicalNote;
use App\Models\Patient;
use App\Models\Visit;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface ClinicalNoteServiceInterface
{
    /**
     * Get all clinical notes with pagination.
     *
     * @param array $filters
     * @param int $perPage
     * @return array{success: bool, data: LengthAwarePaginator|array, message: string, error?: string}
     */
    public function getAllNotes(array $filters = [], int $perPage = 20): array;

    /**
     * Get clinical note by UUID.
     *
     * @param string $uuid
     * @return array{success: bool, data: ClinicalNote|array|null, message: string, error?: string}
     */
    public function getNoteByUuid(string $uuid): array;

    /**
     * Get clinical note by ID.
     *
     * @param int $id
     * @return array{success: bool, data: ClinicalNote|array|null, message: string, error?: string}
     */
    public function getNoteById(int $id): array;

    /**
     * Get notes for a patient.
     *
     * @param int $patientId
     * @param array $filters
     * @param int $perPage
     * @return array{success: bool, data: LengthAwarePaginator|array, message: string, error?: string}
     */
    public function getPatientNotes(int $patientId, array $filters = [], int $perPage = 20): array;

    /**
     * Get notes for a visit.
     *
     * @param int $visitId
     * @return array{success: bool, data: Collection|array, message: string, error?: string}
     */
    public function getVisitNotes(int $visitId): array;

    /**
     * Create a new clinical note.
     *
     * @param array $data
     * @param int $createdByStaffId
     * @return array{success: bool, data: ClinicalNote|array|null, message: string, error?: string}
     */
    public function createNote(array $data, int $createdByStaffId): array;

    /**
     * Update an existing clinical note.
     *
     * @param string $uuid
     * @param array $data
     * @param int $updatedByStaffId
     * @return array{success: bool, data: ClinicalNote|array|null, message: string, error?: string}
     */
    public function updateNote(string $uuid, array $data, int $updatedByStaffId): array;

    /**
     * Delete a clinical note (soft delete).
     *
     * @param string $uuid
     * @return array{success: bool, message: string, error?: string}
     */
    public function deleteNote(string $uuid): array;

    /**
     * Restore a soft-deleted clinical note.
     *
     * @param string $uuid
     * @return array{success: bool, data: ClinicalNote|array|null, message: string, error?: string}
     */
    public function restoreNote(string $uuid): array;

    /**
     * Force delete a clinical note.
     *
     * @param string $uuid
     * @return array{success: bool, message: string, error?: string}
     */
    public function forceDeleteNote(string $uuid): array;

    /**
     * Finalize a draft note.
     *
     * @param string $uuid
     * @return array{success: bool, data: ClinicalNote|array|null, message: string, error?: string}
     */
    public function finalizeNote(string $uuid): array;

    /**
     * Cancel a note.
     *
     * @param string $uuid
     * @param string|null $reason
     * @return array{success: bool, data: ClinicalNote|array|null, message: string, error?: string}
     */
    public function cancelNote(string $uuid, ?string $reason = null): array;

    /**
     * Amend an existing note.
     *
     * @param string $uuid
     * @param array $amendedData
     * @param int $amendedByStaffId
     * @return array{success: bool, data: ClinicalNote|array|null, message: string, error?: string}
     */
    public function amendNote(string $uuid, array $amendedData, int $amendedByStaffId): array;

    /**
     * Get note history including amendments.
     *
     * @param string $uuid
     * @return array{success: bool, data: Collection|array, message: string, error?: string}
     */
    public function getNoteHistory(string $uuid): array;

    /**
     * Get notes by date range for a patient.
     *
     * @param int $patientId
     * @param string $startDate
     * @param string $endDate
     * @return array{success: bool, data: Collection|array, message: string, error?: string}
     */
    public function getNotesByDateRange(int $patientId, string $startDate, string $endDate): array;

    /**
     * Search notes by content.
     *
     * @param string $searchTerm
     * @param int|null $facilityId
     * @param int $limit
     * @return array{success: bool, data: Collection|array, message: string, error?: string}
     */
    public function searchNotes(string $searchTerm, ?int $facilityId = null, int $limit = 20): array;

    /**
     * Get note statistics for a facility.
     *
     * @param int $facilityId
     * @return array{success: bool, data: array, message: string, error?: string}
     */
    public function getNoteStatistics(int $facilityId): array;

    /**
     * Get latest note for a patient.
     *
     * @param int $patientId
     * @return array{success: bool, data: ClinicalNote|array|null, message: string, error?: string}
     */
    public function getLatestPatientNote(int $patientId): array;

    /**
     * Validate that a note can be amended.
     *
     * @param ClinicalNote $note
     * @return bool
     */
    public function canAmendNote(ClinicalNote $note): bool;

    /**
     * Generate a PDF of the clinical note.
     *
     * @param string $uuid
     * @return array{success: bool, data: string|array|null, message: string, error?: string}
     */
    public function generateNotePdf(string $uuid): array;
}