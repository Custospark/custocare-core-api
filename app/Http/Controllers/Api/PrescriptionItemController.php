<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PrescriptionItem\AddPrescriptionItemRequest;
use App\Http\Requests\PrescriptionItem\UpdatePrescriptionItemRequest;
use App\Http\Requests\PrescriptionItem\BulkUpdatePrescriptionItemRequest;
use App\Services\Prescription\PrescriptionItemService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class PrescriptionItemController extends Controller
{
    protected PrescriptionItemService $prescriptionItemService;

    public function __construct(PrescriptionItemService $prescriptionItemService)
    {
        $this->prescriptionItemService = $prescriptionItemService;
    }

    /**
     * Get all items for a prescription
     * 
     * GET /prescriptions/{prescriptionId}/items
     */
    public function index(int $prescriptionId): JsonResponse
    {
        $result = $this->prescriptionItemService->getPrescriptionItems($prescriptionId);
        
        $statusCode = $result['success'] ? 200 : 404;
        
        return response()->json($result, $statusCode);
    }

    /**
     * Create a new prescription item
     * 
     * POST /prescriptions/{id}/items
     */
    public function store(AddPrescriptionItemRequest $request, int $id): JsonResponse
    {
        $userId = Auth::id();
        
        $result = $this->prescriptionItemService->store($id, $request->validated(), $userId);
        
        $statusCode = $result['success'] ? 201 : 400;
        
        return response()->json($result, $statusCode);
    }

    /**
     * Update a prescription item
     * 
     * PUT /prescription-items/{id}
     */
    public function update(UpdatePrescriptionItemRequest $request, int $id): JsonResponse
    {
        $userId = Auth::id();
        
        $result = $this->prescriptionItemService->updatePrescriptionItem($id, $request->validated(), $userId);
        
        $statusCode = $result['success'] ? 200 : 404;
        
        return response()->json($result, $statusCode);
    }

    /**
     * Delete a prescription item
     * 
     * DELETE /prescription-items/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        $result = $this->prescriptionItemService->deletePrescriptionItem($id);
        
        $statusCode = $result['success'] ? 200 : 404;
        
        return response()->json($result, $statusCode);
    }

    /**
     * Bulk update prescription items (add, update, delete multiple at once)
     * 
     * PUT /prescriptions/{prescriptionId}/items/bulk
     */
    public function bulkUpdate(BulkUpdatePrescriptionItemRequest $request, int $prescriptionId): JsonResponse
    {
        $userId = Auth::id();
        
        $result = $this->prescriptionItemService->bulkUpdatePrescriptionItems(
            $prescriptionId,
            $request->input('items'),
            $userId
        );
        
        $statusCode = $result['success'] ? 200 : 400;
        
        return response()->json($result, $statusCode);
    }
}