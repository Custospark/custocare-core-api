<?php

namespace App\Services\Contracts;

use App\Models\AiAssessment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;

interface AiAssessmentServiceInterface
{
    /**
     * Get all AI assessments with pagination
     *
     * @param array $filters
     * @param int $perPage
     * @return array
     */
    public function getAllAssessments(array $filters = [], int $perPage = 20): array;

    /**
     * Get AI assessment by UUID
     *
     * @param string $uuid
     * @return array
     */
    public function getAssessmentByUuid(string $uuid): array;

    /**
     * Create new AI assessment
     *
     * @param array $data
     * @return array
     */
    public function createAssessment(array $data): array;

    /**
     * Update AI assessment
     *
     * @param string $uuid
     * @param array $data
     * @return array
     */
    public function updateAssessment(string $uuid, array $data): array;

    /**
     * Delete AI assessment
     *
     * @param string $uuid
     * @return array
     */
    public function deleteAssessment(string $uuid): array;

    /**
     * Review AI assessment
     *
     * @param string $uuid
     * @param array $reviewData
     * @return array
     */
    public function reviewAssessment(string $uuid, array $reviewData): array;

    /**
     * Record clinical outcome
     *
     * @param string $uuid
     * @param array $outcomeData
     * @return array
     */
    public function recordClinicalOutcome(string $uuid, array $outcomeData): array;

    /**
     * Flag adverse event
     *
     * @param string $uuid
     * @param array $eventData
     * @return array
     */
    public function flagAdverseEvent(string $uuid, array $eventData): array;

    /**
     * Get assessments by patient
     *
     * @param int $patientId
     * @param array $filters
     * @return array
     */
    public function getPatientAssessments(int $patientId, array $filters = []): array;

    /**
     * Get assessments by encounter
     *
     * @param int $encounterId
     * @return array
     */
    public function getEncounterAssessments(int $encounterId): array;

    /**
     * Get pending reviews
     *
     * @param int $facilityId
     * @return array
     */
    public function getPendingReviews(int $facilityId): array;

    /**
     * Get model statistics
     *
     * @param int $facilityId
     * @param string|null $timePeriod
     * @return array
     */
    public function getModelStatistics(int $facilityId, ?string $timePeriod = null): array;

    /**
     * Validate AI model input
     *
     * @param array $inputFeatures
     * @param string $modelType
     * @return array
     */
    public function validateInputFeatures(array $inputFeatures, string $modelType): array;

    /**
     * Generate explanation from AI output
     *
     * @param array $predictions
     * @param array $confidenceScores
     * @return string
     */
    public function generateExplanation(array $predictions, array $confidenceScores): string;

    /**
     * Calculate risk stratification
     *
     * @param array $riskScores
     * @return string
     */
    public function calculateRiskLevel(array $riskScores): string;

    /**
     * Export assessments to CSV
     *
     * @param array $filters
     * @return string
     */
    public function exportToCsv(array $filters): string;

    /**
     * Import assessments from file
     *
     * @param UploadedFile $file
     * @param int $facilityId
     * @return array
     */
    public function importFromFile(UploadedFile $file, int $facilityId): array;
}