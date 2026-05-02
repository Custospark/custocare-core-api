<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\ClinicalNote;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface ClinicalNoteRepositoryInterface
{
    /**
     * Find clinical note by ID.
     *
     * @param int $id
     * @return ClinicalNote|null
     */
    public function findById(int $id): ?ClinicalNote;

    /**
     * Find clinical note by ID with relationships.
     *
     * @param int $id
     * @param array $relations
     * @return ClinicalNote|null
     */
    public function findByIdWithRelations(int $id, array $relations = []): ?ClinicalNote;

    /**
     * Get all clinical notes with pagination.
     *
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getAllPaginated(array $filters = [], int $perPage = 20): LengthAwarePaginator;

    /**
     * Get all clinical notes by patient.
     *
     * @param int $patientId
     * @param array $filters
     * @return Collection
     */
    public function getByPatient(int $patientId, array $filters = []): Collection;

    /**
     * Get paginated clinical notes by patient.
     *
     * @param int $patientId
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getPaginatedByPatient(int $patientId, array $filters = [], int $perPage = 20): LengthAwarePaginator;

    /**
     * Get all clinical notes by visit.
     *
     * @param int $visitId
     * @return Collection
     */
    public function getByVisit(int $visitId): Collection;

    /**
     * Get clinical notes by facility.
     *
     * @param int $facilityId
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getByFacility(int $facilityId, array $filters = [], int $perPage = 20): LengthAwarePaginator;

    /**
     * Get final notes by patient.
     *
     * @param int $patientId
     * @param int $limit
     * @return Collection
     */
    public function getFinalNotesByPatient(int $patientId, int $limit = 10): Collection;

    /**
     * Get note history including amendments.
     *
     * @param int $noteId
     * @return Collection
     */
    public function getNoteHistory(int $noteId): Collection;

    /**
     * Create a new clinical note.
     *
     * @param array $data
     * @return ClinicalNote
     */
    public function create(array $data): ClinicalNote;

    /**
     * Update an existing clinical note.
     *
     * @param ClinicalNote $note
     * @param array $data
     * @return bool
     */
    public function update(ClinicalNote $note, array $data): bool;

    /**
     * Delete a clinical note (soft delete).
     *
     * @param ClinicalNote $note
     * @return bool
     */
    public function delete(ClinicalNote $note): bool;

    /**
     * Restore a soft-deleted clinical note.
     *
     * @param int $id
     * @return bool
     */
    public function restore(int $id): bool;

    /**
     * Force delete a clinical note.
     *
     * @param int $id
     * @return bool
     */
    public function forceDelete(int $id): bool;

    /**
     * Update note status.
     *
     * @param ClinicalNote $note
     * @param string $status
     * @return bool
     */
    public function updateStatus(ClinicalNote $note, string $status): bool;

    /**
     * Get notes by date range.
     *
     * @param int $patientId
     * @param string $startDate
     * @param string $endDate
     * @return Collection
     */
    public function getByDateRange(int $patientId, string $startDate, string $endDate): Collection;

    /**
     * Search notes by content.
     *
     * @param string $searchTerm
     * @param int|null $facilityId
     * @param int $limit
     * @return Collection
     */
    public function searchNotes(string $searchTerm, ?int $facilityId = null, int $limit = 20): Collection;

    /**
     * Get note count by status for a facility.
     *
     * @param int $facilityId
     * @return array
     */
    public function getNoteCountByStatus(int $facilityId): array;

    /**
     * Get latest note for a patient.
     *
     * @param int $patientId
     * @return ClinicalNote|null
     */
    public function getLatestNote(int $patientId): ?ClinicalNote;
}