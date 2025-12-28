<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\MedicationDispense\StoreMedicationDispenseRequest;
use App\Http\Requests\MedicationDispense\UpdateMedicationDispenseRequest;
use App\Http\Resources\MedicationDispenseResource;
use App\Services\Contracts\MedicationDispenseServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MedicationDispenseController extends Controller
{
    /**
     * @var MedicationDispenseServiceInterface
     */
    protected $medicationDispenseService;

    /**
     * Controller constructor.
     *
     * @param MedicationDispenseServiceInterface $medicationDispenseService
     */
    public function __construct(MedicationDispenseServiceInterface $medicationDispenseService)
    {
        $this->medicationDispenseService = $medicationDispenseService;
    }

    /**
     * Display a listing of medication dispenses.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = $request->only([
                'facility_id', 'patient_id', 'prescription_id', 
                'status', 'start_date', 'end_date', 'verified_only'
            ]);
            
            $perPage = $request->get('per_page', 20);
            $result = $this->medicationDispenseService->getAllDispenses($filters, $perPage);

            if (!$result['success']) {
                return $this->errorResponse($result['message'], $result['error'] ?? null);
            }

            return $this->successResponse(
                $result['data'],
                $result['message']
            );
        } catch (\Exception $e) {
            Log::error('Failed to retrieve medication dispenses list', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->errorResponse(
                'Unable to retrieve medication dispenses at this time',
                'SERVER_ERROR'
            );
        }
    }

    /**
     * Store a newly created medication dispense.
     *
     * @param StoreMedicationDispenseRequest $request
     * @return JsonResponse
     */
    public function store(StoreMedicationDispenseRequest $request): JsonResponse
    {
        try {
            $validatedData = $request->validated();
            
            // Add current user as dispensing staff if not specified
            if (!isset($validatedData['dispensed_by_staff_id']) && $request->user()) {
                $validatedData['dispensed_by_staff_id'] = $request->user()->id;
            }

            $result = $this->medicationDispenseService->createDispense($validatedData);

            if (!$result['success']) {
                return $this->errorResponse(
                    $result['message'],
                    $result['error'] ?? null,
                    $result['errors'] ?? null,
                    $result['error'] === 'VALIDATION_FAILED' ? 422 : 400
                );
            }

            return $this->successResponse(
                new MedicationDispenseResource($result['data']),
                $result['message'],
                201
            );
        } catch (\Exception $e) {
            Log::error('Failed to create medication dispense', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $request->user()?->id
            ]);

            return $this->errorResponse(
                'Failed to create medication dispense',
                'CREATE_FAILED'
            );
        }
    }

    /**
     * Display the specified medication dispense.
     *
     * @param string $dispenseUuid
     * @return JsonResponse
     */
    public function show(string $dispenseUuid): JsonResponse
    {
        try {
            $result = $this->medicationDispenseService->getDispenseByUuid($dispenseUuid);

            if (!$result['success']) {
                return $this->errorResponse(
                    $result['message'],
                    $result['error'] ?? null,
                    null,
                    404
                );
            }

            return $this->successResponse(
                new MedicationDispenseResource($result['data']),
                $result['message']
            );
        } catch (\Exception $e) {
            Log::error('Failed to retrieve medication dispense', [
                'dispense_uuid' => $dispenseUuid,
                'error' => $e->getMessage()
            ]);

            return $this->errorResponse(
                'Unable to retrieve dispense details',
                'SERVER_ERROR'
            );
        }
    }

    /**
     * Update the specified medication dispense.
     *
     * @param UpdateMedicationDispenseRequest $request
     * @param string $dispenseUuid
     * @return JsonResponse
     */
    public function update(UpdateMedicationDispenseRequest $request, string $dispenseUuid): JsonResponse
    {
        try {
            $validatedData = $request->validated();
            
            $result = $this->medicationDispenseService->updateDispense($dispenseUuid, $validatedData);

            if (!$result['success']) {
                $statusCode = match($result['error'] ?? null) {
                    'DISPENSE_NOT_FOUND' => 404,
                    'DISPENSE_VERIFIED' => 403,
                    'VALIDATION_FAILED' => 422,
                    default => 400
                };

                return $this->errorResponse(
                    $result['message'],
                    $result['error'] ?? null,
                    $result['errors'] ?? null,
                    $statusCode
                );
            }

            return $this->successResponse(
                new MedicationDispenseResource($result['data']),
                $result['message']
            );
        } catch (\Exception $e) {
            Log::error('Failed to update medication dispense', [
                'dispense_uuid' => $dispenseUuid,
                'error' => $e->getMessage(),
                'user_id' => $request->user()?->id
            ]);

            return $this->errorResponse(
                'Failed to update medication dispense',
                'UPDATE_FAILED'
            );
        }
    }

    /**
     * Remove the specified medication dispense.
     *
     * @param string $dispenseUuid
     * @return JsonResponse
     */
    public function destroy(string $dispenseUuid): JsonResponse
    {
        try {
            $result = $this->medicationDispenseService->deleteDispense($dispenseUuid);

            if (!$result['success']) {
                $statusCode = match($result['error'] ?? null) {
                    'DISPENSE_NOT_FOUND' => 404,
                    'DISPENSE_VERIFIED', 'DISPENSE_PICKED_UP' => 403,
                    default => 400
                };

                return $this->errorResponse(
                    $result['message'],
                    $result['error'] ?? null,
                    null,
                    $statusCode
                );
            }

            return $this->successResponse(
                null,
                $result['message']
            );
        } catch (\Exception $e) {
            Log::error('Failed to delete medication dispense', [
                'dispense_uuid' => $dispenseUuid,
                'error' => $e->getMessage()
            ]);

            return $this->errorResponse(
                'Failed to delete medication dispense',
                'DELETE_FAILED'
            );
        }
    }

    /**
     * Verify a medication dispense (pharmacist check).
     *
     * @param Request $request
     * @param string $dispenseUuid
     * @return JsonResponse
     */
    public function verify(Request $request, string $dispenseUuid): JsonResponse
    {
        try {
            $request->validate([
                'pharmacist_notes' => 'nullable|string|max:1000',
                'safety_confirmation' => 'required|boolean'
            ]);

            $pharmacistId = $request->user()->id;
            
            $result = $this->medicationDispenseService->verifyDispense(
                $dispenseUuid,
                $pharmacistId,
                $request->all()
            );

            if (!$result['success']) {
                $statusCode = match($result['error'] ?? null) {
                    'DISPENSE_NOT_FOUND' => 404,
                    'ALREADY_VERIFIED' => 409,
                    'SELF_VERIFICATION_NOT_ALLOWED' => 403,
                    'VALIDATION_FAILED' => 422,
                    default => 400
                };

                return $this->errorResponse(
                    $result['message'],
                    $result['error'] ?? null,
                    $result['errors'] ?? null,
                    $statusCode
                );
            }

            return $this->successResponse(
                new MedicationDispenseResource($result['data']),
                $result['message']
            );
        } catch (\Exception $e) {
            Log::error('Failed to verify medication dispense', [
                'dispense_uuid' => $dispenseUuid,
                'pharmacist_id' => $request->user()?->id,
                'error' => $e->getMessage()
            ]);

            return $this->errorResponse(
                'Failed to verify dispense',
                'VERIFICATION_FAILED'
            );
        }
    }

    /**
     * Mark dispense as picked up.
     *
     * @param Request $request
     * @param string $dispenseUuid
     * @return JsonResponse
     */
    public function markAsPickedUp(Request $request, string $dispenseUuid): JsonResponse
    {
        try {
            $request->validate([
                'picked_up_by_name' => 'required|string|max:200',
                'pickup_id_verified' => 'required|string|max:100',
                'delivery_method' => 'nullable|in:pickup_in_person,mail_order,delivery_service,administered_in_facility,sent_to_home_health'
            ]);

            $result = $this->medicationDispenseService->markAsPickedUp(
                $dispenseUuid,
                $request->all()
            );

            if (!$result['success']) {
                $statusCode = match($result['error'] ?? null) {
                    'DISPENSE_NOT_FOUND' => 404,
                    'ALREADY_PICKED_UP' => 409,
                    'INVALID_PICKUP_DATA' => 422,
                    default => 400
                };

                return $this->errorResponse(
                    $result['message'],
                    $result['error'] ?? null,
                    $result['errors'] ?? null,
                    $statusCode
                );
            }

            return $this->successResponse(
                new MedicationDispenseResource($result['data']),
                $result['message']
            );
        } catch (\Exception $e) {
            Log::error('Failed to mark dispense as picked up', [
                'dispense_uuid' => $dispenseUuid,
                'error' => $e->getMessage()
            ]);

            return $this->errorResponse(
                'Failed to update pickup status',
                'PICKUP_UPDATE_FAILED'
            );
        }
    }

    /**
     * Update dispense status.
     *
     * @param Request $request
     * @param string $dispenseUuid
     * @return JsonResponse
     */
    public function updateStatus(Request $request, string $dispenseUuid): JsonResponse
    {
        try {
            $request->validate([
                'status' => 'required|in:dispensed,not_picked_up,returned,destroyed',
                'reason' => 'nullable|string|max:500'
            ]);

            $result = $this->medicationDispenseService->updateDispenseStatus(
                $dispenseUuid,
                $request->status,
                $request->reason
            );

            if (!$result['success']) {
                $statusCode = match($result['error'] ?? null) {
                    'DISPENSE_NOT_FOUND' => 404,
                    'INVALID_STATUS' => 422,
                    'NOT_PICKED_UP' => 400,
                    default => 400
                };

                return $this->errorResponse(
                    $result['message'],
                    $result['error'] ?? null,
                    null,
                    $statusCode
                );
            }

            return $this->successResponse(
                new MedicationDispenseResource($result['data']),
                $result['message']
            );
        } catch (\Exception $e) {
            Log::error('Failed to update dispense status', [
                'dispense_uuid' => $dispenseUuid,
                'status' => $request->status,
                'error' => $e->getMessage()
            ]);

            return $this->errorResponse(
                'Failed to update dispense status',
                'STATUS_UPDATE_FAILED'
            );
        }
    }

    /**
     * Get dispenses by prescription.
     *
     * @param int $prescriptionId
     * @return JsonResponse
     */
    public function getByPrescription(int $prescriptionId): JsonResponse
    {
        try {
            $result = $this->medicationDispenseService->getDispensesByPrescription($prescriptionId);

            if (!$result['success']) {
                return $this->errorResponse(
                    $result['message'],
                    $result['error'] ?? null
                );
            }

            return $this->successResponse(
                MedicationDispenseResource::collection($result['data']),
                $result['message']
            );
        } catch (\Exception $e) {
            Log::error('Failed to get dispenses by prescription', [
                'prescription_id' => $prescriptionId,
                'error' => $e->getMessage()
            ]);

            return $this->errorResponse(
                'Unable to retrieve dispenses for this prescription',
                'SERVER_ERROR'
            );
        }
    }

    /**
     * Get dispenses by patient.
     *
     * @param Request $request
     * @param int $patientId
     * @return JsonResponse
     */
    public function getByPatient(Request $request, int $patientId): JsonResponse
    {
        try {
            $filters = $request->only(['status', 'start_date', 'end_date']);
            $perPage = $request->get('per_page', 20);

            $result = $this->medicationDispenseService->getDispensesByPatient($patientId, $filters, $perPage);

            if (!$result['success']) {
                return $this->errorResponse(
                    $result['message'],
                    $result['error'] ?? null
                );
            }

            return $this->successResponse(
                $result['data'],
                $result['message']
            );
        } catch (\Exception $e) {
            Log::error('Failed to get dispenses by patient', [
                'patient_id' => $patientId,
                'error' => $e->getMessage()
            ]);

            return $this->errorResponse(
                'Unable to retrieve patient dispenses',
                'SERVER_ERROR'
            );
        }
    }

    /**
     * Get facility statistics.
     *
     * @param Request $request
     * @param int $facilityId
     * @return JsonResponse
     */
    public function getFacilityStatistics(Request $request, int $facilityId): JsonResponse
    {
        try {
            $request->validate([
                'start_date' => 'required|date|before_or_equal:end_date',
                'end_date' => 'required|date|after_or_equal:start_date'
            ]);

            $result = $this->medicationDispenseService->getFacilityStatistics(
                $facilityId,
                $request->start_date,
                $request->end_date
            );

            if (!$result['success']) {
                return $this->errorResponse(
                    $result['message'],
                    $result['error'] ?? null
                );
            }

            return $this->successResponse(
                $result['data'],
                $result['message']
            );
        } catch (\Exception $e) {
            Log::error('Failed to get facility statistics', [
                'facility_id' => $facilityId,
                'error' => $e->getMessage()
            ]);

            return $this->errorResponse(
                'Unable to retrieve facility statistics',
                'SERVER_ERROR'
            );
        }
    }

    /**
     * Return a success response.
     *
     * @param mixed $data
     * @param string $message
     * @param int $statusCode
     * @return JsonResponse
     */
    protected function successResponse($data = null, string $message = 'Success', int $statusCode = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data
        ], $statusCode);
    }

    /**
     * Return an error response.
     *
     * @param string $message
     * @param string|null $errorCode
     * @param array|null $errors
     * @param int $statusCode
     * @return JsonResponse
     */
    protected function errorResponse(
        string $message = 'An error occurred',
        ?string $errorCode = null,
        ?array $errors = null,
        int $statusCode = 400
    ): JsonResponse {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        if ($errorCode) {
            $response['error'] = $errorCode;
        }

        if ($errors) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $statusCode);
    }
}