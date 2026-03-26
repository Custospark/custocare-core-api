<?php

namespace App\Services\Billing;

use App\Models\Visit;
use App\Models\BillingCycle;
use App\Models\Patient;
use App\Services\Billing\Traits\BillingHelpers;
use App\Services\Billing\Validation\BillingValidation;
use App\Services\Billing\Processing\BillingProcessor;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Billing Service
 *
 * Main entry point for billing operations from controllers.
 * Handles business logic orchestration with atomic transactions
 * and comprehensive error handling.
 */
class BillingService
{
    use BillingHelpers;

    /**
     * @var BillingValidation
     */
    protected BillingValidation $validation;

    /**
     * @var BillingProcessor
     */
    protected BillingProcessor $processor;

    /**
     * BillingService constructor.
     *
     * @param BillingValidation $validation
     * @param BillingProcessor $processor
     */
    public function __construct(
        BillingValidation $validation,
        BillingProcessor $processor
    ) {
        $this->validation = $validation;
        $this->processor = $processor;
    }

    /**
     * Finalize billing and persist to database with atomic transaction
     *
     * @param array $data Validated billing data
     * @param int $facilityId Facility ID
     * @param int $staffId Staff ID performing the operation
     * @return array Success status and result data
     */
    public function saveBilling(array $data, int $facilityId, int $staffId): array
{
    try {
        // Step 1: Verify visit belongs to facility and patient
        $visitVerification = $this->verifyVisit(
            $data['visit_id'],
            $facilityId,
            $data['patient_id']
        );

        if (!$visitVerification['success']) {
            return $visitVerification;
        }

        $visit = $visitVerification['data'];

        // Step 2: Check if visit can be billed
        $billingEligibility = $this->canBeBilled($visit);
        
        if (!$billingEligibility['success']) {
            return $billingEligibility;
        }

        // Step 3: Check for existing billing cycle to prevent duplicate billing
        $existingBillingCheck = $this->checkExistingBilling($visit->id, $facilityId);
        
        if (!$existingBillingCheck['success']) {
            return $existingBillingCheck;
        }

        // Step 4: Validate inventory availability BEFORE transaction
        $inventoryValidation = $this->validation->validateInventoryAvailability($data['charge_items'], $staffId);
        
        if (!$inventoryValidation['success']) {
            return $inventoryValidation;
        }

        // Step 5: Calculate discount amount - Add null safety for discount
        $discountAmount = 0;
        if (isset($data['discount']) && is_array($data['discount'])) {
            $discountAmount = $this->calculateDiscountAmount(
                $data['discount']['type'] ?? 'fixed',
                $data['discount']['value'] ?? 0,
                $data['billing_data']['subtotal'] ?? 0
            );
        }
        // Step 6: Calculate payment split from actual payment methods
        $paymentMethods = $data['payment_methods'] ?? [];
        $totalPaid = $data['billing_data']['totalPaid'] ?? 0;

        $paymentSplit = $this->calculatePaymentSplit(
            $paymentMethods,
            $totalPaid
        );

        // Derive the authoritative payment/billing state from actual amounts.
        // This prevents "partially_paid" from being set when total paid is zero.
        $grandTotal = (float) ($data['billing_data']['grandTotal'] ?? 0);

        $paymentState = $this->determineBillingState(
            $grandTotal,
            (float) ($paymentSplit['total_paid'] ?? 0),
            $data['status'] ?? 'ready'
        );

        // Persist the authoritative values back into the working payload
        // so downstream processing uses one single source of truth.
        $data['payment_status'] = $paymentState['payment_status'];
        $data['resolved_billing_status'] = $paymentState['billing_status'];
        $data['resolved_ui_status'] = $paymentState['ui_status'];
        $data['resolved_total_paid'] = $paymentState['total_paid'];
        $data['resolved_balance'] = $paymentState['balance'];
        $data['resolved_is_fully_paid'] = $paymentState['is_fully_paid'];

        $data['billing_data']['totalPaid'] = $paymentState['total_paid'];
        $data['billing_data']['balance'] = $paymentState['balance'];

        // Step 7: Determine primary payment method
        $isPrimaryCash = false;
        $isInsuranceInvolved = false;

        // Step 8: Process billing within atomic transaction
        $result = $this->processor->processBillingTransaction(
            $data,
            $facilityId,
            $staffId,
            $discountAmount,
            $paymentSplit,
            $isPrimaryCash,
            $isInsuranceInvolved,
            $visit
        );

       // Use the authoritative resolved values
            $grandTotal = (float) ($data['billing_data']['grandTotal'] ?? 0);
            $validatedTotalPaid = (float) ($data['resolved_total_paid'] ?? 0);
            $balance = (float) ($data['resolved_balance'] ?? max(0, $grandTotal - $validatedTotalPaid));
            $isFullyPaid = (bool) ($data['resolved_is_fully_paid'] ?? ($balance <= 0));


        Log::info('Billing finalized successfully', [
            'billing_cycle_id' => $result['billing_cycle']->id ?? null,
            'visit_id' => $data['visit_id'] ?? null,
            'staff_id' => $staffId,
            'grand_total' => $grandTotal,
            'validated_total_paid' => $validatedTotalPaid,
            'balance' => $balance,
            'is_fully_paid' => $isFullyPaid,
        ]);

        // Return success response with accurate data
        return [
            'success' => true,
            'message' => $isFullyPaid 
                ? 'Payment successfully settled. Visit has been completed.' 
                : 'Billing saved successfully.',
            'data' => [
                'billing_cycle_id' => $result['billing_cycle']->id ?? null,
                'billing_cycle_uuid' => $result['billing_cycle']->billing_cycle_uuid ?? null,
                'receipt_number' => "REC-" . ($result['billing_cycle']->id ?? '0000'),
                'billing_status' => $result['billing_cycle']->billing_status ?? null,
                'net_amount' => $result['billing_cycle']->net_amount ?? 0,
                'total_paid' => $validatedTotalPaid,
                'balance' => $balance,
                'created_at' => isset($result['billing_cycle']->created_at) 
                    ? $result['billing_cycle']->created_at->toISOString() 
                    : now()->toISOString(),
                'line_items_count' => count($result['line_items'] ?? []),
            ],
        ];

    } catch (Throwable $e) {
        Log::error('Failed to finalize billing', [
            'error_message' => $e->getMessage(),
            'error_code' => $e->getCode(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
            'visit_id' => $data['visit_id'] ?? null,
            'patient_id' => $data['patient_id'] ?? null,
            'facility_id' => $facilityId,
            'staff_id' => $staffId,
        ]);

        return [
            'success' => false,
            'message' => 'An unexpected error occurred while submitting billing. Please try again or contact support if the issue persists.',
            'errors' => ['system' => ['Billing transaction failed. All changes have been rolled back.']],
            'error' => config('app.debug') ? $e->getMessage() : null,
        ];
    }
}
    /**
     * Get billing data for a visit
     *
     * @param int $visitId Visit ID
     * @param int $facilityId Facility ID
     * @return array Success status and billing data
     */
    public function getBillingByVisit(int $visitId, int $facilityId): array
    {
        try {
            // Verify visit exists and belongs to facility
            $visit = Visit::query()
                ->where('id', $visitId)
                ->where('facility_id', $facilityId)
                ->with(['patient.user'])
                ->first();

            if (!$visit) {
                return [
                    'success' => false,
                    'message' => 'Visit not found or does not belong to this facility.',
                    'errors' => ['visit_id' => ['Invalid visit for this facility.']],
                ];
            }

            // Get billing cycle for this visit
            $billingCycle = BillingCycle::query()
                ->where('visit_id', $visitId)
                ->where('facility_id', $facilityId)
                ->with(['lineItems'])
                ->orderByDesc('created_at')
                ->first();

            // If no billing cycle exists, return empty state
            if (!$billingCycle) {
                return [
                    'success' => true,
                    'message' => 'No billing record found for this visit.',
                    'data' => [
                        'has_billing' => false,
                        'visit_id' => $visitId,
                        'visit' => [
                            'id' => $visit->id,
                            'current_phase' => $visit->current_phase,
                            'payment_status' => $visit->payment_status,
                            'status' => $visit->status,
                        ],
                        'patient' => [
                            'id' => $visit->patient_id,
                            'name' => $visit->patient->user->display_name ?? 
                                     "{$visit->patient->user->first_name} {$visit->patient->user->last_name}",
                        ],
                    ],
                ];
            }

            // Parse and transform billing data
            $billingData = $this->transformBillingData($billingCycle, $visit);

            Log::info('Billing data retrieved successfully', [
                'visit_id' => $visitId,
                'billing_cycle_id' => $billingCycle->id,
            ]);

            return [
                'success' => true,
                'message' => 'Billing data retrieved successfully.',
                'data' => $billingData,
            ];

        } catch (Throwable $e) {
            Log::error('Failed to retrieve billing data', [
                'visit_id' => $visitId,
                'facility_id' => $facilityId,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'An unexpected error occurred while retrieving billing data. Please try again or contact support.',
                'errors' => ['system' => ['Failed to retrieve billing information.']],
                'error' => config('app.debug') ? $e->getMessage() : null,
            ];
        }
    }

    /**
 * Transform billing data for response
 * 
 * FIXED: Correct balance calculation and payment status determination
 *
 * @param BillingCycle $billingCycle Billing cycle model
 * @param Visit $visit Visit model
 * @return array Transformed billing data
 */
public function transformBillingData(BillingCycle $billingCycle, Visit $visit): array
{
    // ---- Helpers ------------------------------------------------------------

    $decodeJsonish = function ($value): array {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value) && trim($value) !== '') {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : [];
        }

        return [];
    };

    $toIso = function ($date): ?string {
        if (!$date) {
            return null;
        }

        if (is_object($date)) {
            if (method_exists($date, 'toIso8601String')) {
                return $date->toIso8601String();
            }
            if (method_exists($date, 'format')) {
                return $date->format('c');
            }
        }

        if (is_string($date) && trim($date) !== '') {
            $ts = strtotime($date);
            return $ts ? date(DATE_ATOM, $ts) : null;
        }

        return null;
    };

    $toEpochMs = function ($date): int {
        if (!$date) {
            return 0;
        }

        if (is_object($date)) {
            if (method_exists($date, 'getTimestamp')) {
                return (int) $date->getTimestamp() * 1000;
            }
            if (method_exists($date, 'timestamp')) {
                try {
                    $ts = $date->timestamp;
                    return is_numeric($ts) ? ((int) $ts * 1000) : 0;
                } catch (Throwable $e) {
                    return 0;
                }
            }
        }

        if (is_string($date) && trim($date) !== '') {
            $ts = strtotime($date);
            return $ts ? ((int) $ts * 1000) : 0;
        }

        return 0;
    };

    // ---- Parse cycle-level blobs -------------------------------------------

    $metadata = $decodeJsonish($billingCycle->metadata ?? null);
    $paymentMethods = is_array($metadata['payment_methods'] ?? null) ? $metadata['payment_methods'] : [];
    $additionalNotes = (string) ($metadata['additional_notes'] ?? '');

    $taxes = $decodeJsonish($billingCycle->tax_details ?? null);

    // Discount normalization
    $discountApplied = (float) ($billingCycle->discount_applied ?? 0);
    $discountReason  = $billingCycle->discount_reason ?? null;

    $discount = [
        'type'   => $discountApplied > 0 ? ($discountReason ? 'fixed' : 'percentage') : 'percentage',
        'value'  => $discountApplied,
        'reason' => $discountReason,
    ];

    // ---- Line items -> charge_items ----------------------------------------

    $lineItems = $billingCycle->lineItems ?? collect();

    $chargeItems = $lineItems->map(function ($lineItem) use ($decodeJsonish) {
        $serviceSnapshot = $decodeJsonish($lineItem->service_version_snapshot ?? null);
        $lineMetadata    = $decodeJsonish($lineItem->metadata ?? null);

        $serviceCode = (string) ($lineItem->service_code ?? '');
        $serviceKey  = (string) ($lineMetadata['service_key'] ?? ($serviceCode !== '' ? "key::{$serviceCode}" : "key::unknown"));

        $uuid = $lineItem->line_item_uuid ?? null;
        $chargeId = $uuid ? "charge::{$uuid}" : "charge::{$lineItem->id}";

        return [
            'id' => $chargeId,
            'service_key' => $serviceKey,
            'service' => [
                'id' => $lineItem->service_version_id ?? ($serviceSnapshot['id'] ?? null),
                'code' => $serviceCode,
                'name' => (string) ($lineItem->service_description ?? ($serviceSnapshot['name'] ?? '')),
                'unitPrice' => (float) ($lineItem->unit_price_at_time ?? 0),
                'category' => (string) ($lineMetadata['category'] ?? ($serviceSnapshot['category'] ?? 'General')),
            ],
            'quantity' => (int) ($lineItem->quantity ?? 0),
            'totalAmount' => (float) ($lineItem->line_total_amount ?? 0),
        ];
    })->values()->toArray();

    // ---- Totals summary -----------------------------------------------------

    // Fallback to legacy fields for backward compatibility.
    $totalCharged = (float) ($billingCycle->subtotal_amount ?? $billingCycle->total_amount_charged ?? 0);
    $totalTax     = (float) ($billingCycle->total_tax_amount ?? 0);
    $grandTotal   = (float) ($billingCycle->grand_total_amount ?? $billingCycle->net_amount ?? 0);

    $totalPaid = (float) (
        $billingCycle->total_paid_amount
        ?? (
            (float) ($billingCycle->patient_payment_received ?? 0)
            + (float) ($billingCycle->insurance_payment_received ?? 0)
        )
    );

    $balance = (float) (
        $billingCycle->balance_amount
        ?? max(0, $grandTotal - $totalPaid)
    );

    $isPaid = abs($balance) < 0.01;

    $billingData = [
        'subtotal'       => $totalCharged,
        'discountAmount' => $discountApplied,
        'taxableAmount'  => (float) ($billingCycle->taxable_amount ?? max(0, $totalCharged - $discountApplied)),
        'taxes'          => $taxes,
        'taxTotal'       => $totalTax,
        'grandTotal'     => $grandTotal,
        'totalPaid'      => $totalPaid,
        'balance'        => $balance,
        'isPaid'         => $isPaid,
    ];


    // ---- Status mapping -----------------------------------------------------

    $uiStatus = $this->mapBillingStatusToUI($billingCycle->billing_status);

    // ---- Patient name safe fallback ----------------------------------------

    $patientUser = $visit->patient->user ?? null;
    $patientName = null;

    if ($patientUser) {
        $patientName = $patientUser->display_name
            ?? trim((string) ($patientUser->first_name ?? '') . ' ' . (string) ($patientUser->last_name ?? ''));
    }

    // ---- Attending Staff Information ----------------------------------------
    // Get attending staff details from the billing cycle's created_by_staff_id
    
    $attendingStaffName = null;
    $attendingStaffRole = null;
    $attendingStaffDisplay = null;

    if ($billingCycle->created_by_staff_id) {
        // Get staff record
        $staff = \App\Models\Staff::where('id', $billingCycle->created_by_staff_id)->first();
        
        if ($staff) {
            // Get user display name from users table via user_id
            $user = \App\Models\User::where('id', $staff->user_id)->first();
            if ($user) {
                $attendingStaffName = $user->display_name 
                    ?? trim((string) ($user->first_name ?? '') . ' ' . (string) ($user->last_name ?? ''));
            }
            
            // Get staff role from facility_staff_roles table
            // Assuming there's a relationship or we can query directly
            $facilityStaffRole = \App\Models\FacilityStaffRole::where('staff_id', $staff->id)
                ->where('facility_id', $visit->facility_id) // Use the visit's facility
                ->first();
                
            if ($facilityStaffRole) {
                $attendingStaffRole = $facilityStaffRole->role_code;
                
                // Format the role for display (replace underscores and hyphens with spaces, uppercase)
                $formattedRole = str_replace(['_', '-'], ' ', $attendingStaffRole);
                $formattedRole = ucwords(strtolower($formattedRole));
                
                $attendingStaffDisplay = "{$attendingStaffName} ({$formattedRole})";
            } else {
                $attendingStaffDisplay = $attendingStaffName;
            }
        }
    }

    // ---- Response -----------------------------------------------------------

    return [
        'has_billing' => true,

        // Visit & patient info
        'visit_id'      => $visit->id,
        'visit_uuid'    => $visit->visit_uuid,
        'patient_id'    => $visit->patient_id,
        'patient_number'    => Patient::where('id',$visit->patient_id)->value('patient_uuid'),
        'patient_name'  => $patientName ?: 'Unknown',

        // Billing cycle info
        'billing_cycle_id'   => $billingCycle->id,
        'billing_status'   => $billingCycle->billing_status,
        'billing_cycle_uuid' => $billingCycle->billing_cycle_uuid,
        'receipt_number'     => "REC-{$billingCycle->id}",
        
        // Attending Staff Information - NEW FIELDS
        'attending_staff_id' => $billingCycle->created_by_staff_id,
        'attending_staff_name' => $attendingStaffName,
        'attending_staff_role' => $attendingStaffRole,
        'attending_staff_display' => $attendingStaffDisplay, // Pre-formatted for convenience

        // Redux-shaped billing state
        'charge_items'      => $chargeItems,
        'discount'          => $discount,
        'taxes'             => $taxes,
        'payment_methods'   => $paymentMethods,
        'additional_notes'  => $additionalNotes,
        'payment_status'    => Visit::where('id',$visit->id)->value('payment_status'),

        // Calculated - FIXED: Using accurate values
        'billing_data' => $billingData,

        // Timestamps (safe ISO)
        'billed_at'  => $toIso($billingCycle->billed_at ?? null),
        'created_at' => $toIso($billingCycle->created_at ?? null),
        'updated_at' => $toIso($billingCycle->updated_at ?? null),

        // Metadata (safe epoch ms)
        'last_updated'   => $toEpochMs($billingCycle->updated_at ?? null),
        'is_dirty'       => false,
        'is_processing'  => false,
    ];
}

