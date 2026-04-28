<?php

declare(strict_types=1);

namespace App\Services\Lab\Contracts;

use App\Models\LabRequest;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface LabRequestServiceInterface
{
    /**
     * Get all requests with pagination.
     *
     * @param array $filters
     * @param int $perPage
     * @return array{success: bool, data: array, message: string, error?: string}
     */
    public function getAllRequests(array $filters = [], int $perPage = 20): array;

    /**
     * Get request by UUID.
     *
     * @param string $uuid
     * @return array{success: bool, data: array, message: string, error?: string}
     */
    public function getRequestByUuid(string $uuid): array;

    /**
     * Get request by ID.
     *
     * @param int $id
     * @return array{success: bool, data: array, message: string, error?: string}
     */
    public function getRequestById(int $id): array;

    /**
     * Create a new request.
     *
     * @param array $data
     * @return array{success: bool, data: array, message: string, error?: string}
     */
    public function createRequest(array $data): array;

    /**
     * Update an existing request.
     *
     * @param string $uuid
     * @param array $data
     * @return array{success: bool, data: array, message: string, error?: string}
     */
    public function updateRequest(string $uuid, array $data): array;

    /**
     * Delete a request.
     *
     * @param string $uuid
     * @return array{success: bool, data: array, message: string, error?: string}
     */
    public function deleteRequest(string $uuid): array;

    /**
     * Update request status.
     *
     * @param string $uuid
     * @param string $status
     * @return array{success: bool, data: array, message: string, error?: string}
     */
    public function updateRequestStatus(string $uuid, string $status): array;

    /**
     * Cancel a request.
     *
     * @param string $uuid
     * @param string $reason
     * @param int|null $cancelledByStaffId
     * @return array{success: bool, data: array, message: string, error?: string}
     */
    public function cancelRequest(string $uuid, string $reason, ?int $cancelledByStaffId = null): array;

    /**
     * Get requests by facility.
     *
     * @param int $facilityId
     * @param array $filters
     * @param int $perPage
     * @return array{success: bool, data: array, message: string, error?: string}
     */
    public function getRequestsByFacility(int $facilityId, array $filters = [], int $perPage = 20): array;

    /**
     * Get requests by patient.
     *
     * @param int $patientId
     * @param array $filters
     * @param int $perPage
     * @return array{success: bool, data: array, message: string, error?: string}
     */
    public function getRequestsByPatient(int $patientId, array $filters = [], int $perPage = 20): array;

    /**
     * Get requests by visit.
     *
     * @param int $visitId
     * @return array{success: bool, data: array, message: string, error?: string}
     */
    public function getRequestsByVisit(int $visitId): array;

    /**
     * Get requests by status.
     *
     * @param string $status
     * @param int|null $facilityId
     * @return array{success: bool, data: array, message: string, error?: string}
     */
    public function getRequestsByStatus(string $status, ?int $facilityId = null): array;

    /**
     * Get pending requests.
     *
     * @param int|null $facilityId
     * @return array{success: bool, data: array, message: string, error?: string}
     */
    public function getPendingRequests(?int $facilityId = null): array;

    /**
     * Get requests requiring attention.
     *
     * @param int $facilityId
     * @return array{success: bool, data: array, message: string, error?: string}
     */
    public function getRequestsRequiringAttention(int $facilityId): array;

    /**
     * Get request with items.
     *
     * @param string $uuid
     * @return array{success: bool, data: array, message: string, error?: string}
     */
    public function getRequestWithItems(string $uuid): array;

    /**
     * Get request with full details.
     *
     * @param string $uuid
     * @return array{success: bool, data: array, message: string, error?: string}
     */
    public function getRequestWithFullDetails(string $uuid): array;

    /**
     * Get request statistics.
     *
     * @param int $facilityId
     * @param string $startDate
     * @param string $endDate
     * @return array{success: bool, data: array, message: string, error?: string}
     */
    public function getRequestStatistics(int $facilityId, string $startDate, string $endDate): array;

    /**
     * Create request with items.
     *
     * @param array $requestData
     * @param array $itemsData
     * @return array{success: bool, data: array, message: string, error?: string}
     */
    public function createRequestWithItems(array $requestData, array $itemsData): array;

    /**
     * Add items to existing request.
     *
     * @param string $requestUuid
     * @param array $itemsData
     * @return array{success: bool, data: array, message: string, error?: string}
     */
    public function addItemsToRequest(string $requestUuid, array $itemsData): array;
}