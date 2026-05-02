<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Vital\StoreVitalRequest;
use App\Http\Requests\Vital\UpdateVitalRequest;
use App\Http\Resources\VitalResource;
use App\Http\Resources\VitalCollection;
use App\Services\Contracts\VitalServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class VitalController extends Controller
{
    /**
     * @var VitalServiceInterface
     */
    protected VitalServiceInterface $vitalService;

    /**
     * Constructor.
     *
     * @param VitalServiceInterface $vitalService
     */
    public function __construct(VitalServiceInterface $vitalService)
    {
        $this->vitalService = $vitalService;
    }

    /**
     * Display a listing of vital records.
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
                'staff_id',
                'consciousness_level',
                'abnormal_only',
                'critical_only',
                'date_from',
                'date_to',
                'order_by',
                'order_direction',
            ]);

            $perPage = $request->get('per_page', 15);

            $result = $this->vitalService->getAllVitals($filters, $perPage);

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'errors' => ['system' => [$result['error'] ?? 'Unknown error']],
                    'data' => null,
                ], 500);
            }

            $vitals = $result['data'];

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => VitalResource::collection($vitals),
                'meta' => [
                    'total' => $vitals->total(),
                    'per_page' => $vitals->perPage(),
                    'current_page' => $vitals->currentPage(),
                    'last_page' => $vitals->lastPage(),
                    'from' => $vitals->firstItem(),
                    'to' => $vitals->lastItem(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve vital records list', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve vital records',
                'errors' => ['system' => ['An unexpected error occurred']],
                'data' => null,
            ], 500);
        }
    }

    /**
     * Store a newly created vital record.
     *
     * @param StoreVitalRequest $request
     * @return JsonResponse
     */
    public function store(StoreVitalRequest $request): JsonResponse
    {
        try {
            $validatedData = $request->validated();
            $recordedByStaffId = $validatedData['staff_id'] ?? $request->user()?->id ?? 1;

            $result = $this->vitalService->createVital($validatedData, $recordedByStaffId);

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'errors' => is_array($result['error']) ? $result['error'] : ['vital' => [$result['error'] ?? 'Creation failed']],
                    'data' => null,
                ], 422);
            }

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => new VitalResource($result['data']),
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
            Log::error('Failed to create vital record', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create vital record',
                'data' => null,
                'errors' => ['system' => ['An unexpected error occurred']],
            ], 500);
        }
    }

    /**
     * Display the specified vital record.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        try {
            $result = $this->vitalService->getVitalById($id);

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'errors' => ['vital' => [$result['error'] ?? 'Not found']],
                    'data' => null,
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => new VitalResource($result['data']),
                'errors' => null,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve vital record', [
                'error' => $e->getMessage(),
                'id' => $id,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve vital record',
                'errors' => ['system' => ['An unexpected error occurred']],
                'data' => null,
            ], 500);
        }
    }

    /**
     * Update the specified vital record.
     *
     * @param UpdateVitalRequest $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(UpdateVitalRequest $request, int $id): JsonResponse
    {
        try {
            $validatedData = array_filter($request->validated(), fn($value) => !is_null($value));
            $updatedByStaffId = $request->user()?->id ?? 1;

            $result = $this->vitalService->updateVital($id, $validatedData, $updatedByStaffId);

            if (!$result['success']) {
                $statusCode = isset($result['error']) && $result['error'] === 'Vital record not found' ? 404 : 422;
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'errors' => is_array($result['error']) ? $result['error'] : ['vital' => [$result['error'] ?? 'Update failed']],
                    'data' => null,
                ], $statusCode);
            }

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => new VitalResource($result['data']),
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
            Log::error('Failed to update vital record', [
                'error' => $e->getMessage(),
                'id' => $id,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update vital record',
                'errors' => ['system' => ['An unexpected error occurred']],
                'data' => null,
            ], 500);
        }
    }

    /**
     * Remove the specified vital record.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $result = $this->vitalService->deleteVital($id);

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'errors' => ['vital' => [$result['error'] ?? 'Delete failed']],
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
            Log::error('Failed to delete vital record', [
                'error' => $e->getMessage(),
                'id' => $id,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete vital record',
                'errors' => ['system' => ['An unexpected error occurred']],
                'data' => null,
            ], 500);
        }
    }

    /**
     * Get vital records for a specific patient.
     *
     * @param Request $request
     * @param int $patientId
     * @return JsonResponse
     */
    public function patientVitals(Request $request, int $patientId): JsonResponse
    {
        try {
            $filters = $request->only(['date_from', 'date_to', 'abnormal_only']);
            $perPage = $request->get('per_page', 15);
            $latestOnly = $request->boolean('latest_only', false);

            if ($latestOnly) {
                $result = $this->vitalService->getLatestPatientVitals($patientId);
                
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
                    'data' => $result['data'] ? new VitalResource($result['data']) : null,
                    'errors' => null,
                ]);
            }

            $result = $this->vitalService->getPatientVitals($patientId, $filters, $perPage);

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'errors' => ['system' => [$result['error'] ?? 'Unknown error']],
                    'data' => null,
                ], 500);
            }

            $vitals = $result['data'];

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => VitalResource::collection($vitals),
                'meta' => [
                    'total' => $vitals->total(),
                    'per_page' => $vitals->perPage(),
                    'current_page' => $vitals->currentPage(),
                    'last_page' => $vitals->lastPage(),
                    'patient_id' => $patientId,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get patient vital records', [
                'error' => $e->getMessage(),
                'patient_id' => $patientId,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve patient vital records',
                'errors' => ['system' => ['An unexpected error occurred']],
                'data' => null,
            ], 500);
        }
    }

    /**
     * Get vital records for a specific visit.
     *
     * @param int $visitId
     * @return JsonResponse
     */
    public function visitVitals(int $visitId): JsonResponse
    {
        try {
            $result = $this->vitalService->getVisitVitals($visitId);

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
                'data' => VitalResource::collection($result['data']),
                'meta' => [
                    'count' => $result['data']->count(),
                    'visit_id' => $visitId,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get visit vital records', [
                'error' => $e->getMessage(),
                'visit_id' => $visitId,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve visit vital records',
                'errors' => ['system' => ['An unexpected error occurred']],
                'data' => null,
            ], 500);
        }
    }

    /**
     * Get abnormal vital records.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function abnormal(Request $request): JsonResponse
    {
        try {
            $facilityId = $request->get('facility_id');
            $limit = $request->get('limit', 50);

            $result = $this->vitalService->getAbnormalVitals($facilityId, $limit);

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
                'data' => VitalResource::collection($result['data']),
                'meta' => [
                    'count' => $result['data']->count(),
                    'facility_id' => $facilityId,
                    'limit' => $limit,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get abnormal vital records', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve abnormal vital records',
                'errors' => ['system' => ['An unexpected error occurred']],
                'data' => null,
            ], 500);
        }
    }

    /**
     * Get critical vital records requiring attention.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function critical(Request $request): JsonResponse
    {
        try {
            $facilityId = $request->get('facility_id');
            $limit = $request->get('limit', 50);

            $result = $this->vitalService->getCriticalVitals($facilityId, $limit);

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
                'data' => VitalResource::collection($result['data']),
                'meta' => [
                    'count' => $result['data']->count(),
                    'facility_id' => $facilityId,
                    'limit' => $limit,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get critical vital records', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve critical vital records',
                'errors' => ['system' => ['An unexpected error occurred']],
                'data' => null,
            ], 500);
        }
    }

    /**
     * Get vital signs trend for a patient.
     *
     * @param Request $request
     * @param int $patientId
     * @return JsonResponse
     */
    public function trend(Request $request, int $patientId): JsonResponse
    {
        try {
            $vitalType = $request->get('vital_type');
            $limit = $request->get('limit', 10);

            if (!$vitalType) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vital type is required',
                    'errors' => ['vital_type' => ['Vital type parameter is required']],
                    'data' => null,
                ], 422);
            }

            $result = $this->vitalService->getVitalTrend($patientId, $vitalType, $limit);

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
                    'patient_id' => $patientId,
                    'vital_type' => $vitalType,
                    'limit' => $limit,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get vital trend', [
                'error' => $e->getMessage(),
                'patient_id' => $patientId,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve vital trend',
                'errors' => ['system' => ['An unexpected error occurred']],
                'data' => null,
            ], 500);
        }
    }

    /**
     * Get vital statistics for a facility.
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

            if (!$startDate || !$endDate) {
                return response()->json([
                    'success' => false,
                    'message' => 'Start date and end date are required',
                    'errors' => ['date_range' => ['Both start_date and end_date are required']],
                    'data' => null,
                ], 422);
            }

            $result = $this->vitalService->getVitalStatistics($facilityId, $startDate, $endDate);

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
            Log::error('Failed to get vital statistics', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve vital statistics',
                'errors' => ['system' => ['An unexpected error occurred']],
                'data' => null,
            ], 500);
        }
    }
}