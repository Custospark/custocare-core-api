<?php

declare(strict_types=1);

namespace App\Services\Prescription;

use App\Repositories\Contracts\PrescriptionRepositoryInterface;

class PrescriptionBillingService
{
    protected PrescriptionRepositoryInterface $prescriptionRepository;

    public function __construct(PrescriptionRepositoryInterface $prescriptionRepository)
    {
        $this->prescriptionRepository = $prescriptionRepository;
    }

    public function getPrescriptionsForBilling(int $patientId): array
    {
        $prescriptions = $this->prescriptionRepository->getReadyForBilling($patientId);
        
        $billingData = [];
        
        foreach ($prescriptions as $prescription) {
            $billingData[] = [
                'prescription_id' => $prescription->id,
                'prescription_number' => $prescription->prescription_number,
                'prescription_date' => $prescription->prescription_date->format('Y-m-d'),
                'valid_until' => $prescription->valid_until ? $prescription->valid_until->format('Y-m-d') : null,
                'doctor_name' => $prescription->prescribedBy->name ?? 'Unknown',
                'items' => $prescription->getBillingItems(),
                'total_quantity' => $prescription->total_quantity,
                'total_items' => $prescription->total_items_count
            ];
        }
        
        return [
            'success' => true,
            'message' => 'Prescriptions retrieved successfully',
            'data' => $billingData
        ];
    }

    public function getSinglePrescriptionForBilling(int $prescriptionId): array
    {
        $prescription = $this->prescriptionRepository->findWithItems($prescriptionId);
        
        if (!$prescription) {
            return [
                'success' => false,
                'message' => 'Prescription not found',
                'data' => null
            ];
        }
        
        return [
            'success' => true,
            'message' => 'Prescription retrieved successfully',
            'data' => [
                'prescription_id' => $prescription->id,
                'prescription_number' => $prescription->prescription_number,
                'prescription_date' => $prescription->prescription_date->format('Y-m-d'),
                'valid_until' => $prescription->valid_until ? $prescription->valid_until->format('Y-m-d') : null,
                'doctor_name' => $prescription->prescribedBy->name ?? 'Unknown',
                'patient_id' => $prescription->patient_id,
                'items' => $prescription->getBillingItems(),
                'total_quantity' => $prescription->total_quantity,
                'total_items' => $prescription->total_items_count
            ]
        ];
    }
}