        /**
     * Get all billing data for a facility with pagination
     * Returns data in the same format as getBillingByVisit but as a collection
     *
     * @param int $facilityId Facility ID
     * @param array $filters Filter criteria (status, date_from, date_to, payment_method, min_amount, max_amount)
     * @param string $search Search term (patient name, visit ID, receipt number)
     * @param int $perPage Number of items per page
     * @param int $page Page number
     * @return array Success status and paginated billing data
     */
    public function getBillingByFacility(
        int $facilityId,
        array $filters = [],
        string $search = '',
        int $perPage = 15,
        int $page = 1
    ): array {
        try {
            // Build the base query with eager loading
            $query = BillingCycle::query()
                ->where('facility_id', $facilityId)
                ->withTrashed() // ← include soft-deleted (voided) records
                ->with([
                    'visit' => fn($q) => $q->withTrashed(),
                    'visit.patient.user',
                    'lineItems' => fn($q) => $q->withTrashed(),
                ])
                ->orderByDesc('created_at');

            // Apply status filter
            if (!empty($filters['status'])) {
                if (is_array($filters['status'])) {
                    $query->whereIn('billing_status', $filters['status']);
                } else {
                    $query->where('billing_status', $filters['status']);
                }
            }

            // Apply date range filter
            if (!empty($filters['date_from'])) {
                $query->whereDate('created_at', '>=', $filters['date_from']);
            }
            if (!empty($filters['date_to'])) {
                $query->whereDate('created_at', '<=', $filters['date_to']);
            }

            // Apply payment method filter (searches in metadata)
            if (!empty($filters['payment_method'])) {
                $query->where('metadata', 'like', '%' . $filters['payment_method'] . '%');
            }

            // Apply amount filters
            if (!empty($filters['min_amount'])) {
                $query->where('net_amount', '>=', (float) $filters['min_amount']);
            }
            if (!empty($filters['max_amount'])) {
                $query->where('net_amount', '<=', (float) $filters['max_amount']);
            }

            // Apply search across multiple fields
            if (!empty($search)) {
                $query->where(function ($q) use ($search) {
                    // Search by billing cycle UUID
                    $q->where('billing_cycle_uuid', 'LIKE', "%{$search}%")
                    // Search by ID (receipt number)
                    ->orWhere('id', 'LIKE', "%{$search}%")
                    // Search by visit ID
                    ->orWhere('visit_id', 'LIKE', "%{$search}%")
                    // Search in patient name through visit relationship
                    ->orWhereHas('visit.patient.user', function ($userQuery) use ($search) {
                        $userQuery->where('first_name', 'LIKE', "%{$search}%")
                                    ->orWhere('last_name', 'LIKE', "%{$search}%")
                                    ->orWhere('display_name', 'LIKE', "%{$search}%");
                    });
                });
            }

            // Get total count for pagination
            $total = $query->count();

            // Apply pagination
            $billingCycles = $query->forPage($page, $perPage)->get();

            // Transform each billing cycle using the existing transform method
            $transformedData = $billingCycles->map(function ($billingCycle) {
                $visit = $billingCycle->visit;
                
                // Skip if visit is missing (shouldn't happen due to FK constraints)
                if (!$visit) {
                    Log::warning('Billing cycle missing associated visit', [
                        'billing_cycle_id' => $billingCycle->id,
                    ]);
                    return null;
                }

                // Use the existing transform method to maintain consistent format
                return $this->transformBillingData($billingCycle, $visit);
            })->filter()->values()->toArray();

            // Calculate pagination metadata
            $totalPages = ceil($total / $perPage);
            $from = ($page - 1) * $perPage + 1;
            $to = min($page * $perPage, $total);

            Log::info('Facility billing data retrieved successfully', [
                'facility_id' => $facilityId,
                'total_records' => $total,
                'filters_applied' => array_keys(array_filter($filters)),
                'search_term' => $search ?: 'none',
            ]);

            return [
                'success' => true,
                'message' => 'Billing data retrieved successfully.',
                'data' => [
                    'items' => $transformedData,
                    'pagination' => [
                        'current_page' => (int) $page,
                        'per_page' => (int) $perPage,
                        'total_items' => $total,
                        'total_pages' => $totalPages,
                        'from' => $total > 0 ? $from : null,
                        'to' => $total > 0 ? $to : null,
                        'has_previous' => $page > 1,
                        'has_next' => $page < $totalPages,
                    ],
                    'filters_applied' => array_filter($filters),
                    'search_term' => $search ?: null,
                ],
            ];

        } catch (Throwable $e) {
            Log::error('Failed to retrieve facility billing data', [
                'facility_id' => $facilityId,
                'filters' => $filters,
                'search' => $search,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'An unexpected error occurred while retrieving billing data. Please try again or contact support.',
                'errors' => ['system' => ['Failed to retrieve billing information.']],
                'error' => config('app.debug') ? $e->getMessage() : null,
            ];
        }
    }

    /**
     * Get billing statistics for a facility
     *
     * @param int $facilityId Facility ID
     * @param array $filters Optional date range filters
     * @return array Success status and statistics
     */
    public function getBillingStatistics(int $facilityId, array $filters = []): array
    {
        try {
            $query = BillingCycle::query()->where('facility_id', $facilityId);

            // Apply date range filters if provided
            if (!empty($filters['date_from'])) {
                $query->whereDate('created_at', '>=', $filters['date_from']);
            }
            if (!empty($filters['date_to'])) {
                $query->whereDate('created_at', '<=', $filters['date_to']);
            }

            // Get totals by status
            $statusTotals = $query->selectRaw('
                billing_status,
                COUNT(*) as count,
                SUM(net_amount) as total_amount,
                SUM(patient_payment_received) as total_patient_paid,
                SUM(insurance_payment_received) as total_insurance_paid
            ')->groupBy('billing_status')->get();

            // Calculate overall statistics
            $statistics = [
                'total_billing_cycles' => $query->count(),
                'total_revenue' => $query->sum('net_amount'),
                'total_patient_payments' => $query->sum('patient_payment_received'),
                'total_insurance_payments' => $query->sum('insurance_payment_received'),
                'total_discounts_applied' => $query->sum('discount_applied'),
                'average_cycle_amount' => $query->avg('net_amount') ?? 0,
                'by_status' => [],
            ];

            // Transform status data
            foreach ($statusTotals as $statusTotal) {
                $uiStatus = $this->mapBillingStatusToUI($statusTotal->billing_status);
                
                if (!isset($statistics['by_status'][$uiStatus])) {
                    $statistics['by_status'][$uiStatus] = [
                        'count' => 0,
                        'total_amount' => 0,
                        'breakdown' => [],
                    ];
                }

                $statistics['by_status'][$uiStatus]['count'] += $statusTotal->count;
                $statistics['by_status'][$uiStatus]['total_amount'] += $statusTotal->total_amount;
                $statistics['by_status'][$uiStatus]['breakdown'][$statusTotal->billing_status] = [
                    'count' => $statusTotal->count,
                    'total_amount' => $statusTotal->total_amount,
                    'patient_paid' => $statusTotal->total_patient_paid,
                    'insurance_paid' => $statusTotal->total_insurance_paid,
                ];
            }

            return [
                'success' => true,
                'message' => 'Billing statistics retrieved successfully.',
                'data' => $statistics,
            ];

        } catch (Throwable $e) {
            Log::error('Failed to retrieve billing statistics', [
                'facility_id' => $facilityId,
                'filters' => $filters,
                'error_message' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to retrieve billing statistics.',
                'errors' => ['system' => [$e->getMessage()]],
            ];
        }
    }
}