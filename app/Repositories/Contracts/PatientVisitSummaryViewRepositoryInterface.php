<?php

namespace App\Repositories\Contracts;

use App\Models\PatientVisitSummaryView;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface PatientVisitSummaryViewRepositoryInterface
{
    /**
     * Find a summary view by ID.
     *
     * @param int $id
     * @return PatientVisitSummaryView|null
     */
    public function findById(int $id): ?PatientVisitSummaryView;

    /**
     * Find a summary view by patient ID.
     *
     * @param int $patientId
     * @return PatientVisitSummaryView|null
     */
    public function findByPatientId(int $patientId): ?PatientVisitSummaryView;

    /**
     * Get all summary views with pagination.
     *
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function paginate(array $filters = [], int $perPage = 20): LengthAwarePaginator;

    /**
     * Create a new summary view.
     *
     * @param array $data
     * @return PatientVisitSummaryView
     */
    public function create(array $data): PatientVisitSummaryView;

    /**
     * Update an existing summary view.
     *
     * @param int $id
     * @param array $data
     * @return PatientVisitSummaryView
     */
    public function update(int $id, array $data): PatientVisitSummaryView;

    /**
     * Update or create a summary view by patient ID.
     *
     * @param int $patientId
     * @param array $data
     * @return PatientVisitSummaryView
     */
    public function updateOrCreateByPatientId(int $patientId, array $data): PatientVisitSummaryView;

    /**
     * Delete a summary view.
     *
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool;

    /**
     * Get summaries with upcoming appointments.
     *
     * @param string $startDate
     * @param string $endDate
     * @return Collection
     */
    public function getWithUpcomingAppointments(string $startDate, string $endDate): Collection;

    /**
     * Get summaries by last update date.
     *
     * @param string $date
     * @return Collection
     */
    public function getByLastUpdatedDate(string $date): Collection;

    /**
     * Get patient IDs with outdated summaries.
     *
     * @param int $hoursThreshold
     * @return array
     */
    public function getOutdatedPatientIds(int $hoursThreshold = 24): array;
}