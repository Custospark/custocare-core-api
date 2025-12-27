<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\VisitEvent\StoreVisitEventRequest;
use App\Http\Requests\VisitEvent\UpdateVisitEventRequest;
use App\Http\Resources\VisitEventResource;
use App\Services\Contracts\VisitEventServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class VisitEventController extends Controller
{
    /**
     * @var VisitEventServiceInterface
     */
    protected $visitEventService;

    /**
     * VisitEventController constructor.
     *
     * @param VisitEventServiceInterface $visitEventService
     */
    public function __construct(VisitEventServiceInterface $visitEventService)
    {
        $this->visitEventService = $visitEventService;
        
        // Apply policy to all methods except index and show (handled individually)
        $this->authorizeResource(\App\Models\VisitEvent::class, 'visit_event', [
            'except' => ['index', 'show'],
        ]);
    }

    /**
     * Display a listing of visit events.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            // Check authorization
            $this->authorize('viewAny', \App\Models\VisitEvent::class);

            // Get filters from request
            $filters = $request->only([
                'facility_id',
                'visit_id',
                'event_type',
                'actor_type',
                'actor_id',
                'from_date',
                'to_date',
            ]);

            // Validate date filters
            if (isset($filters['from_date']) && !strtotime($filters['from_date'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid from_date format',
                    'status_code' => 422,
                ], 422);
            }

            if (isset($filters['to_date']) && !strtotime($filters['to_date'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid to_date format',
                    'status_code' => 422,
                ], 422);
            }

            // Get paginated events
            $perPage = $request->get('per_page', 15);
            $result = $this->visitEventService->getPaginatedEvents($filters, $perPage);

            if (!$result['success']) {
                return response()->json($result, $result['status_code'] ?? 500);
            }

            // Transform events using resource
            $events = $result['data'];
            $transformedEvents = VisitEventResource::collection($events);

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => $transformedEvents,
                'meta' => [
                    'pagination' => $result['pagination'],
                    'filters_applied' => $filters,
                ],
            ]);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            Log::warning('Authorization failed for visit events index', [
                'user_id' => $request->user()->id ?? 'guest',
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to view visit events',
                'status_code' => 403,
            ], 403);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve visit events', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve visit events',
                'error' => config('app.debug') ? $e->getMessage() : null,
                'status_code' => 500,
            ], 500);
        }
    }

    /**
     * Store a newly created visit event.
     *
     * @param StoreVisitEventRequest $request
     * @return JsonResponse
     */
    public function store(StoreVisitEventRequest $request): JsonResponse
    {
        try {
            // Authorization is handled by the form request
            
            $validatedData = $request->validated();
            
            // Record the event
            $result = $this->visitEventService->recordEvent($validatedData);

            if (!$result['success']) {
                return response()->json($result, $result['status_code'] ?? 500);
            }

            // Load the created event with relationships
            $event = \App\Models\VisitEvent::with(['visit', 'precedingEvent'])
                ->find($result['data']['event_id']);

            return response()->json([
                'success' => true,
                'message' => 'Visit event recorded successfully',
                'data' => new VisitEventResource($event),
                'event_metadata' => $result['data'],
            ], Response::HTTP_CREATED);
        } catch (\Exception $e) {
            Log::error('Failed to store visit event', [
                'request_data' => $request->all(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to record visit event',
                'error' => config('app.debug') ? $e->getMessage() : null,
                'status_code' => 500,
            ], 500);
        }
    }

    /**
     * Display the specified visit event.
     *
     * @param Request $request
     * @param string $uuid
     * @return JsonResponse
     */
    public function show(Request $request, string $uuid): JsonResponse
    {
        try {
            // Get the event
            $result = $this->visitEventService->getEventByUuid($uuid);

            if (!$result['success']) {
                return response()->json($result, $result['status_code'] ?? 404);
            }

            $visitEvent = $result['data'];

            // Check authorization
            $this->authorize('view', $visitEvent);

            return response()->json([
                'success' => true,
                'message' => 'Visit event retrieved successfully',
                'data' => new VisitEventResource($visitEvent->load(['visit', 'precedingEvent'])),
            ]);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            Log::warning('Authorization failed for visit event show', [
                'user_id' => $request->user()->id ?? 'guest',
                'event_uuid' => $uuid,
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to view this visit event',
                'status_code' => 403,
            ], 403);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve visit event', [
                'event_uuid' => $uuid,
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve visit event',
                'error' => config('app.debug') ? $e->getMessage() : null,
                'status_code' => 500,
            ], 500);
        }
    }

    /**
     * Update the specified visit event (limited updates).
     *
     * @param UpdateVisitEventRequest $request
     * @param string $uuid
     * @return JsonResponse
     */
    public function update(UpdateVisitEventRequest $request, string $uuid): JsonResponse
    {
        try {
            // Authorization is handled by the form request
            
            $validatedData = $request->validated();
            
            // Get the event
            $result = $this->visitEventService->getEventByUuid($uuid);

            if (!$result['success']) {
                return response()->json($result, $result['status_code'] ?? 404);
            }

            $visitEvent = $result['data'];

            // Update only allowed fields
            if (isset($validatedData['metadata'])) {
                $visitEvent->metadata = $validatedData['metadata'];
            }

            if (isset($validatedData['client_ip'])) {
                $visitEvent->client_ip = $validatedData['client_ip'];
            }

            if (isset($validatedData['client_user_agent'])) {
                $visitEvent->client_user_agent = $validatedData['client_user_agent'];
            }

            $visitEvent->save();

            return response()->json([
                'success' => true,
                'message' => 'Visit event metadata updated successfully',
                'data' => new VisitEventResource($visitEvent->fresh(['visit', 'precedingEvent'])),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update visit event', [
                'event_uuid' => $uuid,
                'request_data' => $request->all(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update visit event',
                'error' => config('app.debug') ? $e->getMessage() : null,
                'status_code' => 500,
            ], 500);
        }
    }

    /**
     * Remove the specified visit event from storage.
     *
     * @param Request $request
     * @param string $uuid
     * @return JsonResponse
     */
    public function destroy(Request $request, string $uuid): JsonResponse
    {
        try {
            // Get the event
            $result = $this->visitEventService->getEventByUuid($uuid);

            if (!$result['success']) {
                return response()->json($result, $result['status_code'] ?? 404);
            }

            $visitEvent = $result['data'];

            // Check authorization
            $this->authorize('delete', $visitEvent);

            // Visit events are immutable, so we don't actually delete them
            // In production, you might implement an archive or soft delete
            Log::warning('Attempt to delete immutable visit event blocked', [
                'user_id' => $request->user()->id,
                'event_uuid' => $uuid,
                'event_id' => $visitEvent->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Visit events are immutable and cannot be deleted',
                'status_code' => 403,
            ], 403);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            Log::warning('Authorization failed for visit event destroy', [
                'user_id' => $request->user()->id ?? 'guest',
                'event_uuid' => $uuid,
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to delete visit events',
                'status_code' => 403,
            ], 403);
        } catch (\Exception $e) {
            Log::error('Failed to process visit event deletion', [
                'event_uuid' => $uuid,
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to process deletion request',
                'error' => config('app.debug') ? $e->getMessage() : null,
                'status_code' => 500,
            ], 500);
        }
    }

    /**
     * Get clinical timeline for a visit.
     *
     * @param Request $request
     * @param int $visitId
     * @return JsonResponse
     */
    public function clinicalTimeline(Request $request, int $visitId): JsonResponse
    {
        try {
            // Check authorization for clinical events
            $this->authorize('viewClinicalEvents', \App\Models\VisitEvent::class);

            $result = $this->visitEventService->getClinicalTimeline($visitId);

            if (!$result['success']) {
                return response()->json($result, $result['status_code'] ?? 500);
            }

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => $result['data'],
                'meta' => [
                    'visit_id' => $visitId,
                    'event_count' => $result['count'],
                ],
            ]);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            Log::warning('Authorization failed for clinical timeline', [
                'user_id' => $request->user()->id ?? 'guest',
                'visit_id' => $visitId,
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to view clinical events',
                'status_code' => 403,
            ], 403);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve clinical timeline', [
                'visit_id' => $visitId,
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve clinical timeline',
                'error' => config('app.debug') ? $e->getMessage() : null,
                'status_code' => 500,
            ], 500);
        }
    }

    /**
     * Get visit state timeline.
     *
     * @param Request $request
     * @param int $visitId
     * @return JsonResponse
     */
    public function visitStateTimeline(Request $request, int $visitId): JsonResponse
    {
        try {
            // Check authorization
            $this->authorize('viewAny', \App\Models\VisitEvent::class);

            $result = $this->visitEventService->getVisitStateTimeline($visitId);

            if (!$result['success']) {
                return response()->json($result, $result['status_code'] ?? 500);
            }

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => $result['data'],
                'meta' => [
                    'visit_id' => $visitId,
                    'event_count' => $result['count'],
                ],
            ]);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            Log::warning('Authorization failed for visit state timeline', [
                'user_id' => $request->user()->id ?? 'guest',
                'visit_id' => $visitId,
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to view visit state events',
                'status_code' => 403,
            ], 403);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve visit state timeline', [
                'visit_id' => $visitId,
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve visit state timeline',
                'error' => config('app.debug') ? $e->getMessage() : null,
                'status_code' => 500,
            ], 500);
        }
    }

    /**
     * Verify event chain integrity for a visit.
     *
     * @param Request $request
     * @param int $visitId
     * @return JsonResponse
     */
    public function verifyChain(Request $request, int $visitId): JsonResponse
    {
        try {
            // Check authorization
            $this->authorize('verifyChain', \App\Models\VisitEvent::class);

            $result = $this->visitEventService->verifyEventChain($visitId);

            return response()->json($result, $result['success'] ? 200 : 500);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            Log::warning('Authorization failed for verify chain', [
                'user_id' => $request->user()->id ?? 'guest',
                'visit_id' => $visitId,
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to verify event chains',
                'status_code' => 403,
            ], 403);
        } catch (\Exception $e) {
            Log::error('Failed to verify event chain', [
                'visit_id' => $visitId,
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to verify event chain',
                'error' => config('app.debug') ? $e->getMessage() : null,
                'status_code' => 500,
            ], 500);
        }
    }

    /**
     * Recalculate integrity hash for an event.
     *
     * @param Request $request
     * @param int $eventId
     * @return JsonResponse
     */
    public function recalculateHash(Request $request, int $eventId): JsonResponse
    {
        try {
            // Get the event first
            $event = \App\Models\VisitEvent::find($eventId);
            
            if (!$event) {
                return response()->json([
                    'success' => false,
                    'message' => 'Event not found',
                    'status_code' => 404,
                ], 404);
            }

            // Check authorization
            $this->authorize('recalculateHash', $event);

            $result = $this->visitEventService->recalculateIntegrityHash($eventId);

            return response()->json($result, $result['success'] ? 200 : 500);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            Log::warning('Authorization failed for recalculate hash', [
                'user_id' => $request->user()->id ?? 'guest',
                'event_id' => $eventId,
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to recalculate integrity hashes',
                'status_code' => 403,
            ], 403);
        } catch (\Exception $e) {
            Log::error('Failed to recalculate integrity hash', [
                'event_id' => $eventId,
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to recalculate integrity hash',
                'error' => config('app.debug') ? $e->getMessage() : null,
                'status_code' => 500,
            ], 500);
        }
    }

    /**
     * Get facility events report.
     *
     * @param Request $request
     * @param int $facilityId
     * @return JsonResponse
     */
    public function facilityReport(Request $request, int $facilityId): JsonResponse
    {
        try {
            // Check authorization
            $this->authorize('generateReports', \App\Models\VisitEvent::class);

            // Validate date range
            $request->validate([
                'from' => 'required|date|before_or_equal:to',
                'to' => 'required|date|after_or_equal:from',
            ]);

            $from = \Carbon\Carbon::parse($request->input('from'));
            $to = \Carbon\Carbon::parse($request->input('to'));
            
            $eventTypes = $request->input('event_types', []);

            $result = $this->visitEventService->getFacilityEventsReport(
                $facilityId,
                $from,
                $to,
                $eventTypes
            );

            return response()->json($result, $result['success'] ? 200 : 500);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            Log::warning('Authorization failed for facility report', [
                'user_id' => $request->user()->id ?? 'guest',
                'facility_id' => $facilityId,
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to generate facility reports',
                'status_code' => 403,
            ], 403);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
                'status_code' => 422,
            ], 422);
        } catch (\Exception $e) {
            Log::error('Failed to generate facility report', [
                'facility_id' => $facilityId,
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate facility report',
                'error' => config('app.debug') ? $e->getMessage() : null,
                'status_code' => 500,
            ], 500);
        }
    }

    /**
     * Get event statistics for a facility.
     *
     * @param Request $request
     * @param int $facilityId
     * @return JsonResponse
     */
    public function statistics(Request $request, int $facilityId): JsonResponse
    {
        try {
            // Check authorization
            $this->authorize('generateReports', \App\Models\VisitEvent::class);

            // Validate date range
            $request->validate([
                'from' => 'required|date|before_or_equal:to',
                'to' => 'required|date|after_or_equal:from',
            ]);

            $from = \Carbon\Carbon::parse($request->input('from'));
            $to = \Carbon\Carbon::parse($request->input('to'));

            $result = $this->visitEventService->getEventStatistics($facilityId, $from, $to);

            return response()->json($result, $result['success'] ? 200 : 500);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            Log::warning('Authorization failed for statistics', [
                'user_id' => $request->user()->id ?? 'guest',
                'facility_id' => $facilityId,
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to view event statistics',
                'status_code' => 403,
            ], 403);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
                'status_code' => 422,
            ], 422);
        } catch (\Exception $e) {
            Log::error('Failed to get event statistics', [
                'facility_id' => $facilityId,
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve event statistics',
                'error' => config('app.debug') ? $e->getMessage() : null,
                'status_code' => 500,
            ], 500);
        }
    }
}