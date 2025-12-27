<?php

namespace App\Services\Contracts;

use App\Models\VisitEvent;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Carbon\Carbon;

interface VisitEventServiceInterface
{
    /**
     * Record a new visit event.
     *
     * @param array $eventData
     * @return array
     */
    public function recordEvent(array $eventData): array;

    /**
     * Get visit event by UUID.
     *
     * @param string $eventUuid
     * @return array
     */
    public function getEventByUuid(string $eventUuid): array;

    /**
     * Get all events for a visit.
     *
     * @param int $visitId
     * @return array
     */
    public function getVisitEvents(int $visitId): array;

    /**
     * Get paginated visit events with filters.
     *
     * @param array $filters
     * @param int $perPage
     * @return array
     */
    public function getPaginatedEvents(array $filters = [], int $perPage = 15): array;

    /**
     * Get clinical timeline for a visit.
     *
     * @param int $visitId
     * @return array
     */
    public function getClinicalTimeline(int $visitId): array;

    /**
     * Get visit state timeline.
     *
     * @param int $visitId
     * @return array
     */
    public function getVisitStateTimeline(int $visitId): array;

    /**
     * Get facility events report.
     *
     * @param int $facilityId
     * @param Carbon $from
     * @param Carbon $to
     * @param array $eventTypes
     * @return array
     */
    public function getFacilityEventsReport(
        int $facilityId,
        Carbon $from,
        Carbon $to,
        array $eventTypes = []
    ): array;

    /**
     * Verify event chain integrity for a visit.
     *
     * @param int $visitId
     * @return array
     */
    public function verifyEventChain(int $visitId): array;

    /**
     * Get event statistics for a facility.
     *
     * @param int $facilityId
     * @param Carbon $from
     * @param Carbon $to
     * @return array
     */
    public function getEventStatistics(int $facilityId, Carbon $from, Carbon $to): array;

    /**
     * Recalculate integrity hash for an event.
     *
     * @param int $eventId
     * @return array
     */
    public function recalculateIntegrityHash(int $eventId): array;
}