<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ClinicalNote\StoreClinicalNoteRequest;
use App\Http\Requests\ClinicalNote\UpdateClinicalNoteRequest;
use App\Http\Resources\ClinicalNoteResource;
use App\Http\Resources\ClinicalNoteCollection;
use App\Services\Contracts\ClinicalNoteServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ClinicalNoteController extends Controller
{
    /**
     * @var ClinicalNoteServiceInterface
     */
    protected ClinicalNoteServiceInterface $clinicalNoteService;

    /**
     * Constructor.
     *
     * @param ClinicalNoteServiceInterface $clinicalNoteService
     */
    public function __construct(ClinicalNoteServiceInterface $clinicalNoteService)
    {
        $this->clinicalNoteService = $clinicalNoteService;
    }

    /**
     * Display a listing of clinical notes.
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
                'note_type',
                'note_status',
                'date_from',
                'date_to',
                'search',
                'order_by',
                'order_direction',
            ]);

            $perPage = $request->get('per_page', 15);

            $result = $this->clinicalNoteService->getAllNotes($filters, $perPage);

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'errors' => ['system' => [$result['error'] ?? 'Unknown error']],
                    'data' => null,
                ], 500);
            }

            $notes = $result['data'];

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => ClinicalNoteResource::collection($notes),
                'meta' => [
                    'total' => $notes->total(),
                    'per_page' => $notes->perPage(),
                    'current_page' => $notes->currentPage(),
                    'last_page' => $notes->lastPage(),
                    'from' => $notes->firstItem(),
                    'to' => $notes->lastItem(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve clinical notes list', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve clinical notes',
                'errors' => ['system' => ['An unexpected error occurred']],
                'data' => null,
            ], 500);
        }
    }

    /**
     * Store a newly created clinical note.
     *
     * @param StoreClinicalNoteRequest $request
     * @return JsonResponse
     */
    public function store(StoreClinicalNoteRequest $request): JsonResponse
    {
        try {
            $validatedData = $request->validated();
            $createdByStaffId = $validatedData['staff_id'] ?? $request->user()?->id ?? 1;

            $result = $this->clinicalNoteService->createNote($validatedData, $createdByStaffId);

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'errors' => ['validation' => $result['error'] ?? 'Validation failed'],
                    'data' => null,
                ], 422);
            }

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => new ClinicalNoteResource($result['data']),
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
            Log::error('Failed to create clinical note', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create clinical note',
                'data' => null,
                'errors' => ['system' => ['An unexpected error occurred']],
            ], 500);
        }
    }

    /**
     * Display the specified clinical note.
     *
     * @param string $uuid
     * @return JsonResponse
     */
    public function show(string $uuid): JsonResponse
    {
        try {
            $result = $this->clinicalNoteService->getNoteByUuid($uuid);

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'errors' => ['note' => [$result['error'] ?? 'Not found']],
                    'data' => null,
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => new ClinicalNoteResource($result['data']),
                'errors' => null,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve clinical note', [
                'error' => $e->getMessage(),
                'uuid' => $uuid,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve clinical note',
                'errors' => ['system' => ['An unexpected error occurred']],
                'data' => null,
            ], 500);
        }
    }

    /**
     * Update the specified clinical note.
     *
     * @param UpdateClinicalNoteRequest $request
     * @param string $uuid
     * @return JsonResponse
     */
    public function update(UpdateClinicalNoteRequest $request, string $uuid): JsonResponse
    {
        try {
            $validatedData = array_filter($request->validated(), fn($value) => !is_null($value));
            $updatedByStaffId = $request->user()?->id ?? 1;

            $result = $this->clinicalNoteService->updateNote($uuid, $validatedData, $updatedByStaffId);

            if (!$result['success']) {
                $statusCode = isset($result['error']) && str_contains($result['error'], 'not found') ? 404 : 422;
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'errors' => ['note' => [$result['error'] ?? 'Update failed']],
                    'data' => null,
                ], $statusCode);
            }

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => new ClinicalNoteResource($result['data']),
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
            Log::error('Failed to update clinical note', [
                'error' => $e->getMessage(),
                'uuid' => $uuid,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update clinical note',
                'errors' => ['system' => ['An unexpected error occurred']],
                'data' => null,
            ], 500);
        }
    }

    /**
     * Remove the specified clinical note (soft delete).
     *
     * @param string $uuid
     * @return JsonResponse
     */
    public function destroy(string $uuid): JsonResponse
    {
        try {
            $result = $this->clinicalNoteService->deleteNote($uuid);

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'errors' => ['note' => [$result['error'] ?? 'Delete failed']],
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
            Log::error('Failed to delete clinical note', [
                'error' => $e->getMessage(),
                'uuid' => $uuid,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete clinical note',
                'errors' => ['system' => ['An unexpected error occurred']],
                'data' => null,
            ], 500);
        }
    }

    /**
     * Get notes for a specific patient.
     *
     * @param Request $request
     * @param int $patientId
     * @return JsonResponse
     */
    public function patientNotes(Request $request, int $patientId): JsonResponse
    {
        try {
            $filters = $request->only(['note_type', 'note_status', 'date_from', 'date_to']);
            $perPage = $request->get('per_page', 15);

            $result = $this->clinicalNoteService->getPatientNotes($patientId, $filters, $perPage);

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'errors' => ['system' => [$result['error'] ?? 'Unknown error']],
                    'data' => null,
                ], 500);
            }

            $notes = $result['data'];

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => ClinicalNoteResource::collection($notes),
                'meta' => [
                    'total' => $notes->total(),
                    'per_page' => $notes->perPage(),
                    'current_page' => $notes->currentPage(),
                    'last_page' => $notes->lastPage(),
                    'patient_id' => $patientId,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get patient notes', [
                'error' => $e->getMessage(),
                'patient_id' => $patientId,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve patient notes',
                'errors' => ['system' => ['An unexpected error occurred']],
                'data' => null,
            ], 500);
        }
    }

    /**
     * Get notes for a specific visit.
     *
     * @param int $visitId
     * @return JsonResponse
     */
    public function visitNotes(int $visitId): JsonResponse
    {
        try {
            $result = $this->clinicalNoteService->getVisitNotes($visitId);

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
                'data' => ClinicalNoteResource::collection($result['data']),
                'meta' => [
                    'count' => $result['data']->count(),
                    'visit_id' => $visitId,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get visit notes', [
                'error' => $e->getMessage(),
                'visit_id' => $visitId,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve visit notes',
                'errors' => ['system' => ['An unexpected error occurred']],
                'data' => null,
            ], 500);
        }
    }

    /**
     * Finalize a draft note.
     *
     * @param string $uuid
     * @return JsonResponse
     */
    public function finalize(string $uuid): JsonResponse
    {
        try {
            $result = $this->clinicalNoteService->finalizeNote($uuid);

            if (!$result['success']) {
                $statusCode = isset($result['error']) && str_contains($result['error'], 'not found') ? 404 : 422;
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'errors' => ['note' => [$result['error'] ?? 'Finalization failed']],
                    'data' => null,
                ], $statusCode);
            }

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => new ClinicalNoteResource($result['data']),
                'errors' => null,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to finalize note', [
                'error' => $e->getMessage(),
                'uuid' => $uuid,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to finalize note',
                'errors' => ['system' => ['An unexpected error occurred']],
                'data' => null,
            ], 500);
        }
    }

    /**
     * Cancel a note.
     *
     * @param Request $request
     * @param string $uuid
     * @return JsonResponse
     */
    public function cancel(Request $request, string $uuid): JsonResponse
    {
        try {
            $reason = $request->get('reason');
            $result = $this->clinicalNoteService->cancelNote($uuid, $reason);

            if (!$result['success']) {
                $statusCode = isset($result['error']) && str_contains($result['error'], 'not found') ? 404 : 422;
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'errors' => ['note' => [$result['error'] ?? 'Cancellation failed']],
                    'data' => null,
                ], $statusCode);
            }

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => new ClinicalNoteResource($result['data']),
                'errors' => null,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to cancel note', [
                'error' => $e->getMessage(),
                'uuid' => $uuid,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel note',
                'errors' => ['system' => ['An unexpected error occurred']],
                'data' => null,
            ], 500);
        }
    }

    /**
     * Amend an existing note.
     *
     * @param Request $request
     * @param string $uuid
     * @return JsonResponse
     */
    public function amend(Request $request, string $uuid): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'subjective' => 'nullable|string',
                'objective' => 'nullable|string',
                'assessment' => 'nullable|string',
                'plan' => 'nullable|string',
                'review_of_systems' => 'nullable|string',
                'past_medical_history' => 'nullable|string',
                'custom_fields' => 'nullable|array',
                'structured_data' => 'nullable|array',
            ]);

            $amendedByStaffId = $request->user()?->id ?? 1;

            $result = $this->clinicalNoteService->amendNote($uuid, $validatedData, $amendedByStaffId);

            if (!$result['success']) {
                $statusCode = isset($result['error']) && str_contains($result['error'], 'not found') ? 404 : 422;
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'errors' => ['note' => [$result['error'] ?? 'Amendment failed']],
                    'data' => null,
                ], $statusCode);
            }

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => new ClinicalNoteResource($result['data']),
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
            Log::error('Failed to amend note', [
                'error' => $e->getMessage(),
                'uuid' => $uuid,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to amend note',
                'errors' => ['system' => ['An unexpected error occurred']],
                'data' => null,
            ], 500);
        }
    }

    /**
     * Get note history including amendments.
     *
     * @param string $uuid
     * @return JsonResponse
     */
    public function history(string $uuid): JsonResponse
    {
        try {
            $result = $this->clinicalNoteService->getNoteHistory($uuid);

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'errors' => ['note' => [$result['error'] ?? 'History retrieval failed']],
                    'data' => null,
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => ClinicalNoteResource::collection($result['data']),
                'meta' => [
                    'count' => $result['data']->count(),
                    'note_uuid' => $uuid,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get note history', [
                'error' => $e->getMessage(),
                'uuid' => $uuid,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve note history',
                'errors' => ['system' => ['An unexpected error occurred']],
                'data' => null,
            ], 500);
        }
    }

    /**
     * Search notes by content.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function search(Request $request): JsonResponse
    {
        try {
            $searchTerm = $request->input('q');
            $facilityId = $request->input('facility_id');
            $limit = $request->input('limit', 20);

            if (!$searchTerm) {
                return response()->json([
                    'success' => false,
                    'message' => 'Search term is required',
                    'errors' => ['q' => ['Search term is required']],
                    'data' => null,
                ], 422);
            }

            $result = $this->clinicalNoteService->searchNotes($searchTerm, $facilityId, $limit);

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
                'data' => ClinicalNoteResource::collection($result['data']),
                'meta' => [
                    'count' => $result['data']->count(),
                    'search_term' => $searchTerm,
                    'limit' => $limit,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to search notes', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to search notes',
                'errors' => ['system' => ['An unexpected error occurred']],
                'data' => null,
            ], 500);
        }
    }

    /**
     * Get note statistics for a facility.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function statistics(Request $request): JsonResponse
    {
        try {
            $facilityId = $request->get('facility_id');

            if (!$facilityId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Facility ID is required',
                    'errors' => ['facility_id' => ['Facility ID is required']],
                    'data' => null,
                ], 422);
            }

            $result = $this->clinicalNoteService->getNoteStatistics($facilityId);

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
            Log::error('Failed to get note statistics', [
                'error' => $e->getMessage(),
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
     * Restore a soft-deleted note.
     *
     * @param string $uuid
     * @return JsonResponse
     */
    public function restore(string $uuid): JsonResponse
    {
        try {
            $result = $this->clinicalNoteService->restoreNote($uuid);

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'errors' => ['note' => [$result['error'] ?? 'Restore failed']],
                    'data' => null,
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => new ClinicalNoteResource($result['data']),
                'errors' => null,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to restore note', [
                'error' => $e->getMessage(),
                'uuid' => $uuid,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to restore note',
                'errors' => ['system' => ['An unexpected error occurred']],
                'data' => null,
            ], 500);
        }
    }

    /**
     * Get the latest note for a patient.
     *
     * @param int $patientId
     * @return JsonResponse
     */
    public function latest(int $patientId): JsonResponse
    {
        try {
            $result = $this->clinicalNoteService->getLatestPatientNote($patientId);

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'errors' => ['system' => [$result['error'] ?? 'Retrieval failed']],
                    'data' => null,
                ], 500);
            }

            if (!$result['data']) {
                return response()->json([
                    'success' => true,
                    'message' => 'No notes found for this patient',
                    'data' => null,
                    'errors' => null,
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => new ClinicalNoteResource($result['data']),
                'errors' => null,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get latest note', [
                'error' => $e->getMessage(),
                'patient_id' => $patientId,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve latest note',
                'errors' => ['system' => ['An unexpected error occurred']],
                'data' => null,
            ], 500);
        }
    }
}