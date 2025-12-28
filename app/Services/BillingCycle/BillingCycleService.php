<?php

namespace App\Services\BillingCycle;

use App\Services\Contracts\BillingCycleServiceInterface;
use App\Repositories\Contracts\BillingCycleRepositoryInterface;
use App\Models\BillingCycle;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class BillingCycleService implements BillingCycleServiceInterface
{
    /**
     * Repository instance
     *
     * @var BillingCycleRepositoryInterface
     */
    private BillingCycleRepositoryInterface $repository;

    /**
     * Constructor
     *
     * @param BillingCycleRepositoryInterface $repository
     */
    public function __construct(BillingCycleRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Get all billing cycles with pagination
     *
     * @param array $filters
     * @return array
     */
    public function getAllBillingCycles(array $filters = []): array
    {
        try {
            $perPage = $filters['per_page'] ?? 20;
            $paginator = $this->repository->getAllPaginated($filters, $perPage);
            
            return [
                'success' => true,
                'message' => 'Billing cycles retrieved successfully',
                'data' => [
                    'billing_cycles' => $paginator->items(),
                    'pagination' => [
                        'total' => $paginator->total(),
                        'per_page' => $paginator->perPage(),
                        'current_page' => $paginator->currentPage(),
                        'last_page' => $paginator->lastPage(),
                        'from' => $paginator->firstItem(),
                        'to' => $paginator->lastItem(),
                    ]
                ]
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get all billing cycles', [
                'filters' => $filters,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to retrieve billing cycles',
                'error' => 'An internal server error occurred',
                'data' => []
            ];
        }
    }

    /**
     * Get billing cycle by UUID
     *
     * @param string $uuid
     * @return array
     */
    public function getBillingCycleByUuid(string $uuid): array
    {
        try {
            $billingCycle = $this->repository->findByUuid($uuid);
            
            if (!$billingCycle) {
                return [
                    'success' => false,
                    'message' => 'Billing cycle not found',
                    'error' => 'The requested billing cycle does not exist',
                    'data' => []
                ];
            }
            
            return [
                'success' => true,
                'message' => 'Billing cycle retrieved successfully',
                'data' => [
                    'billing_cycle' => $billingCycle
                ]
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get billing cycle by UUID', [
                'uuid' => $uuid,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to retrieve billing cycle',
                'error' => 'An internal server error occurred',
                'data' => []
            ];
        }
    }

    /**
     * Create a new billing cycle
     *
     * @param array $data
     * @return array
     */
    public function createBillingCycle(array $data): array
    {
        try {
            // Validate required relationships
            if (empty($data['facility_id']) || empty($data['visit_id']) || empty($data['patient_id'])) {
                return [
                    'success' => false,
                    'message' => 'Validation failed',
                    'error' => 'Facility, visit, and patient are required',
                    'data' => []
                ];
            }
            
            // Validate cycle type
            $validCycleTypes = [
                'visit_based', 'admission_discharge', 'daily_inpatient', 'weekly_inpatient',
                'procedure_based', 'bundled_payment', 'subscription'
            ];
            
            if (empty($data['cycle_type']) || !in_array($data['cycle_type'], $validCycleTypes)) {
                return [
                    'success' => false,
                    'message' => 'Validation failed',
                    'error' => 'Invalid or missing cycle type',
                    'data' => []
                ];
            }
            
            // Set default billing status if not provided
            if (empty($data['billing_status'])) {
                $data['billing_status'] = 'draft';
            }
            
            // Validate billing status
            $validBillingStatuses = [
                'draft', 'pending_review', 'pending_submission', 'submitted_to_insurance',
                'partially_paid', 'paid_in_full', 'payment_plan', 'collections',
                'disputed', 'written_off', 'charity_care'
            ];
            
            if (!in_array($data['billing_status'], $validBillingStatuses)) {
                return [
                    'success' => false,
                    'message' => 'Validation failed',
                    'error' => 'Invalid billing status',
                    'data' => []
                ];
            }
            
            // Ensure financial amounts are non-negative
            $financialFields = [
                'total_amount_charged', 'total_adjustments', 'insurance_covered_amount',
                'insurance_adjustment_amount', 'insurance_payment_received',
                'patient_responsibility_amount', 'patient_copay_amount',
                'patient_deductible_amount', 'patient_coinsurance_amount',
                'patient_payment_received', 'discount_applied',
                'contractual_adjustment', 'charity_care_adjustment',
                'bad_debt_adjustment', 'total_tax_amount'
            ];
            
            foreach ($financialFields as $field) {
                if (isset($data[$field]) && $data[$field] < 0) {
                    return [
                        'success' => false,
                        'message' => 'Validation failed',
                        'error' => "{$field} cannot be negative",
                        'data' => []
                    ];
                }
            }
            
            $billingCycle = $this->repository->create($data);
            
            return [
                'success' => true,
                'message' => 'Billing cycle created successfully',
                'data' => [
                    'billing_cycle' => $billingCycle
                ]
            ];
        } catch (\RuntimeException $e) {
            Log::error('Failed to create billing cycle', [
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to create billing cycle',
                'error' => $e->getMessage(),
                'data' => []
            ];
        } catch (\Exception $e) {
            Log::error('Unexpected error creating billing cycle', [
                'data' => $data,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to create billing cycle',
                'error' => 'An unexpected error occurred',
                'data' => []
            ];
        }
    }

    /**
     * Update an existing billing cycle
     *
     * @param string $uuid
     * @param array $data
     * @return array
     */
    public function updateBillingCycle(string $uuid, array $data): array
    {
        try {
            $billingCycle = $this->repository->findByUuid($uuid);
            
            if (!$billingCycle) {
                return [
                    'success' => false,
                    'message' => 'Billing cycle not found',
                    'error' => 'The requested billing cycle does not exist',
                    'data' => []
                ];
            }
            
            // Validate billing status if provided
            if (isset($data['billing_status'])) {
                $validBillingStatuses = [
                    'draft', 'pending_review', 'pending_submission', 'submitted_to_insurance',
                    'partially_paid', 'paid_in_full', 'payment_plan', 'collections',
                    'disputed', 'written_off', 'charity_care'
                ];
                
                if (!in_array($data['billing_status'], $validBillingStatuses)) {
                    return [
                        'success' => false,
                        'message' => 'Validation failed',
                        'error' => 'Invalid billing status',
                        'data' => []
                    ];
                }
            }
            
            // Validate cycle type if provided
            if (isset($data['cycle_type'])) {
                $validCycleTypes = [
                    'visit_based', 'admission_discharge', 'daily_inpatient', 'weekly_inpatient',
                    'procedure_based', 'bundled_payment', 'subscription'
                ];
                
                if (!in_array($data['cycle_type'], $validCycleTypes)) {
                    return [
                        'success' => false,
                        'message' => 'Validation failed',
                        'error' => 'Invalid cycle type',
                        'data' => []
                    ];
                }
            }
            
            // Ensure financial amounts are non-negative
            $financialFields = [
                'total_amount_charged', 'total_adjustments', 'insurance_covered_amount',
                'insurance_adjustment_amount', 'insurance_payment_received',
                'patient_responsibility_amount', 'patient_copay_amount',
                'patient_deductible_amount', 'patient_coinsurance_amount',
                'patient_payment_received', 'discount_applied',
                'contractual_adjustment', 'charity_care_adjustment',
                'bad_debt_adjustment', 'total_tax_amount'
            ];
            
            foreach ($financialFields as $field) {
                if (isset($data[$field]) && $data[$field] < 0) {
                    return [
                        'success' => false,
                        'message' => 'Validation failed',
                        'error' => "{$field} cannot be negative",
                        'data' => []
                    ];
                }
            }
            
            $updatedBillingCycle = $this->repository->update($billingCycle, $data);
            
            return [
                'success' => true,
                'message' => 'Billing cycle updated successfully',
                'data' => [
                    'billing_cycle' => $updatedBillingCycle
                ]
            ];
        } catch (\RuntimeException $e) {
            Log::error('Failed to update billing cycle', [
                'uuid' => $uuid,
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to update billing cycle',
                'error' => $e->getMessage(),
                'data' => []
            ];
        } catch (\Exception $e) {
            Log::error('Unexpected error updating billing cycle', [
                'uuid' => $uuid,
                'data' => $data,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to update billing cycle',
                'error' => 'An unexpected error occurred',
                'data' => []
            ];
        }
    }

    /**
     * Delete a billing cycle
     *
     * @param string $uuid
     * @return array
     */
    public function deleteBillingCycle(string $uuid): array
    {
        try {
            $billingCycle = $this->repository->findByUuid($uuid);
            
            if (!$billingCycle) {
                return [
                    'success' => false,
                    'message' => 'Billing cycle not found',
                    'error' => 'The requested billing cycle does not exist',
                    'data' => []
                ];
            }
            
            // Check if billing cycle can be deleted based on status
            $nonDeletableStatuses = ['submitted_to_insurance', 'partially_paid', 'paid_in_full', 'collections'];
            if (in_array($billingCycle->billing_status, $nonDeletableStatuses)) {
                return [
                    'success' => false,
                    'message' => 'Cannot delete billing cycle',
                    'error' => 'Billing cycle with status ' . $billingCycle->billing_status . ' cannot be deleted',
                    'data' => []
                ];
            }
            
            $success = $this->repository->delete($billingCycle);
            
            if (!$success) {
                return [
                    'success' => false,
                    'message' => 'Failed to delete billing cycle',
                    'error' => 'Failed to delete billing cycle from database',
                    'data' => []
                ];
            }
            
            return [
                'success' => true,
                'message' => 'Billing cycle deleted successfully',
                'data' => []
            ];
        } catch (\Exception $e) {
            Log::error('Failed to delete billing cycle', [
                'uuid' => $uuid,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to delete billing cycle',
                'error' => 'An internal server error occurred',
                'data' => []
            ];
        }
    }

    /**
     * Update billing status
     *
     * @param string $uuid
     * @param string $status
     * @param array $additionalData
     * @return array
     */
    public function updateBillingStatus(string $uuid, string $status, array $additionalData = []): array
    {
        try {
            $billingCycle = $this->repository->findByUuid($uuid);
            
            if (!$billingCycle) {
                return [
                    'success' => false,
                    'message' => 'Billing cycle not found',
                    'error' => 'The requested billing cycle does not exist',
                    'data' => []
                ];
            }
            
            // Validate status
            $validBillingStatuses = [
                'draft', 'pending_review', 'pending_submission', 'submitted_to_insurance',
                'partially_paid', 'paid_in_full', 'payment_plan', 'collections',
                'disputed', 'written_off', 'charity_care'
            ];
            
            if (!in_array($status, $validBillingStatuses)) {
                return [
                    'success' => false,
                    'message' => 'Validation failed',
                    'error' => 'Invalid billing status',
                    'data' => []
                ];
            }
            
            // Validate status transitions
            $allowedTransitions = [
                'draft' => ['pending_review', 'pending_submission', 'disputed'],
                'pending_review' => ['draft', 'pending_submission', 'disputed'],
                'pending_submission' => ['submitted_to_insurance', 'disputed'],
                'submitted_to_insurance' => ['partially_paid', 'paid_in_full', 'disputed'],
                'partially_paid' => ['paid_in_full', 'collections', 'disputed'],
                'payment_plan' => ['partially_paid', 'paid_in_full', 'collections'],
                'collections' => ['paid_in_full', 'written_off'],
                'disputed' => ['draft', 'pending_review', 'pending_submission', 'submitted_to_insurance'],
                'written_off' => [],
                'charity_care' => [],
                'paid_in_full' => [],
            ];
            
            $currentStatus = $billingCycle->billing_status;
            if (!in_array($status, $allowedTransitions[$currentStatus] ?? [])) {
                return [
                    'success' => false,
                    'message' => 'Invalid status transition',
                    'error' => 'Cannot transition from ' . $currentStatus . ' to ' . $status,
                    'data' => []
                ];
            }
            
            // Special handling for dispute status
            if ($status === 'disputed') {
                $additionalData['is_disputed'] = true;
                $additionalData['dispute_opened_at'] = now();
                if (isset($additionalData['dispute_reason'])) {
                    $additionalData['dispute_reason'] = $additionalData['dispute_reason'];
                }
            }
            
            // Handle dispute resolution
            if ($currentStatus === 'disputed' && $status !== 'disputed') {
                $additionalData['is_disputed'] = false;
                $additionalData['dispute_resolved_at'] = now();
            }
            
            $updatedBillingCycle = $this->repository->updateStatus($billingCycle, $status, $additionalData);
            
            return [
                'success' => true,
                'message' => 'Billing status updated successfully',
                'data' => [
                    'billing_cycle' => $updatedBillingCycle
                ]
            ];
        } catch (\RuntimeException $e) {
            Log::error('Failed to update billing status', [
                'uuid' => $uuid,
                'status' => $status,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to update billing status',
                'error' => $e->getMessage(),
                'data' => []
            ];
        } catch (\Exception $e) {
            Log::error('Unexpected error updating billing status', [
                'uuid' => $uuid,
                'status' => $status,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to update billing status',
                'error' => 'An unexpected error occurred',
                'data' => []
            ];
        }
    }

    /**
     * Record payment
     *
     * @param string $uuid
     * @param array $paymentData
     * @return array
     */
    public function recordPayment(string $uuid, array $paymentData): array
    {
        try {
            $billingCycle = $this->repository->findByUuid($uuid);
            
            if (!$billingCycle) {
                return [
                    'success' => false,
                    'message' => 'Billing cycle not found',
                    'error' => 'The requested billing cycle does not exist',
                    'data' => []
                ];
            }
            
            // Validate payment data
            if (empty($paymentData['amount']) || !is_numeric($paymentData['amount'])) {
                return [
                    'success' => false,
                    'message' => 'Validation failed',
                    'error' => 'Payment amount is required and must be numeric',
                    'data' => []
                ];
            }
            
            if ($paymentData['amount'] <= 0) {
                return [
                    'success' => false,
                    'message' => 'Validation failed',
                    'error' => 'Payment amount must be greater than zero',
                    'data' => []
                ];
            }
            
            // Validate payment type
            $validPaymentTypes = ['insurance', 'patient'];
            $paymentType = $paymentData['payment_type'] ?? 'patient';
            if (!in_array($paymentType, $validPaymentTypes)) {
                return [
                    'success' => false,
                    'message' => 'Validation failed',
                    'error' => 'Invalid payment type',
                    'data' => []
                ];
            }
            
            // Check if billing cycle can accept payments
            $nonPayableStatuses = ['draft', 'pending_review', 'written_off', 'charity_care'];
            if (in_array($billingCycle->billing_status, $nonPayableStatuses)) {
                return [
                    'success' => false,
                    'message' => 'Cannot record payment',
                    'error' => 'Billing cycle with status ' . $billingCycle->billing_status . ' cannot accept payments',
                    'data' => []
                ];
            }
            
            // Check if payment exceeds outstanding amount
            $outstandingAmount = $billingCycle->net_amount - 
                ($billingCycle->insurance_payment_received + $billingCycle->patient_payment_received);
            
            if ($paymentData['amount'] > $outstandingAmount) {
                return [
                    'success' => false,
                    'message' => 'Payment exceeds outstanding amount',
                    'error' => 'Payment amount cannot exceed outstanding amount of ' . $outstandingAmount,
                    'data' => []
                ];
            }
            
            $updatedBillingCycle = $this->repository->recordPayment($billingCycle, $paymentData);
            
            return [
                'success' => true,
                'message' => 'Payment recorded successfully',
                'data' => [
                    'billing_cycle' => $updatedBillingCycle
                ]
            ];
        } catch (\RuntimeException $e) {
            Log::error('Failed to record payment', [
                'uuid' => $uuid,
                'payment_data' => $paymentData,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to record payment',
                'error' => $e->getMessage(),
                'data' => []
            ];
        } catch (\Exception $e) {
            Log::error('Unexpected error recording payment', [
                'uuid' => $uuid,
                'payment_data' => $paymentData,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to record payment',
                'error' => 'An unexpected error occurred',
                'data' => []
            ];
        }
    }

    /**
     * Get billing cycles by facility
     *
     * @param int $facilityId
     * @param array $filters
     * @return array
     */
    public function getBillingCyclesByFacility(int $facilityId, array $filters = []): array
    {
        try {
            $perPage = $filters['per_page'] ?? 20;
            $paginator = $this->repository->getByFacility($facilityId, $filters, $perPage);
            
            return [
                'success' => true,
                'message' => 'Billing cycles retrieved successfully',
                'data' => [
                    'billing_cycles' => $paginator->items(),
                    'pagination' => [
                        'total' => $paginator->total(),
                        'per_page' => $paginator->perPage(),
                        'current_page' => $paginator->currentPage(),
                        'last_page' => $paginator->lastPage(),
                        'from' => $paginator->firstItem(),
                        'to' => $paginator->lastItem(),
                    ]
                ]
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get billing cycles by facility', [
                'facility_id' => $facilityId,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to retrieve billing cycles',
                'error' => 'An internal server error occurred',
                'data' => []
            ];
        }
    }

    /**
     * Get billing cycles by patient
     *
     * @param int $patientId
     * @param array $filters
     * @return array
     */
    public function getBillingCyclesByPatient(int $patientId, array $filters = []): array
    {
        try {
            $perPage = $filters['per_page'] ?? 20;
            $paginator = $this->repository->getByPatient($patientId, $filters, $perPage);
            
            return [
                'success' => true,
                'message' => 'Billing cycles retrieved successfully',
                'data' => [
                    'billing_cycles' => $paginator->items(),
                    'pagination' => [
                        'total' => $paginator->total(),
                        'per_page' => $paginator->perPage(),
                        'current_page' => $paginator->currentPage(),
                        'last_page' => $paginator->lastPage(),
                        'from' => $paginator->firstItem(),
                        'to' => $paginator->lastItem(),
                    ]
                ]
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get billing cycles by patient', [
                'patient_id' => $patientId,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to retrieve billing cycles',
                'error' => 'An internal server error occurred',
                'data' => []
            ];
        }
    }

    /**
     * Get overdue billing cycles
     *
     * @param array $filters
     * @return array
     */
    public function getOverdueBillingCycles(array $filters = []): array
    {
        try {
            $perPage = $filters['per_page'] ?? 20;
            $paginator = $this->repository->getOverdue($filters, $perPage);
            
            return [
                'success' => true,
                'message' => 'Overdue billing cycles retrieved successfully',
                'data' => [
                    'billing_cycles' => $paginator->items(),
                    'pagination' => [
                        'total' => $paginator->total(),
                        'per_page' => $paginator->perPage(),
                        'current_page' => $paginator->currentPage(),
                        'last_page' => $paginator->lastPage(),
                        'from' => $paginator->firstItem(),
                        'to' => $paginator->lastItem(),
                    ]
                ]
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get overdue billing cycles', [
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to retrieve overdue billing cycles',
                'error' => 'An internal server error occurred',
                'data' => []
            ];
        }
    }

    /**
     * Get disputed billing cycles
     *
     * @param array $filters
     * @return array
     */
    public function getDisputedBillingCycles(array $filters = []): array
    {
        try {
            $perPage = $filters['per_page'] ?? 20;
            $paginator = $this->repository->getDisputed($filters, $perPage);
            
            return [
                'success' => true,
                'message' => 'Disputed billing cycles retrieved successfully',
                'data' => [
                    'billing_cycles' => $paginator->items(),
                    'pagination' => [
                        'total' => $paginator->total(),
                        'per_page' => $paginator->perPage(),
                        'current_page' => $paginator->currentPage(),
                        'last_page' => $paginator->lastPage(),
                        'from' => $paginator->firstItem(),
                        'to' => $paginator->lastItem(),
                    ]
                ]
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get disputed billing cycles', [
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to retrieve disputed billing cycles',
                'error' => 'An internal server error occurred',
                'data' => []
            ];
        }
    }

    /**
     * Get financial summary
     *
     * @param int $facilityId
     * @param array $dateRange
     * @return array
     */
    public function getFinancialSummary(int $facilityId, array $dateRange = []): array
    {
        try {
            $summary = $this->repository->getFinancialSummary($facilityId, $dateRange);
            
            return [
                'success' => true,
                'message' => 'Financial summary retrieved successfully',
                'data' => $summary
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get financial summary', [
                'facility_id' => $facilityId,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to retrieve financial summary',
                'error' => 'An internal server error occurred',
                'data' => []
            ];
        }
    }
}