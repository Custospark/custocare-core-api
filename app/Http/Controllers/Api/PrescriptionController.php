<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Prescription\StorePrescriptionRequest;
use App\Http\Requests\Prescription\UpdatePrescriptionRequest;
use App\Http\Requests\Prescription\CancelPrescriptionRequest;
use App\Http\Requests\PrescriptionItem\AddPrescriptionItemRequest;
use App\Http\Resources\PrescriptionResource;
use App\Services\Prescription\PrescriptionService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class PrescriptionController extends Controller
{
    protected PrescriptionService $prescriptionService;

    public function __construct(PrescriptionService $prescriptionService)
    {
        $this->prescriptionService = $prescriptionService;
    }

    /**
     * Get all prescriptions with optional filters
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['facility_id', 'patient_id', 'status', 'date_from', 'date_to']);
        $prescriptions = $this->prescriptionService->getAllPrescriptions($filters);
        
        return response()->json([
            'success' => true,
            'message' => 'Prescriptions retrieved successfully',
            'data' => PrescriptionResource::collection($prescriptions),
            'meta' => [
                'total' => $prescriptions->count(),
                'filters' => $filters
            ]
        ]);
    }

    /**
     * Get paginated prescriptions
     */
    public function paginate(Request $request): JsonResponse
    {
        $perPage = $request->get('per_page', 15);
        $filters = $request->only(['facility_id', 'patient_id', 'status']);
        $prescriptions = $this->prescriptionService->getPrescriptionsPaginated($perPage, $filters);
        
        return response()->json([
            'success' => true,
            'message' => 'Prescriptions retrieved successfully',
            'data' => PrescriptionResource::collection($prescriptions),
            'meta' => [
                'current_page' => $prescriptions->currentPage(),
                'per_page' => $prescriptions->perPage(),
                'total' => $prescriptions->total(),
                'last_page' => $prescriptions->lastPage(),
            ]
        ]);
    }

    /**
     * Get single prescription with details
     */
    public function show(int $id): JsonResponse
    {
        $prescription = $this->prescriptionService->getPrescription($id);
        
        if (!$prescription) {
            return response()->json([
                'success' => false,
                'message' => 'Prescription not found',
                'data' => null
            ], 404);
        }
        
        return response()->json([
            'success' => true,
            'message' => 'Prescription retrieved successfully',
            'data' => new PrescriptionResource($prescription)
        ]);
    }

    /**
     * Get patient's prescriptions
     */
    public function patientPrescriptions(int $patientId, Request $request): JsonResponse
    {
        $statuses = $request->get('statuses', []);
        $prescriptions = $this->prescriptionService->getPatientPrescriptions($patientId, $statuses);
        
        return response()->json([
            'success' => true,
            'message' => 'Patient prescriptions retrieved successfully',
            'data' => PrescriptionResource::collection($prescriptions),
            'meta' => [
                'patient_id' => $patientId,
                'total' => $prescriptions->count()
            ]
        ]);
    }

    /**
     * Create new prescription
     */
    public function store(StorePrescriptionRequest $request): JsonResponse
    {
        $userId = Auth::id();
        
        $result = $this->prescriptionService->createPrescription(
            $request->except(['items']),
            $request->input('items', []),
            $userId
        );
        
        $statusCode = $result['success'] ? 201 : 400;
        
        return response()->json($result, $statusCode);
    }


   
    
    /**
     * Update prescription
     */
    public function update(UpdatePrescriptionRequest $request, int $id): JsonResponse
    {
        $userId = Auth::id();
        
        $result = $this->prescriptionService->updatePrescription(
            $id,
            $request->except(['items']),
            $request->input('items'),
            $userId
        );
        
        $statusCode = $result['success'] ? 200 : ($result['data'] === null ? 404 : 400);
        
        return response()->json($result, $statusCode);
    }

    /**
     * Delete prescription
     */
    public function destroy(int $id): JsonResponse
    {
        $result = $this->prescriptionService->deletePrescription($id);
        
        $statusCode = $result['success'] ? 200 : 404;
        
        return response()->json($result, $statusCode);
    }

    /**
     * Cancel prescription
     */
    public function cancel(CancelPrescriptionRequest $request, int $id): JsonResponse
    {
        $userId = Auth::id();
        
        $result = $this->prescriptionService->cancelPrescription(
            $id,
            $request->input('cancellation_reason'),
            $userId,
            $request->input('cancellation_notes')
        );
        
        $statusCode = $result['success'] ? 200 : 404;
        
        return response()->json($result, $statusCode);
    }

    /**
     * Mark prescription as dispensed
     */
    public function markDispensed(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'pharmacy_name' => ['nullable', 'string', 'max:255'],
            'dispensed_by_name' => ['nullable', 'string', 'max:255'],
        ]);
        
        $result = $this->prescriptionService->markAsDispensed($id, $request->only(['pharmacy_name', 'dispensed_by_name']));
        
        $statusCode = $result['success'] ? 200 : 404;
        
        return response()->json($result, $statusCode);
    }

    /**
     * Apply template to prescription
     */
    public function applyTemplate(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'template_id' => ['required', 'exists:clinical_templates,id']
        ]);
        
        $userId = Auth::id();
        
        $result = $this->prescriptionService->applyTemplate($id, $request->input('template_id'), $userId);
        
        $statusCode = $result['success'] ? 200 : 400;
        
        return response()->json($result, $statusCode);
    }

    /**
     * Get prescription for billing import
     */
    public function getForBilling(int $id): JsonResponse
    {
        $result = $this->prescriptionService->getPrescriptionForBilling($id);
        
        $statusCode = $result['success'] ? 200 : 404;
        
        return response()->json($result, $statusCode);
    }
}