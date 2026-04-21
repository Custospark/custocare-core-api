<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Billing;

use App\Http\Controllers\Controller;
use App\Services\Prescription\PrescriptionBillingService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PrescriptionBillingController extends Controller
{
    protected PrescriptionBillingService $billingService;

    public function __construct(PrescriptionBillingService $billingService)
    {
        $this->billingService = $billingService;
    }

    /**
     * Get all active prescriptions ready for billing for a patient
     */
    public function getForPatient(int $patientId): JsonResponse
    {
        $result = $this->billingService->getPrescriptionsForBilling($patientId);
        
        return response()->json($result);
    }

    /**
     * Get single prescription for billing import
     */
    public function getPrescription(int $prescriptionId): JsonResponse
    {
        $result = $this->billingService->getSinglePrescriptionForBilling($prescriptionId);
        
        $statusCode = $result['success'] ? 200 : 404;
        
        return response()->json($result, $statusCode);
    }

    /**
     * Get multiple prescriptions for bulk billing import
     */
    public function getMultiple(Request $request): JsonResponse
    {
        $request->validate([
            'prescription_ids' => ['required', 'array', 'min:1'],
            'prescription_ids.*' => ['exists:prescriptions,id']
        ]);
        
        $allResults = [];
        
        foreach ($request->input('prescription_ids') as $prescriptionId) {
            $result = $this->billingService->getSinglePrescriptionForBilling($prescriptionId);
            if ($result['success']) {
                $allResults[] = $result['data'];
            }
        }
        
        return response()->json([
            'success' => true,
            'message' => count($allResults) . ' prescriptions retrieved successfully',
            'data' => $allResults,
            'meta' => [
                'total_prescriptions' => count($allResults),
                'requested_ids' => $request->input('prescription_ids')
            ]
        ]);
    }
}