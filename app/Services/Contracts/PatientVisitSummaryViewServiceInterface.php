<?php

namespace App\Services\Contracts;

use App\Models\PatientVisitSummaryView;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface PatientVisitSummaryViewServiceInterface
{
    /**
     * Get a summary view by ID.
     *
     * @param int $id
     * @return array
     */
    public function getSummaryViewById(int $id): array;

    /**
     * Get a summary view by patient ID.
     *
     * @param int $patientId
     * @return array
     */
    public function getSummaryByPatientId(int $patientId): array;

    /**
     * Get all summary views with pagination.
     *
     * @param array $filters
     * @param int $perPage
     * @return array
     */
    public function getAllSummaries(array $filters = [], int $perPage = 20): array;

    /**
     * Create a new summary view.
     *
     * @param array $data
     * @return array
     */
    public function createSummaryView(array $data): array;

    /**
     * Update an existing summary view.
     *
     * @param int $id
     * @param array $data
     * @return array
     */
    public function updateSummaryView(int $id, array $data): array;

    /**
     * Refresh a summary view for a patient.
     *
     * @param int $patientId
     * @return array
     */
    public function refreshSummaryView(int $patientId): array;

    /**
     * Batch refresh multiple summary views.
     *
     * @param array $patientIds
     * @return array
     */
    public function batchRefreshSummaryViews(array $patientIds): array;

    /**
     * Delete a summary view.
     *
     * @param int $id
     * @return array
     */
    public function deleteSummaryView(int $id): array;

    /**
     * Get summaries with upcoming appointments.
     *
     * @param string $startDate
     * @param string $endDate
     * @return array
     */
    public function getUpcomingAppointments(string $startDate, string $endDate): array;

    /**
     * Get health metrics trends for a patient.
     *
     * @param int $patientId
     * @return array
     */
    public function getHealthMetricsTrends(int $patientId): array;

    /**
     * Get care coordination insights.
     *
     * @param array $filters
     * @return array
     */
    public function getCareCoordinationInsights(array $filters = []): array;
}