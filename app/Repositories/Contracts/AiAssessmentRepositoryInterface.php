<?php

namespace App\Repositories\Contracts;

use App\Models\AiAssessment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface AiAssessmentRepositoryInterface
{
    /**
     * Find AI assessment by UUID
     *
     * @param string $uuid
     * @return AiAssessment|null
     */
    public function findByUuid(string $uuid): ?AiAssessment;

    /**
     * Find AI assessment by ID
     *
     * @param int $id
     * @return AiAssessment|null
     */
    public function findById(int $id): ?AiAssessment;

    /**
     * Get all AI assessments with pagination
     *
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getAllPaginated(array $filters = [], int $perPage = 20): LengthAwarePaginator;

    /**
     * Get AI assessments by patient ID
     *
     * @param int $patientId
     * @param array $filters
     * @return Collection
     */
    public function getByPatientId(int $patientId, array $filters = []): Collection;

    /**
     * Get AI assessments by clinical encounter ID
     *
     * @param int $encounterId
     * @return Collection
     */
    public function getByEncounterId(int $encounterId): Collection;

    /**
     * Get AI assessments by model type
     *
     * @param string $modelType
     * @param array $filters
     * @return Collection
     */
    public function getByModelType(string $modelType, array $filters = []): Collection;

    /**
     * Get pending reviews
     *
     * @param int $facilityId
     * @return Collection
     */
    public function getPendingReviews(int $facilityId): Collection;

    /**
     * Create new AI assessment
     *
     * @param array $data
     * @return AiAssessment
     */
    public function create(array $data): AiAssessment;

    /**
     * Update AI assessment
     *
     * @param AiAssessment $assessment
     * @param array $data
     * @return bool
     */
    public function update(AiAssessment $assessment, array $data): bool;

    /**
     * Delete AI assessment
     *
     * @param AiAssessment $assessment
     * @return bool|null
     */
    public function delete(AiAssessment $assessment): ?bool;

    /**
     * Restore soft deleted AI assessment
     *
     * @param AiAssessment $assessment
     * @return bool
     */
    public function restore(AiAssessment $assessment): bool;

    /**
     * Update review status
     *
     * @param AiAssessment $assessment
     * @param string $status
     * @param array $reviewData
     * @return bool
     */
    public function updateReviewStatus(AiAssessment $assessment, string $status, array $reviewData = []): bool;

    /**
     * Record clinical outcome
     *
     * @param AiAssessment $assessment
     * @param array $outcomeData
     * @return bool
     */
    public function recordOutcome(AiAssessment $assessment, array $outcomeData): bool;

    /**
     * Flag adverse event
     *
     * @param AiAssessment $assessment
     * @param array $eventData
     * @return bool
     */
    public function flagAdverseEvent(AiAssessment $assessment, array $eventData): bool;

    /**
     * Get statistics by model
     *
     * @param int $facilityId
     * @param string|null $timePeriod
     * @return array
     */
    public function getModelStatistics(int $facilityId, ?string $timePeriod = null): array;
}