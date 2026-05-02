<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\Models\Consultation;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface ConsultationServiceInterface
{
    /**
     * Get all consultations with pagination.
     *
     * @param array $filters
     * @param int $perPage
     * @return array{success: bool, data: LengthAwarePaginator|array, message: string, error?: string}
     */
    public function getAllConsultations(array $filters = [], int $perPage = 20): array;

    /**
     * Get consultation by ID.
     *
     * @param int $id
     * @return array{success: bool, data: Consultation|array|null, message: string, error?: string}
     */
    public function getConsultationById(int $id): array;

    /**
     * Get consultations for a patient.
     *
     * @param int $patientId
     * @param array $filters
     * @param int $perPage
     * @return array{success: bool, data: LengthAwarePaginator|array, message: string, error?: string}
     */
    public function getPatientConsultations(int $patientId, array $filters = [], int $perPage = 20): array;

    /**
     * Get consultations for a visit.
     *
     * @param int $visitId
     * @return array{success: bool, data: Collection|array, message: string, error?: string}
     */
    public function getVisitConsultations(int $visitId): array;

    /**
     * Create a new consultation request.
     *
     * @param array $data
     * @param int $requestedByStaffId
     * @return array{success: bool, data: Consultation|array|null, message: string, error?: string}
     */
    public function createConsultation(array $data, int $requestedByStaffId): array;

    /**
     * Update an existing consultation.
     *
     * @param int $id
     * @param array $data
     * @param int $updatedByStaffId
     * @return array{success: bool, data: Consultation|array|null, message: string, error?: string}
     */
    public function updateConsultation(int $id, array $data, int $updatedByStaffId): array;

    /**
     * Delete a consultation (soft delete).
     *
     * @param int $id
     * @return array{success: bool, message: string, error?: string}
     */
    public function deleteConsultation(int $id): array;

    /**
     * Accept a consultation request.
     *
     * @param int $id
     * @param int $consultantStaffId
     * @return array{success: bool, data: Consultation|array|null, message: string, error?: string}
     */
    public function acceptConsultation(int $id, int $consultantStaffId): array;

    /**
     * Decline a consultation request.
     *
     * @param int $id
     * @param string|null $reason
     * @return array{success: bool, data: Consultation|array|null, message: string, error?: string}
     */
    public function declineConsultation(int $id, ?string $reason = null): array;

    /**
     * Complete a consultation.
     *
     * @param int $id
     * @param array|null $findings
     * @param array|null $recommendations
     * @return array{success: bool, data: Consultation|array|null, message: string, error?: string}
     */
    public function completeConsultation(int $id, ?array $findings = null, ?array $recommendations = null): array;

    /**
     * Cancel a consultation request.
     *
     * @param int $id
     * @param string|null $reason
     * @return array{success: bool, data: Consultation|array|null, message: string, error?: string}
     */
    public function cancelConsultation(int $id, ?string $reason = null): array;

    /**
     * Schedule a consultation.
     *
     * @param int $id
     * @param string $scheduledFor
     * @param string|null $location
     * @param int|null $durationMinutes
     * @return array{success: bool, data: Consultation|array|null, message: string, error?: string}
     */
    public function scheduleConsultation(int $id, string $scheduledFor, ?string $location = null, ?int $durationMinutes = null): array;

    /**
     * Get pending consultations.
     *
     * @param int|null $facilityId
     * @param int $limit
     * @return array{success: bool, data: Collection|array, message: string, error?: string}
     */
    public function getPendingConsultations(?int $facilityId = null, int $limit = 50): array;

    /**
     * Get urgent consultations.
     *
     * @param int|null $facilityId
     * @param int $limit
     * @return array{success: bool, data: Collection|array, message: string, error?: string}
     */
    public function getUrgentConsultations(?int $facilityId = null, int $limit = 50): array;

    /**
     * Get overdue consultations.
     *
     * @param int|null $facilityId
     * @param int $limit
     * @return array{success: bool, data: Collection|array, message: string, error?: string}
     */
    public function getOverdueConsultations(?int $facilityId = null, int $limit = 50): array;

    /**
     * Get consultation statistics for a facility.
     *
     * @param int $facilityId
     * @param string $startDate
     * @param string $endDate
     * @return array{success: bool, data: array, message: string, error?: string}
     */
    public function getConsultationStatistics(int $facilityId, string $startDate, string $endDate): array;

    /**
     * Get consultation count by status.
     *
     * @param int $facilityId
     * @return array{success: bool, data: array, message: string, error?: string}
     */
    public function getConsultationCountByStatus(int $facilityId): array;

    /**
     * Get consultations by specialty.
     *
     * @param string $specialty
     * @param int|null $facilityId
     * @param int $limit
     * @return array{success: bool, data: Collection|array, message: string, error?: string}
     */
    public function getConsultationsBySpecialty(string $specialty, ?int $facilityId = null, int $limit = 50): array;
}