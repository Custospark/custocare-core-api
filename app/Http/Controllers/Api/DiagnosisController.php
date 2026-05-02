<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Diagnosis\StoreDiagnosisRequest;
use App\Http\Requests\Diagnosis\UpdateDiagnosisRequest;
use App\Http\Resources\DiagnosisResource;
use App\Http\Resources\DiagnosisCollection;
use App\Services\Contracts\DiagnosisServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DiagnosisController extends Controller
{
    /**
     * @var DiagnosisServiceInterface
     */
    protected DiagnosisServiceInterface $diagnosisService;

    /**
     * Constructor.
     *
     * @param DiagnosisServiceInterface $diagnosisService
     */
    public function __construct(DiagnosisServiceInterface $diagnosisService)
    {
        $this->diagnosisService = $diagnosisService;
    }

    /**
     * Display a listing of diagnoses.
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
                'diagnosis_type',
                'clinical_status',
                'certainty',
                'verification_status',
                'diagnosis_code',
                'search',
                'date_from',
                'date_to',
                'order_by',
                'order_direction',
            ]);

            $perPage = $request->get('per_page', 15);

            $result = $this->diagnosisService->getAllDiagnoses($filters, $perPage);

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'errors' => ['system' => [$result['error'] ?? 'Unknown error']],
                    'data' => null,
                ], 500);
            }

            $diagnoses = $result['data'];

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => DiagnosisResource::collection($diagnoses),
                'meta' => [
                    'total' => $diagnoses->total(),
                    'per_page' => $diagnoses->perPage(),
                    'current_page' => $diagnoses->currentPage(),
                    'last_page' => $diagnoses->lastPage(),
                    'from' => $diagnoses->firstItem(),
                    'to' => $diagnoses->lastItem(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve diagnoses list', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve diagnoses',
                'errors' => ['system' => ['An unexpected error occurred']],
                'data' => null,
            ], 500);
        }
    }

    /**
     * Store a newly created diagnosis.
     *
     * @param StoreDiagnosisRequest $request
     * @return JsonResponse
     */
    public function store(StoreDiagnosisRequest $request): JsonResponse
    {
        try {
            $validatedData = $request->validated();
            $createdByStaffId = $validatedData['staff_id'] ?? $request->user()?->id ?? 1;

            $result = $this->diagnosisService->createDiagnosis($validatedData, $createdByStaffId);

            if (!$result['success']) {
                $statusCode = isset($result['error']) && $result['error'] === 'Duplicate diagnosis' ? 409 : 422;
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'errors' => is_array($result['error']) ? $result['error'] : ['diagnosis' => [$result['error'] ?? 'Creation failed']],
                    'data' => null,
                ], $statusCode);
            }

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => new DiagnosisResource($result['data']),
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
            Log::error('Failed to create diagnosis', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create diagnosis',
                'data' => null,
                'errors' => ['system' => ['An unexpected error occurred']],
            ], 500);
        }
    }

    /**
     * Display the specified diagnosis.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        try {
            $result = $this->diagnosisService->getDiagnosisById($id);

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'errors' => ['diagnosis' => [$result['error'] ?? 'Not found']],
                    'data' => null,
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => new DiagnosisResource($result['data']),
                'errors' => null,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve diagnosis', [
                'error' => $e->getMessage(),
                'id' => $id,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve diagnosis',
                'errors' => ['system' => ['An unexpected error occurred']],
                'data' => null,
            ], 500);
        }
    }

    /**
     * Update the specified diagnosis.
     *
     * @param UpdateDiagnosisRequest $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(UpdateDiagnosisRequest $request, int $id): JsonResponse
    {
        try {
            $validatedData = array_filter($request->validated(), fn($value) => !is_null($value));
            $updatedByStaffId = $request->user()?->id ?? 1;

            $result = $this->diagnosisService->updateDiagnosis($id, $validatedData, $updatedByStaffId);

            if (!$result['success']) {
                $statusCode = 422;
                if (isset($result['error'])) {
                    if ($result['error'] === 'Diagnosis not found') $statusCode = 404;
                    if ($result['error'] === 'Cannot edit verified diagnosis') $statusCode = 403;
                    if ($result['error'] === 'Duplicate diagnosis') $statusCode = 409;
                }
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'errors' => is_array($result['error']) ? $result['error'] : ['diagnosis' => [$result['error'] ?? 'Update failed']],
                    'data' => null,
                ], $statusCode);
            }

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => new DiagnosisResource($result['data']),
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
            Log::error('Failed to update diagnosis', [
                'error' => $e->getMessage(),
                'id' => $id,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update diagnosis',
                'errors' => ['system' => ['An unexpected error occurred']],
                'data' => null,
            ], 500);
        }
    }

    /**
     * Remove the specified diagnosis (soft delete).
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $result = $this->diagnosisService->deleteDiagnosis($id);

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'errors' => ['diagnosis' => [$result['error'] ?? 'Delete failed']],
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
            Log::error('Failed to delete diagnosis', [
                'error' => $e->getMessage(),
                'id' => $id,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete diagnosis',
                'errors' => ['system' => ['An unexpected error occurred']],
                'data' => null,
            ], 500);
        }
    }

    /**
     * Get diagnoses for a specific patient.
     *
     * @param Request $request
     * @param int $patientId
     * @return JsonResponse
     */
    public function patientDiagnoses(Request $request, int $patientId): JsonResponse
    {
        try {
            $filters = $request->only(['diagnosis_type', 'clinical_status', 'limit']);
            $includeActive = $request->boolean('active_only', false);

            if ($includeActive) {
                $result = $this->diagnosisService->getActivePatientDiagnoses($patientId);
            } else {
                $result = $this->diagnosisService->getPatientDiagnoses($patientId, $filters);
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
                'data' => DiagnosisResource::collection($result['data']),
                'meta' => [
                    'count' => $result['data']->count(),
                    'patient_id' => $patientId,
                    'active_only' => $includeActive,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get patient diagnoses', [
                'error' => $e->getMessage(),
                'patient_id' => $patientId,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve patient diagnoses',
                'errors' => ['system' => ['An unexpected error occurred']],
                'data' => null,
            ], 500);
        }
    }

    /**
     * Get primary diagnoses for a patient.
     *
     * @param int $patientId
     * @return JsonResponse
     */
    public function primaryDiagnoses(int $patientId): JsonResponse
    {
        try {
            $result = $this->diagnosisService->getPrimaryPatientDiagnoses($patientId);

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
                'data' => DiagnosisResource::collection($result['data']),
                'meta' => [
                    'count' => $result['data']->count(),
                    'patient_id' => $patientId,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get primary diagnoses', [
                'error' => $e->getMessage(),
                'patient_id' => $patientId,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve primary diagnoses',
                'errors' => ['system' => ['An unexpected error occurred']],
                'data' => null,
            ], 500);
        }
    }

    /**
     * Get diagnoses for a specific visit.
     *
     * @param int $visitId
     * @return JsonResponse
     */
    public function visitDiagnoses(int $visitId): JsonResponse
    {
        try {
            $result = $this->diagnosisService->getVisitDiagnoses($visitId);

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
                'data' => DiagnosisResource::collection($result['data']),
                'meta' => [
                    'count' => $result['data']->count(),
                    'visit_id' => $visitId,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get visit diagnoses', [
                'error' => $e->getMessage(),
                'visit_id' => $visitId,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve visit diagnoses',
                'errors' => ['system' => ['An unexpected error occurred']],
                'data' => null,
            ], 500);
        }
    }

    /**
     * Verify a diagnosis.
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function verify(Request $request, int $id): JsonResponse
    {
        try {
            $verifiedByStaffId = $request->user()?->id ?? 1;

            $result = $this->diagnosisService->verifyDiagnosis($id, $verifiedByStaffId);

            if (!$result['success']) {
                $statusCode = isset($result['error']) && $result['error'] === 'Diagnosis not found' ? 404 : 422;
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'errors' => ['diagnosis' => [$result['error'] ?? 'Verification failed']],
                    'data' => null,
                ], $statusCode);
            }

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => new DiagnosisResource($result['data']),
                'errors' => null,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to verify diagnosis', [
                'error' => $e->getMessage(),
                'id' => $id,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to verify diagnosis',
                'errors' => ['system' => ['An unexpected error occurred']],
                'data' => null,
            ], 500);
        }
    }

    /**
     * Mark diagnosis as disputed.
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function dispute(Request $request, int $id): JsonResponse
    {
        try {
            $reason = $request->get('reason');
            $result = $this->diagnosisService->disputeDiagnosis($id, $reason);

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'errors' => ['diagnosis' => [$result['error'] ?? 'Dispute failed']],
                    'data' => null,
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => new DiagnosisResource($result['data']),
                'errors' => null,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to dispute diagnosis', [
                'error' => $e->getMessage(),
                'id' => $id,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to dispute diagnosis',
                'errors' => ['system' => ['An unexpected error occurred']],
                'data' => null,
            ], 500);
        }
    }

    /**
     * Resolve a diagnosis.
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function resolve(Request $request, int $id): JsonResponse
    {
        try {
            $resolutionNotes = $request->get('resolution_notes');
            $result = $this->diagnosisService->resolveDiagnosis($id, $resolutionNotes);

            if (!$result['success']) {
                $statusCode = isset($result['error']) && $result['error'] === 'Diagnosis not found' ? 404 : 422;
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'errors' => ['diagnosis' => [$result['error'] ?? 'Resolution failed']],
                    'data' => null,
                ], $statusCode);
            }

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => new DiagnosisResource($result['data']),
                'errors' => null,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to resolve diagnosis', [
                'error' => $e->getMessage(),
                'id' => $id,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to resolve diagnosis',
                'errors' => ['system' => ['An unexpected error occurred']],
                'data' => null,
            ], 500);
        }
    }

    /**
     * Reactivate a resolved diagnosis.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function reactivate(int $id): JsonResponse
    {
        try {
            $result = $this->diagnosisService->reactivateDiagnosis($id);

            if (!$result['success']) {
                $statusCode = isset($result['error']) && $result['error'] === 'Diagnosis not found' ? 404 : 422;
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'errors' => ['diagnosis' => [$result['error'] ?? 'Reactivation failed']],
                    'data' => null,
                ], $statusCode);
            }

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => new DiagnosisResource($result['data']),
                'errors' => null,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to reactivate diagnosis', [
                'error' => $e->getMessage(),
                'id' => $id,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to reactivate diagnosis',
                'errors' => ['system' => ['An unexpected error occurred']],
                'data' => null,
            ], 500);
        }
    }

    /**
     * Get diagnosis statistics for a patient.
     *
     * @param int $patientId
     * @return JsonResponse
     */
    public function statistics(int $patientId): JsonResponse
    {
        try {
            $result = $this->diagnosisService->getPatientDiagnosisStatistics($patientId);

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'errors' => ['system' => [$result['error'] ?? 'Statistics retrieval failed']],
                    'data' => null,
                ], 500);
            }

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => $result['data'],
                'errors' => null,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get diagnosis statistics', [
                'error' => $e->getMessage(),
                'patient_id' => $patientId,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve statistics',
                'errors' => ['system' => ['An unexpected error occurred']],
                'data' => null,
            ], 500);
        }
    }

    /**
     * Get most common diagnoses in a facility.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function mostCommon(Request $request): JsonResponse
    {
        try {
            $facilityId = $request->get('facility_id');
            $limit = $request->get('limit', 10);

            if (!$facilityId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Facility ID is required',
                    'errors' => ['facility_id' => ['Facility ID is required']],
                    'data' => null,
                ], 422);
            }

            $result = $this->diagnosisService->getMostCommonDiagnoses($facilityId, $limit);

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'errors' => ['system' => [$result['error'] ?? 'Retrieval failed']],
                    'data' => null,
                ], 500);
            }

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => $result['data'],
                'meta' => [
                    'facility_id' => $facilityId,
                    'limit' => $limit,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get most common diagnoses', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve most common diagnoses',
                'errors' => ['system' => ['An unexpected error occurred']],
                'data' => null,
            ], 500);
        }
    }

    /**
     * Search diagnoses by code or description.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function search(Request $request): JsonResponse
    {
        try {
            $searchTerm = $request->get('q');
            $facilityId = $request->get('facility_id');
            $limit = $request->get('limit', 20);

            if (!$searchTerm) {
                return response()->json([
                    'success' => false,
                    'message' => 'Search term is required',
                    'errors' => ['q' => ['Search term is required']],
                    'data' => null,
                ], 422);
            }

            $result = $this->diagnosisService->searchDiagnoses($searchTerm, $facilityId, $limit);

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'errors' => ['system' => [$result['error'] ?? 'Search failed']],
                    'data' => null,
                ], 500);
            }

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => DiagnosisResource::collection($result['data']),
                'meta' => [
                    'count' => $result['data']->count(),
                    'search_term' => $searchTerm,
                    'limit' => $limit,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to search diagnoses', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to search diagnoses',
                'errors' => ['system' => ['An unexpected error occurred']],
                'data' => null,
            ], 500);
        }
    }

    /**
     * Get ICD code suggestions.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function suggestIcd(Request $request): JsonResponse
    {
        try {
            $searchTerm = $request->get('q');
            $limit = $request->get('limit', 10);

            if (!$searchTerm) {
                return response()->json([
                    'success' => false,
                    'message' => 'Search term is required',
                    'errors' => ['q' => ['Search term is required']],
                    'data' => null,
                ], 422);
            }

            $result = $this->diagnosisService->suggestIcdCodes($searchTerm, $limit);

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'errors' => ['system' => [$result['error'] ?? 'Suggestion failed']],
                    'data' => null,
                ], 500);
            }

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => $result['data'],
                'meta' => [
                    'search_term' => $searchTerm,
                    'limit' => $limit,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to suggest ICD codes', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get suggestions',
                'errors' => ['system' => ['An unexpected error occurred']],
                'data' => null,
            ], 500);
        }
    }

    /**
     * Restore a soft-deleted diagnosis.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function restore(int $id): JsonResponse
    {
        try {
            $result = $this->diagnosisService->restoreDiagnosis($id);

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'errors' => ['diagnosis' => [$result['error'] ?? 'Restore failed']],
                    'data' => null,
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => new DiagnosisResource($result['data']),
                'errors' => null,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to restore diagnosis', [
                'error' => $e->getMessage(),
                'id' => $id,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to restore diagnosis',
                'errors' => ['system' => ['An unexpected error occurred']],
                'data' => null,
            ], 500);
        }
    }
}