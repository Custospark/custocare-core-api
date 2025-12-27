<?php

namespace App\Services\VisitEvent;

use App\Services\Contracts\VisitEventServiceInterface;
use App\Repositories\Contracts\VisitEventRepositoryInterface;
use App\Models\VisitEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class VisitEventService implements VisitEventServiceInterface
{
    /**
     * @var VisitEventRepositoryInterface
     */
    protected $visitEventRepository;

    /**
     * VisitEventService constructor.
     *
     * @param VisitEventRepositoryInterface $visitEventRepository
     */
    public function __construct(VisitEventRepositoryInterface $visitEventRepository)
    {
        $this->visitEventRepository = $visitEventRepository;
    }

    /**
     * {@inheritdoc}
     */
    public function recordEvent(array $eventData): array
    {
        try {
            // Validate required fields
            $validator = Validator::make($eventData, [
                'facility_id' => 'required|integer|min:1',
                'visit_id' => 'required|integer|min:1',
                'event_type' => 'required|string|in:' . implode(',', [
                    'visit_created',
                    'patient_arrived',
                    'patient_registered',
                    'triage_started',
                    'triage_completed',
                    'vitals_recorded',
                    'routed_to_department',
                    'provider_assigned',
                    'consultation_started',
                    'consultation_completed',
                    'diagnostic_ordered',
                    'diagnostic_completed',
                    'medication_ordered',
                    'medication_administered',
                    'procedure_started',
                    'procedure_completed',
                    'condition_changed',
                    'admission_ordered',
                    'transfer_initiated',
                    'discharge_ordered',
                    'discharge_completed',
                    'visit_cancelled',
                    'patient_left_ama',
                    'patient_lwbs',
                    'clinical_note_added',
                    'billing_updated',
                    'insurance_verified',
                    'consent_obtained',
                    'alert_triggered',
                    'escalation_required'
                ]),
                'event_payload' => 'required|array',
                'actor_type' => 'required|string|in:staff,patient,system,device,external_system',
                'event_occurred_at' => 'required|date',
            ]);

            if ($validator->fails()) {
                throw new ValidationException($validator);
            }

            DB::beginTransaction();

            // Get last event for integrity chain
            $lastEvent = $this->visitEventRepository->getLastEventForVisit($eventData['visit_id']);
            $precedingHash = $lastEvent ? $lastEvent->integrity_hash : null;

            // Prepare event data
            $eventUuid = Str::uuid()->toString();
            $now = Carbon::now();
            $eventOccurredAt = Carbon::parse($eventData['event_occurred_at']);
            
            $eventData['event_uuid'] = $eventUuid;
            $eventData['event_recorded_at'] = $now;
            $eventData['created_at'] = $now;
            $eventData['preceding_event_id'] = $lastEvent ? $lastEvent->id : null;
            $eventData['processing_latency_ms'] = $now->diffInMilliseconds($eventOccurredAt);
            
            // Create temporary event object for hash generation
            $tempEvent = new VisitEvent($eventData);
            $eventData['integrity_hash'] = $tempEvent->generateIntegrityHash($precedingHash);

            // Create the event
            $visitEvent = $this->visitEventRepository->create($eventData);

            DB::commit();

            return [
                'success' => true,
                'message' => 'Event recorded successfully',
                'data' => [
                    'event_id' => $visitEvent->id,
                    'event_uuid' => $visitEvent->event_uuid,
                    'integrity_hash' => $visitEvent->integrity_hash,
                ],
            ];
        } catch (ValidationException $e) {
            DB::rollBack();
            Log::warning('Validation failed while recording event', [
                'errors' => $e->errors(),
                'event_data' => $eventData,
            ]);
            
            return [
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
                'status_code' => 422,
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to record visit event', [
                'event_data' => $eventData,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to record event. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null,
                'status_code' => 500,
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getEventByUuid(string $eventUuid): array
    {
        try {
            $event = $this->visitEventRepository->findByUuid($eventUuid);

            if (!$event) {
                return [
                    'success' => false,
                    'message' => 'Event not found',
                    'status_code' => 404,
                ];
            }

            return [
                'success' => true,
                'message' => 'Event retrieved successfully',
                'data' => $event->load(['visit', 'precedingEvent']),
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get event by UUID', [
                'event_uuid' => $eventUuid,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to retrieve event',
                'error' => config('app.debug') ? $e->getMessage() : null,
                'status_code' => 500,
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getVisitEvents(int $visitId): array
    {
        try {
            $events = $this->visitEventRepository->getEventsForVisit($visitId, ['visit', 'precedingEvent']);

            return [
                'success' => true,
                'message' => 'Events retrieved successfully',
                'data' => $events,
                'count' => $events->count(),
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get visit events', [
                'visit_id' => $visitId,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to retrieve visit events',
                'error' => config('app.debug') ? $e->getMessage() : null,
                'status_code' => 500,
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getPaginatedEvents(array $filters = [], int $perPage = 15): array
    {
        try {
            $events = $this->visitEventRepository->paginate($filters, $perPage, ['visit', 'precedingEvent']);

            return [
                'success' => true,
                'message' => 'Events retrieved successfully',
                'data' => $events,
                'pagination' => [
                    'total' => $events->total(),
                    'per_page' => $events->perPage(),
                    'current_page' => $events->currentPage(),
                    'last_page' => $events->lastPage(),
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get paginated events', [
                'filters' => $filters,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to retrieve events',
                'error' => config('app.debug') ? $e->getMessage() : null,
                'status_code' => 500,
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getClinicalTimeline(int $visitId): array
    {
        try {
            $events = $this->visitEventRepository->getClinicalEventsForVisit($visitId);

            $timeline = $events->map(function ($event) {
                return [
                    'id' => $event->id,
                    'event_uuid' => $event->event_uuid,
                    'event_type' => $event->event_type,
                    'event_occurred_at' => $event->event_occurred_at,
                    'actor_type' => $event->actor_type,
                    'actor_id' => $event->actor_id,
                    'payload' => $event->event_payload,
                ];
            });

            return [
                'success' => true,
                'message' => 'Clinical timeline retrieved successfully',
                'data' => $timeline,
                'count' => $timeline->count(),
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get clinical timeline', [
                'visit_id' => $visitId,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to retrieve clinical timeline',
                'error' => config('app.debug') ? $e->getMessage() : null,
                'status_code' => 500,
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getVisitStateTimeline(int $visitId): array
    {
        try {
            $events = $this->visitEventRepository->getVisitStateEventsForVisit($visitId);

            $timeline = $events->map(function ($event) {
                return [
                    'id' => $event->id,
                    'event_uuid' => $event->event_uuid,
                    'event_type' => $event->event_type,
                    'event_occurred_at' => $event->event_occurred_at,
                    'actor_type' => $event->actor_type,
                    'actor_id' => $event->actor_id,
                    'payload' => $event->event_payload,
                ];
            });

            return [
                'success' => true,
                'message' => 'Visit state timeline retrieved successfully',
                'data' => $timeline,
                'count' => $timeline->count(),
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get visit state timeline', [
                'visit_id' => $visitId,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to retrieve visit state timeline',
                'error' => config('app.debug') ? $e->getMessage() : null,
                'status_code' => 500,
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getFacilityEventsReport(
        int $facilityId,
        Carbon $from,
        Carbon $to,
        array $eventTypes = []
    ): array {
        try {
            $events = $this->visitEventRepository->getEventsForFacilityInRange(
                $facilityId,
                $from,
                $to,
                $eventTypes
            );

            // Group by event type for summary
            $summary = $events->groupBy('event_type')->map(function ($group) {
                return [
                    'count' => $group->count(),
                    'latest' => $group->sortByDesc('event_occurred_at')->first()->event_occurred_at ?? null,
                ];
            });

            return [
                'success' => true,
                'message' => 'Facility events report generated successfully',
                'data' => [
                    'events' => $events,
                    'summary' => $summary,
                    'total_events' => $events->count(),
                    'time_range' => [
                        'from' => $from,
                        'to' => $to,
                    ],
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get facility events report', [
                'facility_id' => $facilityId,
                'from' => $from,
                'to' => $to,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to generate facility events report',
                'error' => config('app.debug') ? $e->getMessage() : null,
                'status_code' => 500,
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function verifyEventChain(int $visitId): array
    {
        try {
            $result = $this->visitEventRepository->verifyEventChainIntegrity($visitId);

            return [
                'success' => true,
                'message' => $result['verified'] 
                    ? 'Event chain integrity verified' 
                    : 'Event chain integrity check failed',
                'data' => $result,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to verify event chain', [
                'visit_id' => $visitId,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to verify event chain integrity',
                'error' => config('app.debug') ? $e->getMessage() : null,
                'status_code' => 500,
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getEventStatistics(int $facilityId, Carbon $from, Carbon $to): array
    {
        try {
            $allEvents = $this->visitEventRepository->getEventsForFacilityInRange($facilityId, $from, $to);

            // Calculate statistics
            $totalEvents = $allEvents->count();
            $eventsByType = $allEvents->groupBy('event_type')->map->count();
            $eventsByActor = $allEvents->groupBy('actor_type')->map->count();
            
            // Calculate average processing latency
            $eventsWithLatency = $allEvents->whereNotNull('processing_latency_ms');
            $avgLatency = $eventsWithLatency->isNotEmpty() 
                ? $eventsWithLatency->avg('processing_latency_ms') 
                : 0;

            // Get busiest hour
            $eventsByHour = $allEvents->groupBy(function ($event) {
                return $event->event_occurred_at->hour;
            })->map->count();

            $busiestHour = $eventsByHour->isNotEmpty() 
                ? $eventsByHour->sortDesc()->keys()->first() 
                : null;

            return [
                'success' => true,
                'message' => 'Event statistics retrieved successfully',
                'data' => [
                    'time_range' => [
                        'from' => $from,
                        'to' => $to,
                    ],
                    'total_events' => $totalEvents,
                    'events_by_type' => $eventsByType,
                    'events_by_actor' => $eventsByActor,
                    'performance' => [
                        'average_processing_latency_ms' => round($avgLatency, 2),
                        'events_with_latency' => $eventsWithLatency->count(),
                        'busiest_hour' => $busiestHour,
                        'events_in_busiest_hour' => $busiestHour ? $eventsByHour[$busiestHour] : 0,
                    ],
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get event statistics', [
                'facility_id' => $facilityId,
                'from' => $from,
                'to' => $to,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to retrieve event statistics',
                'error' => config('app.debug') ? $e->getMessage() : null,
                'status_code' => 500,
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function recalculateIntegrityHash(int $eventId): array
    {
        try {
            DB::beginTransaction();

            $event = $this->visitEventRepository->findById($eventId);
            
            if (!$event) {
                return [
                    'success' => false,
                    'message' => 'Event not found',
                    'status_code' => 404,
                ];
            }

            // Get preceding event hash
            $precedingEvent = $event->precedingEvent;
            $precedingHash = $precedingEvent ? $precedingEvent->integrity_hash : null;

            // Recalculate hash
            $newHash = $event->generateIntegrityHash($precedingHash);

            // Update if different
            if ($newHash !== $event->integrity_hash) {
                $event->integrity_hash = $newHash;
                $event->save();
                
                $message = 'Integrity hash recalculated and updated';
                $wasChanged = true;
            } else {
                $message = 'Integrity hash is already correct';
                $wasChanged = false;
            }

            DB::commit();

            return [
                'success' => true,
                'message' => $message,
                'data' => [
                    'event_id' => $event->id,
                    'old_hash' => $event->integrity_hash,
                    'new_hash' => $newHash,
                    'hash_changed' => $wasChanged,
                    'integrity_verified' => $event->verifyIntegrityHash($precedingHash),
                ],
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to recalculate integrity hash', [
                'event_id' => $eventId,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to recalculate integrity hash',
                'error' => config('app.debug') ? $e->getMessage() : null,
                'status_code' => 500,
            ];
        }
    }
}