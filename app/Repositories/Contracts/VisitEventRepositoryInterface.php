<?php

namespace App\Repositories\Contracts;

use App\Models\VisitEvent;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Carbon\Carbon;

interface VisitEventRepositoryInterface
{
    /**
     * Find a visit event by its UUID.
     *
     * @param string $eventUuid
     * @return VisitEvent|null
     */
    public function findByUuid(string $eventUuid): ?VisitEvent;

    /**
     * Find a visit event by its ID.
     *
     * @param int $id
     * @return VisitEvent|null
     */
    public function findById(int $id): ?VisitEvent;

    /**
     * Get all visit events for a specific visit.
     *
     * @param int $visitId
     * @param array $relations
     * @return Collection
     */
    public function getEventsForVisit(int $visitId, array $relations = []): Collection;

    /**
     * Get visit events by type.
     *
     * @param string|array $eventTypes
     * @param int $facilityId
     * @param Carbon|null $from
     * @param Carbon|null $to
     * @return Collection
     */
    public function getEventsByType(
        $eventTypes,
        int $facilityId,
        ?Carbon $from = null,
        ?Carbon $to = null
    ): Collection;

    /**
     * Get paginated visit events.
     *
     * @param array $filters
     * @param int $perPage
     * @param array $relations
     * @return LengthAwarePaginator
     */
    public function paginate(array $filters = [], int $perPage = 15, array $relations = []): LengthAwarePaginator;

    /**
     * Create a new visit event.
     *
     * @param array $data
     * @return VisitEvent
     */
    public function create(array $data): VisitEvent;

    /**
     * Get the last event for a visit.
     *
     * @param int $visitId
     * @return VisitEvent|null
     */
    public function getLastEventForVisit(int $visitId): ?VisitEvent;

    /**
     * Get event chain for verification.
     *
     * @param int $startEventId
     * @param int $limit
     * @return Collection
     */
    public function getEventChain(int $startEventId, int $limit = 100): Collection;

    /**
     * Get events within a time range for a facility.
     *
     * @param int $facilityId
     * @param Carbon $from
     * @param Carbon $to
     * @param array $eventTypes
     * @return Collection
     */
    public function getEventsForFacilityInRange(
        int $facilityId,
        Carbon $from,
        Carbon $to,
        array $eventTypes = []
    ): Collection;

    /**
     * Get clinical events for a visit.
     *
     * @param int $visitId
     * @return Collection
     */
    public function getClinicalEventsForVisit(int $visitId): Collection;

    /**
     * Get visit state events for a visit.
     *
     * @param int $visitId
     * @return Collection
     */
    public function getVisitStateEventsForVisit(int $visitId): Collection;

    /**
     * Verify event chain integrity for a visit.
     *
     * @param int $visitId
     * @return array
     */
    public function verifyEventChainIntegrity(int $visitId): array;
}