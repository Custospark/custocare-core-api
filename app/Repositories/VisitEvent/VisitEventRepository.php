<?php

namespace App\Repositories\VisitEvent;

use App\Models\VisitEvent;
use App\Repositories\Contracts\VisitEventRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class VisitEventRepository implements VisitEventRepositoryInterface
{
    /**
     * @var VisitEvent
     */
    protected $model;

    /**
     * VisitEventRepository constructor.
     *
     * @param VisitEvent $model
     */
    public function __construct(VisitEvent $model)
    {
        $this->model = $model;
    }

    /**
     * {@inheritdoc}
     */
    public function findByUuid(string $eventUuid): ?VisitEvent
    {
        try {
            return $this->model->where('event_uuid', $eventUuid)->first();
        } catch (\Exception $e) {
            Log::error('Failed to find visit event by UUID', [
                'uuid' => $eventUuid,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function findById(int $id): ?VisitEvent
    {
        try {
            return $this->model->find($id);
        } catch (\Exception $e) {
            Log::error('Failed to find visit event by ID', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getEventsForVisit(int $visitId, array $relations = []): Collection
    {
        try {
            $query = $this->model->where('visit_id', $visitId)
                ->orderBy('event_occurred_at', 'asc');

            if (!empty($relations)) {
                $query->with($relations);
            }

            return $query->get();
        } catch (\Exception $e) {
            Log::error('Failed to get events for visit', [
                'visit_id' => $visitId,
                'error' => $e->getMessage(),
            ]);
            return new Collection();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getEventsByType(
        $eventTypes,
        int $facilityId,
        ?Carbon $from = null,
        ?Carbon $to = null
    ): Collection {
        try {
            $query = $this->model->where('facility_id', $facilityId)
                ->ofType($eventTypes);

            if ($from) {
                $query->where('event_occurred_at', '>=', $from);
            }

            if ($to) {
                $query->where('event_occurred_at', '<=', $to);
            }

            return $query->orderBy('event_occurred_at', 'asc')->get();
        } catch (\Exception $e) {
            Log::error('Failed to get events by type', [
                'facility_id' => $facilityId,
                'event_types' => $eventTypes,
                'error' => $e->getMessage(),
            ]);
            return new Collection();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function paginate(array $filters = [], int $perPage = 15, array $relations = []): LengthAwarePaginator
    {
        try {
            $query = $this->model->query();

            // Apply filters
            if (isset($filters['facility_id'])) {
                $query->where('facility_id', $filters['facility_id']);
            }

            if (isset($filters['visit_id'])) {
                $query->where('visit_id', $filters['visit_id']);
            }

            if (isset($filters['event_type'])) {
                $eventTypes = is_array($filters['event_type']) 
                    ? $filters['event_type'] 
                    : [$filters['event_type']];
                $query->whereIn('event_type', $eventTypes);
            }

            if (isset($filters['actor_type'])) {
                $query->where('actor_type', $filters['actor_type']);
            }

            if (isset($filters['actor_id'])) {
                $query->where('actor_id', $filters['actor_id']);
            }

            if (isset($filters['from_date'])) {
                $query->where('event_occurred_at', '>=', Carbon::parse($filters['from_date']));
            }

            if (isset($filters['to_date'])) {
                $query->where('event_occurred_at', '<=', Carbon::parse($filters['to_date']));
            }

            // Apply relations
            if (!empty($relations)) {
                $query->with($relations);
            }

            return $query->orderBy('event_occurred_at', 'desc')->paginate($perPage);
        } catch (\Exception $e) {
            Log::error('Failed to paginate visit events', [
                'filters' => $filters,
                'error' => $e->getMessage(),
            ]);
            // Return empty paginator instead of throwing
            return new \Illuminate\Pagination\LengthAwarePaginator([], 0, $perPage);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function create(array $data): VisitEvent
    {
        try {
            DB::beginTransaction();

            $visitEvent = $this->model->create($data);

            DB::commit();

            return $visitEvent;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create visit event', [
                'data' => $data,
                'error' => $e->getMessage(),
            ]);
            throw new \RuntimeException('Failed to create visit event: ' . $e->getMessage());
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getLastEventForVisit(int $visitId): ?VisitEvent
    {
        try {
            return $this->model->where('visit_id', $visitId)
                ->orderBy('event_occurred_at', 'desc')
                ->first();
        } catch (\Exception $e) {
            Log::error('Failed to get last event for visit', [
                'visit_id' => $visitId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getEventChain(int $startEventId, int $limit = 100): Collection
    {
        try {
            return $this->model->where('preceding_event_id', $startEventId)
                ->orWhere('id', $startEventId)
                ->orderBy('event_occurred_at', 'asc')
                ->limit($limit)
                ->get();
        } catch (\Exception $e) {
            Log::error('Failed to get event chain', [
                'start_event_id' => $startEventId,
                'error' => $e->getMessage(),
            ]);
            return new Collection();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getEventsForFacilityInRange(
        int $facilityId,
        Carbon $from,
        Carbon $to,
        array $eventTypes = []
    ): Collection {
        try {
            $query = $this->model->where('facility_id', $facilityId)
                ->whereBetween('event_occurred_at', [$from, $to]);

            if (!empty($eventTypes)) {
                $query->whereIn('event_type', $eventTypes);
            }

            return $query->orderBy('event_occurred_at', 'asc')->get();
        } catch (\Exception $e) {
            Log::error('Failed to get events for facility in range', [
                'facility_id' => $facilityId,
                'from' => $from,
                'to' => $to,
                'error' => $e->getMessage(),
            ]);
            return new Collection();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getClinicalEventsForVisit(int $visitId): Collection
    {
        try {
            return $this->model->where('visit_id', $visitId)
                ->whereIn('event_type', VisitEvent::CLINICAL_EVENTS)
                ->orderBy('event_occurred_at', 'asc')
                ->get();
        } catch (\Exception $e) {
            Log::error('Failed to get clinical events for visit', [
                'visit_id' => $visitId,
                'error' => $e->getMessage(),
            ]);
            return new Collection();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getVisitStateEventsForVisit(int $visitId): Collection
    {
        try {
            return $this->model->where('visit_id', $visitId)
                ->whereIn('event_type', VisitEvent::VISIT_STATE_EVENTS)
                ->orderBy('event_occurred_at', 'asc')
                ->get();
        } catch (\Exception $e) {
            Log::error('Failed to get visit state events for visit', [
                'visit_id' => $visitId,
                'error' => $e->getMessage(),
            ]);
            return new Collection();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function verifyEventChainIntegrity(int $visitId): array
    {
        try {
            $events = $this->getEventsForVisit($visitId);
            $verified = true;
            $lastHash = null;
            $failedEvents = [];

            foreach ($events as $event) {
                if (!$event->verifyIntegrityHash($lastHash)) {
                    $verified = false;
                    $failedEvents[] = [
                        'event_id' => $event->id,
                        'event_uuid' => $event->event_uuid,
                        'expected_hash' => $event->generateIntegrityHash($lastHash),
                        'actual_hash' => $event->integrity_hash,
                    ];
                }
                $lastHash = $event->integrity_hash;
            }

            return [
                'verified' => $verified,
                'total_events' => $events->count(),
                'failed_events' => $failedEvents,
                'failed_count' => count($failedEvents),
            ];
        } catch (\Exception $e) {
            Log::error('Failed to verify event chain integrity', [
                'visit_id' => $visitId,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'verified' => false,
                'total_events' => 0,
                'failed_events' => [],
                'failed_count' => 0,
                'error' => 'Failed to verify chain integrity',
            ];
        }
    }
}