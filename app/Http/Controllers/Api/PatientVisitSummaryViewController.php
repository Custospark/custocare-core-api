<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PatientVisitSummaryView\StorePatientVisitSummaryViewRequest;
use App\Http\Requests\PatientVisitSummaryView\UpdatePatientVisitSummaryViewRequest;
use App\Http\Resources\PatientVisitSummaryViewCollection;
use App\Http\Resources\PatientVisitSummaryViewResource;
use App\Models\PatientVisitSummaryView;
use App\Services\Contracts\PatientVisitSummaryViewServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PatientVisitSummaryViewController extends Controller
{
    /**
     * PatientVisitSummaryView service instance.
     *
     * @var PatientVisitSummaryViewServiceInterface
     */
    protected PatientVisitSummaryViewServiceInterface $service;

    /**
     * Constructor.
     *
     * @param PatientVisitSummaryViewServiceInterface $service
     */
    public function __construct(PatientVisitSummaryViewServiceInterface $service)
    {
        $this->service = $service;
    }

    /**
     * Display a listing of the patient visit summary views.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = $request->only([
                'patient_id',
                'has_upcoming_appointments',
                'has_outstanding_bills',
                'last_updated_since',
                'search',
                'sort_by',
                'sort_order',
            ]);

            $perPage = $request->get('per_page', 20);
            $perPage = min($perPage, 100); // Limit per page to 100

            $result = $this->service->getAllSummaries($filters, $perPage);

            if (!$result['success']) {
                return response()->json($result, $result['status']);
            }

            $collection = new PatientVisitSummaryViewCollection($result['data']);

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => $collection,
            ], $result['status']);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve patient visit summaries', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while retrieving patient visit summaries.',
                'data' => null,
            ], 500);
        }
    }

    /**
     * Store a newly created patient visit summary view in storage.
     *
     * @param StorePatientVisitSummaryViewRequest $request
     * @return JsonResponse
     */
    public function store(StorePatientVisitSummaryViewRequest $request): JsonResponse
    {
        try {
            $validatedData = $request->validated();
            $result = $this->service->createSummaryView($validatedData);

            if (!$result['success']) {
                return response()->json($result, $result['status']);
            }

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => new PatientVisitSummaryViewResource($result['data']),
            ], $result['status']);
        } catch (\Exception $e) {
            Log::error('Failed to create patient visit summary', [
                'data' => $request->all(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while creating the patient visit summary.',
                'data' => null,
            ], 500);
        }
    }

    /**
     * Display the specified patient visit summary view.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        try {
            $result = $this->service->getSummaryViewById($id);

            if (!$result['success']) {
                return response()->json($result, $result['status']);
            }

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => new PatientVisitSummaryViewResource($result['data']),
            ], $result['status']);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve patient visit summary', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while retrieving the patient visit summary.',
                'data' => null,
            ], 500);
        }
    }

    /**
     * Display the specified patient visit summary view by patient ID.
     *
     * @param int $patientId
     * @return JsonResponse
     */
    public function showByPatientId(int $patientId): JsonResponse
    {
        try {
            $result = $this->service->getSummaryByPatientId($patientId);

            if (!$result['success']) {
                return response()->json($result, $result['status']);
            }

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => new PatientVisitSummaryViewResource($result['data']),
            ], $result['status']);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve patient visit summary by patient ID', [
                'patient_id' => $patientId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while retrieving the patient visit summary.',
                'data' => null,
            ], 500);
        }
    }

    /**
     * Update the specified patient visit summary view in storage.
     *
     * @param UpdatePatientVisitSummaryViewRequest $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(UpdatePatientVisitSummaryViewRequest $request, int $id): JsonResponse
    {
        try {
            $validatedData = $request->validated();
            $result = $this->service->updateSummaryView($id, $validatedData);

            if (!$result['success']) {
                return response()->json($result, $result['status']);
            }

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => new PatientVisitSummaryViewResource($result['data']),
            ], $result['status']);
        } catch (\Exception $e) {
            Log::error('Failed to update patient visit summary', [
                'id' => $id,
                'data' => $request->all(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while updating the patient visit summary.',
                'data' => null,
            ], 500);
        }
    }

    /**
     * Refresh the patient visit summary view.
     *
     * @param int $patientId
     * @return JsonResponse
     */
    public function refresh(int $patientId): JsonResponse
    {
        try {
            $this->authorize('refresh', PatientVisitSummaryView::class);

            $result = $this->service->refreshSummaryView($patientId);

            return response()->json([
                'success' => $result['success'],
                'message' => $result['message'],
                'data' => $result['data'] ? new PatientVisitSummaryViewResource($result['data']) : null,
            ], $result['status']);
        } catch (\Exception $e) {
            Log::error('Failed to refresh patient visit summary', [
                'patient_id' => $patientId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while refreshing the patient visit summary.',
                'data' => null,
            ], 500);
        }
    }

    /**
     * Batch refresh multiple patient visit summary views.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function batchRefresh(Request $request): JsonResponse
    {
        try {
            $this->authorize('batchRefresh', PatientVisitSummaryView::class);

            $request->validate([
                'patient_ids' => 'required|array',
                'patient_ids.*' => 'integer|exists:patients,id',
            ]);

            $result = $this->service->batchRefreshSummaryViews($request->patient_ids);

            return response()->json([
                'success' => $result['success'],
                'message' => $result['message'],
                'data' => $result['data'],
            ], $result['status']);
        } catch (\Exception $e) {
            Log::error('Failed to batch refresh patient visit summaries', [
                'patient_ids' => $request->patient_ids ?? [],
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while batch refreshing patient visit summaries.',
                'data' => null,
            ], 500);
        }
    }

    /**
     * Remove the specified patient visit summary view from storage.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $result = $this->service->deleteSummaryView($id);

            return response()->json([
                'success' => $result['success'],
                'message' => $result['message'],
                'data' => $result['data'],
            ], $result['status']);
        } catch (\Exception $e) {
            Log::error('Failed to delete patient visit summary', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while deleting the patient visit summary.',
                'data' => null,
            ], 500);
        }
    }

    /**
     * Get upcoming appointments within a date range.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function upcomingAppointments(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'start_date' => 'required|date',
                'end_date' => 'required|date|after_or_equal:start_date',
            ]);

            $result = $this->service->getUpcomingAppointments(
                $request->start_date,
                $request->end_date
            );

            return response()->json([
                'success' => $result['success'],
                'message' => $result['message'],
                'data' => $result['data'],
            ], $result['status']);
        } catch (\Exception $e) {
            Log::error('Failed to get upcoming appointments', [
                'start_date' => $request->start_date ?? null,
                'end_date' => $request->end_date ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while retrieving upcoming appointments.',
                'data' => null,
            ], 500);
        }
    }

    /**
     * Get health metrics trends for a patient.
     *
     * @param int $patientId
     * @return JsonResponse
     */
    public function healthMetricsTrends(int $patientId): JsonResponse
    {
        try {
            $result = $this->service->getHealthMetricsTrends($patientId);

            return response()->json([
                'success' => $result['success'],
                'message' => $result['message'],
                'data' => $result['data'],
            ], $result['status']);
        } catch (\Exception $e) {
            Log::error('Failed to get health metrics trends', [
                'patient_id' => $patientId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while retrieving health metrics trends.',
                'data' => null,
            ], 500);
        }
    }

    /**
     * Get care coordination insights.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function careCoordinationInsights(Request $request): JsonResponse
    {
        try {
            $filters = $request->only([
                'patient_id',
                'has_upcoming_appointments',
                'has_outstanding_bills',
                'last_updated_since',
            ]);

            $result = $this->service->getCareCoordinationInsights($filters);

            return response()->json([
                'success' => $result['success'],
                'message' => $result['message'],
                'data' => $result['data'],
            ], $result['status']);
        } catch (\Exception $e) {
            Log::error('Failed to get care coordination insights', [
                'filters' => $request->all(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while retrieving care coordination insights.',
                'data' => null,
            ], 500);
        }
    }
}