<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Consultation\StoreConsultationRequest;
use App\Http\Requests\Consultation\UpdateConsultationRequest;
use App\Http\Resources\ConsultationResource;
use App\Http\Resources\ConsultationCollection;
use App\Services\Contracts\ConsultationServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ConsultationController extends Controller
{
    /**
     * @var ConsultationServiceInterface
     */
    protected ConsultationServiceInterface $consultationService;

    /**
     * Constructor.
     *
     * @param ConsultationServiceInterface $consultationService
     */
    public function __construct(ConsultationServiceInterface $consultationService)
    {
        $this->consultationService = $consultationService;
    }

    /**
     * Display a listing of consultations.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = $request->only([
                'facility_id',
                'patient_id',
                'visit_id',
                'request_status',
                'priority',
                'consultation_type',
                'specialty_required',
                'consultant_staff_id',
                'requesting_staff_id',
                'date_from',
                'date_to',
                'scheduled_from',
                'scheduled_to',
                'order_by',
                'order_direction',
            ]);

            $perPage = $request->get('per_page', 15);

            $result = $this->consultationService->getAllConsultations($filters, $perPage);

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'errors' => ['system' => [$result['error'] ?? 'Unknown error']],
                    'data' => null,
                ], 500);
            }

            $consultations = $result['data'];

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => ConsultationResource::collection($consultations),
                'meta' => [
                    'total' => $consultations->total(),
                    'per_page' => $consultations->perPage(),
                    'current_page' => $consultations->currentPage(),
                    'last_page' => $consultations->lastPage(),
                    'from' => $consultations->firstItem(),
                    'to' => $consultations->lastItem(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve consultations list', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve consultations',
                'errors' => ['system' => ['An unexpected error occurred']],
                'data' => null,
            ], 500);
        }
    }

    /**
     * Store a newly created consultation request.
     *
     * @param StoreConsultationRequest $request
     * @return JsonResponse
     */
    public function store(StoreConsultationRequest $request): JsonResponse
    {
        try {
            $validatedData = $request->validated();
            $requestedByStaffId = $validatedData['requesting_staff_id'] ?? $request->user()?->id ?? 1;

            $result = $this->consultationService->createConsultation($validatedData, $requestedByStaffId);

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'errors' => is_array($result['error']) ? $result['error'] : ['consultation' => [$result['error'] ?? 'Creation failed']],
                    'data' => null,
                ], 422);
            }

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => new ConsultationResource($result['data']),
                'errors' => null,
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'data' => null,
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Failed to create consultation', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create consultation',
                'data' => null,
                'errors' => ['system' => ['An unexpected error occurred']],
            ], 500);
        }
    }

    /**
     * Display the specified consultation.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        try {
            $result = $this->consultationService->getConsultationById($id);

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'errors' => ['consultation' => [$result['error'] ?? 'Not found']],
                    'data' => null,
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => new ConsultationResource($result['data']),
                'errors' => null,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve consultation', [
                'error' => $e->getMessage(),
                'id' => $id,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve consultation',
                'errors' => ['system' => ['An unexpected error occurred']],
                'data' => null,
            ], 500);
        }
    }

    /**
     * Update the specified consultation.
     *
     * @param UpdateConsultationRequest $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(UpdateConsultationRequest $request, int $id): JsonResponse
    {
        try {
            $validatedData = array_filter($request->validated(), fn($value) => !is_null($value));
            $updatedByStaffId = $request->user()?->id ?? 1;

            $result = $this->consultationService->updateConsultation($id, $validatedData, $updatedByStaffId);

            if (!$result['success']) {
                $statusCode = isset($result['error']) && $result['error'] === 'Consultation not found' ? 404 : 422;
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'errors' => is_array($result['error']) ? $result['error'] : ['consultation' => [$result['error'] ?? 'Update failed']],
                    'data' => null,
                ], $statusCode);
            }

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => new ConsultationResource($result['data']),
                'errors' => null,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'data' => null,
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Failed to update consultation', [
                'error' => $e->getMessage(),
                'id' => $id,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update consultation',
                'errors' => ['system' => ['An unexpected error occurred']],
                'data' => null,
            ], 500);
        }
    }

    /**
     * Remove the specified consultation (soft delete).
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $result = $this->consultationService->deleteConsultation($id);

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'errors' => ['consultation' => [$result['error'] ?? 'Delete failed']],
                    'data' => null,
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => null,
                'errors' => null,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to delete consultation', [
                'error' => $e->getMessage(),
                'id' => $id,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete consultation',
                'errors' => ['system' => ['An unexpected error occurred']],
                'data' => null,
            ], 500);
        }
    }

    /**
     * Get consultations for a specific patient.
     *
     * @param Request $request
     * @param int $patientId
     * @return JsonResponse
     */
    public function patientConsultations(Request $request, int $patientId): JsonResponse
    {
        try {
            $filters = $request->only(['request_status', 'priority', 'date_from', 'date_to']);
            $perPage = $request->get('per_page', 15);

            $result = $this->consultationService->getPatientConsultations($patientId, $filters, $perPage);

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'errors' => ['system' => [$result['error'] ?? 'Unknown error']],
                    'data' => null,
                ], 500);
            }

            $consultations = $result['data'];

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => ConsultationResource::collection($consultations),
                'meta' => [
                    'total' => $consultations->total(),
                    'per_page' => $consultations->perPage(),
                    'current_page' => $consultations->currentPage(),
                    'last_page' => $consultations->lastPage(),
                    'patient_id' => $patientId,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get patient consultations', [
                'error' => $e->getMessage(),
                'patient_id' => $patientId,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve patient consultations',
                'errors' => ['system' => ['An unexpected error occurred']],
                'data' => null,
            ], 500);
        }
    }

    /**
     * Get consultations for a specific visit.
     *
     * @param int $visitId
     * @return JsonResponse
     */
    public function visitConsultations(int $visitId): JsonResponse
    {
        try {
            $result = $this->consultationService->getVisitConsultations($visitId);

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'errors' => ['system' => [$result['error'] ?? 'Unknown error']],
                    'data' => null,
                ], 500);
            }

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => ConsultationResource::collection($result['data']),
                'meta' => [
                    'count' => $result['data']->count(),
                    'visit_id' => $visitId,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get visit consultations', [
                'error' => $e->getMessage(),
                'visit_id' => $visitId,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve visit consultations',
                'errors' => ['system' => ['An unexpected error occurred']],
                'data' => null,
            ], 500);
        }
    }

    /**
     * Accept a consultation request.
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function accept(Request $request, int $id): JsonResponse
    {
        try {
            $consultantStaffId = $request->get('consultant_staff_id', $request->user()?->id ?? 1);

            $result = $this->consultationService->acceptConsultation($id, $consultantStaffId);

            if (!$result['success']) {
                $statusCode = isset($result['error']) && $result['error'] === 'Consultation not found' ? 404 : 422;
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'errors' => ['consultation' => [$result['error'] ?? 'Acceptance failed']],
                    'data' => null,
                ], $statusCode);
            }

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => new ConsultationResource($result['data']),
                'errors' => null,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to accept consultation', [
                'error' => $e->getMessage(),
                'id' => $id,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to accept consultation',
                'errors' => ['system' => ['An unexpected error occurred']],
                'data' => null,
            ], 500);
        }
    }

    /**
     * Decline a consultation request.
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function decline(Request $request, int $id): JsonResponse
    {
        try {
            $reason = $request->get('reason');
            $result = $this->consultationService->declineConsultation($id, $reason);

            if (!$result['success']) {
                $statusCode = isset($result['error']) && $result['error'] === 'Consultation not found' ? 404 : 422;
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'errors' => ['consultation' => [$result['error'] ?? 'Decline failed']],
                    'data' => null,
                ], $statusCode);
            }

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => new ConsultationResource($result['data']),
                'errors' => null,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to decline consultation', [
                'error' => $e->getMessage(),
                'id' => $id,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to decline consultation',
                'errors' => ['system' => ['An unexpected error occurred']],
                'data' => null,
            ], 500);
        }
    }

    /**
     * Complete a consultation.
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function complete(Request $request, int $id): JsonResponse
    {
        try {
            $findings = $request->get('findings');
            $recommendations = $request->get('recommendations');

            $result = $this->consultationService->completeConsultation($id, $findings, $recommendations);

            if (!$result['success']) {
                $statusCode = isset($result['error']) && $result['error'] === 'Consultation not found' ? 404 : 422;
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'errors' => ['consultation' => [$result['error'] ?? 'Completion failed']],
                    'data' => null,
                ], $statusCode);
            }

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => new ConsultationResource($result['data']),
                'errors' => null,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to complete consultation', [
                'error' => $e->getMessage(),
                'id' => $id,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to complete consultation',
                'errors' => ['system' => ['An unexpected error occurred']],
                'data' => null,
            ], 500);
        }
    }

    /**
     * Cancel a consultation request.
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function cancel(Request $request, int $id): JsonResponse
    {
        try {
            $reason = $request->get('reason');
            $result = $this->consultationService->cancelConsultation($id, $reason);

            if (!$result['success']) {
                $statusCode = isset($result['error']) && $result['error'] === 'Consultation not found' ? 404 : 422;
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'errors' => ['consultation' => [$result['error'] ?? 'Cancellation failed']],
                    'data' => null,
                ], $statusCode);
            }

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => new ConsultationResource($result['data']),
                'errors' => null,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to cancel consultation', [
                'error' => $e->getMessage(),
                'id' => $id,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel consultation',
                'errors' => ['system' => ['An unexpected error occurred']],
                'data' => null,
            ], 500);
        }
    }

    /**
     * Schedule a consultation.
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function schedule(Request $request, int $id): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'scheduled_for' => 'required|date',
                'location' => 'nullable|string|max:200',
                'duration_minutes' => 'nullable|integer|min:5|max:480',
            ]);

            $result = $this->consultationService->scheduleConsultation(
                $id,
                $validatedData['scheduled_for'],
                $validatedData['location'] ?? null,
                $validatedData['duration_minutes'] ?? null
            );

            if (!$result['success']) {
                $statusCode = isset($result['error']) && $result['error'] === 'Consultation not found' ? 404 : 422;
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'errors' => ['consultation' => [$result['error'] ?? 'Scheduling failed']],
                    'data' => null,
                ], $statusCode);
            }

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => new ConsultationResource($result['data']),
                'errors' => null,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'data' => null,
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Failed to schedule consultation', [
                'error' => $e->getMessage(),
                'id' => $id,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to schedule consultation',
                'errors' => ['system' => ['An unexpected error occurred']],
                'data' => null,
            ], 500);
        }
    }

    /**
     * Get pending consultations.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function pending(Request $request): JsonResponse
    {
        try {
            $facilityId = $request->get('facility_id');
            $limit = $request->get('limit', 50);

            $result = $this->consultationService->getPendingConsultations($facilityId, $limit);

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'errors' => ['system' => [$result['error'] ?? 'Unknown error']],
                    'data' => null,
                ], 500);
            }

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => ConsultationResource::collection($result['data']),
                'meta' => [
                    'count' => $result['data']->count(),
                    'facility_id' => $facilityId,
                    'limit' => $limit,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get pending consultations', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve pending consultations',
                'errors' => ['system' => ['An unexpected error occurred']],
                'data' => null,
            ], 500);
        }
    }

    /**
     * Get urgent consultations.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function urgent(Request $request): JsonResponse
    {
        try {
            $facilityId = $request->get('facility_id');
            $limit = $request->get('limit', 50);

            $result = $this->consultationService->getUrgentConsultations($facilityId, $limit);

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'errors' => ['system' => [$result['error'] ?? 'Unknown error']],
                    'data' => null,
                ], 500);
            }

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => ConsultationResource::collection($result['data']),
                'meta' => [
                    'count' => $result['data']->count(),
                    'facility_id' => $facilityId,
                    'limit' => $limit,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get urgent consultations', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve urgent consultations',
                'errors' => ['system' => ['An unexpected error occurred']],
                'data' => null,
            ], 500);
        }
    }

    /**
     * Get overdue consultations.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function overdue(Request $request): JsonResponse
    {
        try {
            $facilityId = $request->get('facility_id');
            $limit = $request->get('limit', 50);

            $result = $this->consultationService->getOverdueConsultations($facilityId, $limit);

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'errors' => ['system' => [$result['error'] ?? 'Unknown error']],
                    'data' => null,
                ], 500);
            }

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => ConsultationResource::collection($result['data']),
                'meta' => [
                    'count' => $result['data']->count(),
                    'facility_id' => $facilityId,
                    'limit' => $limit,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get overdue consultations', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve overdue consultations',
                'errors' => ['system' => ['An unexpected error occurred']],
                'data' => null,
            ], 500);
        }
    }

    /**
     * Get consultation statistics.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function statistics(Request $request): JsonResponse
    {
        try {
            $facilityId = $request->get('facility_id');
            $startDate = $request->get('start_date');
            $endDate = $request->get('end_date');

            if (!$facilityId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Facility ID is required',
                    'errors' => ['facility_id' => ['Facility ID is required']],
                    'data' => null,
                ], 422);
            }

            if ($startDate && $endDate) {
                $result = $this->consultationService->getConsultationStatistics($facilityId, $startDate, $endDate);
            } else {
                $result = $this->consultationService->getConsultationCountByStatus($facilityId);
            }

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'errors' => ['system' => [$result['error'] ?? 'Unknown error']],
                    'data' => null,
                ], 500);
            }

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => $result['data'],
                'meta' => [
                    'facility_id' => $facilityId,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get consultation statistics', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve consultation statistics',
                'errors' => ['system' => ['An unexpected error occurred']],
                'data' => null,
            ], 500);
        }
    }

    /**
     * Get consultations by specialty.
     *
     * @param Request $request
     * @param string $specialty
     * @return JsonResponse
     */
    public function bySpecialty(Request $request, string $specialty): JsonResponse
    {
        try {
            $facilityId = $request->get('facility_id');
            $limit = $request->get('limit', 50);

            $result = $this->consultationService->getConsultationsBySpecialty($specialty, $facilityId, $limit);

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'errors' => ['system' => [$result['error'] ?? 'Unknown error']],
                    'data' => null,
                ], 500);
            }

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => ConsultationResource::collection($result['data']),
                'meta' => [
                    'count' => $result['data']->count(),
                    'specialty' => $specialty,
                    'facility_id' => $facilityId,
                    'limit' => $limit,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get consultations by specialty', [
                'error' => $e->getMessage(),
                'specialty' => $specialty,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve consultations by specialty',
                'errors' => ['system' => ['An unexpected error occurred']],
                'data' => null,
            ], 500);
        }
    }
}