<?php

namespace App\Services\Billing;

use App\Models\BillingCycle;
use App\Models\InvoiceLineItem;
use App\Models\Patient;
use App\Models\Staff;
use App\Models\User;
use App\Models\Visit;
use App\Services\Billing\Processing\BillingProcessor;
use App\Services\Billing\Traits\BillingHelpers;
use App\Services\Billing\Validation\BillingValidation;
use Illuminate\Support\Facades\Log;
use Throwable;

class BillingService
{
    use BillingHelpers;

    protected BillingValidation $validation;
    protected BillingProcessor $processor;

    public function __construct(
        BillingValidation $validation,
        BillingProcessor $processor
    ) {
        $this->validation = $validation;
        $this->processor = $processor;
    }

    public function saveBilling(array $data, int $facilityId, int $staffId): array
    {
        try {
            $visitVerification = $this->verifyVisit(
                $data['visit_id'],
                $facilityId,
                $data['patient_id']
            );

            if (!$visitVerification['success']) {
                return $visitVerification;
            }

            $visit = $visitVerification['data'];

            $data['charge_items'] = $this->normalizeChargeItems($data['charge_items'] ?? []);
            $data['payment_methods'] = $this->normalizePaymentMethods($data['payment_methods'] ?? []);
            $data['discount'] = $this->normalizeDiscount($data['discount'] ?? []);
            $data['taxes'] = $this->normalizeTaxDefinitions($data['taxes'] ?? []);

            $data['submission_fingerprint'] = $this->buildBillingSubmissionFingerprint($data, $facilityId);

            $replayedCycle = $this->findReplaySubmission(
                (int) $data['visit_id'],
                $facilityId,
                $data['submission_fingerprint']
            );

            if ($replayedCycle) {
                Log::info('Idempotent replay detected for billing save', [
                    'visit_id' => $data['visit_id'],
                    'billing_cycle_id' => $replayedCycle->id,
                ]);

                return $this->buildReplaySuccessResponse($replayedCycle);
            }

            $billingEligibility = $this->canBeBilled($visit);
            if (!$billingEligibility['success']) {
                return $billingEligibility;
            }

            $existingBillingCheck = $this->checkExistingBilling($visit->id, $facilityId);
            if (!$existingBillingCheck['success']) {
                return $existingBillingCheck;
            }

            /** @var BillingCycle|null $existingEditableBillingCycle */
            $existingEditableBillingCycle = $existingBillingCheck['data'] ?? null;

            $authoritativeBillingData = $this->buildAuthoritativeBillingDataForSave(
                $data['charge_items'],
                $existingEditableBillingCycle,
                $data['discount'] ?? [],
                $data['taxes'] ?? []
            );

            if ($this->billingDataMismatch($data['billing_data'] ?? [], $authoritativeBillingData)) {
                Log::warning('Frontend billing_data mismatch detected; backend authoritative totals applied.', [
                    'visit_id' => $data['visit_id'] ?? null,
                    'provided_billing_data' => $data['billing_data'] ?? [],
                    'computed_billing_data' => $authoritativeBillingData,
                ]);
            }

            $data['billing_data'] = array_merge($data['billing_data'] ?? [], $authoritativeBillingData);
            $data['taxes'] = $authoritativeBillingData['taxes'];

            $inventoryValidation = $this->validation->validateInventoryAvailability(
                $data['charge_items'],
                $staffId
            );

            if (!$inventoryValidation['success']) {
                return $inventoryValidation;
            }

            $paymentSplit = $this->calculatePaymentSplit(
                $data['payment_methods'] ?? [],
                (float) ($data['billing_data']['totalPaid'] ?? 0)
            );

            $paymentState = $this->determineBillingState(
                (float) ($data['billing_data']['grandTotal'] ?? 0),
                (float) ($paymentSplit['total_paid'] ?? 0),
                (string) ($data['status'] ?? 'ready')
            );

            $data['payment_status'] = $paymentState['payment_status'];
            $data['resolved_billing_status'] = $paymentState['billing_status'];
            $data['resolved_ui_status'] = $paymentState['ui_status'];
            $data['resolved_total_paid'] = $paymentState['total_paid'];
            $data['resolved_balance'] = $paymentState['balance'];
            $data['resolved_is_fully_paid'] = $paymentState['is_fully_paid'];

            $data['billing_data']['totalPaid'] = $paymentState['total_paid'];
            $data['billing_data']['balance'] = $paymentState['balance'];

            $discountAmount = (float) ($data['billing_data']['discountAmount'] ?? 0);

            $largestPaymentMethod = collect($data['payment_methods'] ?? [])
                ->sortByDesc('amount')
                ->first();

            $isPrimaryCash = ($largestPaymentMethod['type'] ?? null) === 'cash';
            $isInsuranceInvolved = $this->isInsuranceInvolved($data['payment_methods'] ?? []);

            $result = $this->processor->processBillingTransaction(
                $data,
                $facilityId,
                $staffId,
                $discountAmount,
                $paymentSplit,
                $isPrimaryCash,
                $isInsuranceInvolved,
                $visit,
                $existingEditableBillingCycle
            );

            $billingCycle = $result['billing_cycle']->fresh();

            $grandTotal = round((float) ($billingCycle->grand_total_amount ?? $billingCycle->net_amount ?? 0), 2);
            $validatedTotalPaid = round((float) ($billingCycle->total_paid_amount ?? 0), 2);
            $balance = round((float) ($billingCycle->balance_amount ?? max(0, $grandTotal - $validatedTotalPaid)), 2);
            $isFullyPaid = abs($balance) < 0.01;

            $wasExistingCycleUpdated = !empty($existingEditableBillingCycle);

            Log::info('Billing finalized successfully', [
                'billing_cycle_id' => $billingCycle->id ?? null,
                'visit_id' => $data['visit_id'] ?? null,
                'staff_id' => $staffId,
                'grand_total' => $grandTotal,
                'validated_total_paid' => $validatedTotalPaid,
                'balance' => $balance,
                'is_fully_paid' => $isFullyPaid,
                'was_existing_cycle_updated' => $wasExistingCycleUpdated,
            ]);

            return [
                'success' => true,
                'message' => $isFullyPaid
                    ? 'Payment successfully settled. Visit has been completed.'
                    : ($wasExistingCycleUpdated
                        ? 'Existing billing cycle updated successfully. New values were added to the current bill.'
                        : 'Billing saved successfully.'),
                'data' => [
                    'billing_cycle_id' => $billingCycle->id ?? null,
                    'billing_cycle_uuid' => $billingCycle->billing_cycle_uuid ?? null,
                    'receipt_number' => 'REC-' . ($billingCycle->id ?? '0000'),
                    'billing_status' => $billingCycle->billing_status ?? null,
                    'net_amount' => $billingCycle->net_amount ?? 0,
                    'total_paid' => $billingCycle->total_paid_amount ?? $validatedTotalPaid,
                    'balance' => $billingCycle->balance_amount ?? $balance,
                    'created_at' => isset($billingCycle->created_at)
                        ? $billingCycle->created_at->toISOString()
                        : now()->toISOString(),
                    'line_items_count' => count($result['line_items'] ?? []),
                    'was_existing_cycle_updated' => $wasExistingCycleUpdated,
                    'idempotent_replay' => false,
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

    protected function buildReplaySuccessResponse(BillingCycle $billingCycle): array
    {
        $billingCycle = $billingCycle->fresh();

        return [
            'success' => true,
            'message' => 'Duplicate billing submission ignored. Existing billing record returned.',
            'data' => [
                'billing_cycle_id' => $billingCycle->id ?? null,
                'billing_cycle_uuid' => $billingCycle->billing_cycle_uuid ?? null,
                'receipt_number' => 'REC-' . ($billingCycle->id ?? '0000'),
                'billing_status' => $billingCycle->billing_status ?? null,
                'net_amount' => $billingCycle->net_amount ?? 0,
                'total_paid' => $billingCycle->total_paid_amount ?? 0,
                'balance' => $billingCycle->balance_amount ?? 0,
                'created_at' => isset($billingCycle->created_at)
                    ? $billingCycle->created_at->toISOString()
                    : now()->toISOString(),
                'line_items_count' => $billingCycle->lineItems()->count(),
                'was_existing_cycle_updated' => true,
                'idempotent_replay' => true,
            ],
        ];
    }

    public function getBillingByVisit(int $visitId, int $facilityId, ?int $currentStaffId = null): array
    {
        try {
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

            $billingCycle = BillingCycle::query()
                ->where('visit_id', $visitId)
                ->where('facility_id', $facilityId)
                ->with(['lineItems'])
                ->orderByRaw("CASE WHEN billing_status IN ('paid_in_full', 'partially_paid') THEN 0 ELSE 1 END")
                ->orderByDesc('created_at')
                ->first();

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

            $billingData = $this->transformBillingData($billingCycle, $visit, $currentStaffId);

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
            ]);

            return [
                'success' => false,
                'message' => 'An unexpected error occurred while retrieving billing data. Please try again or contact support.',
                'errors' => ['system' => ['Failed to retrieve billing information.']],
                'error' => config('app.debug') ? $e->getMessage() : null,
            ];
        }
    }

    public function transformBillingData(BillingCycle $billingCycle, Visit $visit, ?int $currentStaffId = null): array
    {
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
            }

            if (is_string($date) && trim($date) !== '') {
                $ts = strtotime($date);
                return $ts ? ((int) $ts * 1000) : 0;
            }

            return 0;
        };

        $resolveStaffDisplayName = function (?int $staffId): ?string {
            if (!$staffId) {
                return null;
            }

            $staff = Staff::query()->find($staffId);
            if (!$staff) {
                return null;
            }

            $user = User::query()->find($staff->user_id);
            if (!$user) {
                return null;
            }

            return trim(($user->last_name ?? '') . ' ' . ($user->first_name ?? '')) ?: $user->display_name;
        };

        $isActiveLineItem = function ($lineItem): bool {
            $quantity = (float) ($lineItem->quantity ?? 0);
            if ($quantity <= 0) {
                return false;
            }

            $status = strtolower((string) ($lineItem->line_item_status ?? ''));
            return !in_array($status, [
                'removed',
                'voided',
                'cancelled',
                'deleted',
                // 'adjusted',
            ], true);
        };

        $transformLineItem = function ($lineItem) use (
            $decodeJsonish,
            $currentStaffId,
            $resolveStaffDisplayName,
            $billingCycle
        ) {
            $serviceSnapshot = $decodeJsonish($lineItem->service_version_snapshot ?? null);
            $lineMetadata = $decodeJsonish($lineItem->metadata ?? null);

            $serviceCode = (string) ($lineItem->service_code ?? '');
            $serviceKey = (string) ($lineMetadata['service_key'] ?? ($serviceCode !== '' ? "key::{$serviceCode}" : "key::unknown"));

            $uuid = $lineItem->line_item_uuid ?? null;
            $chargeId = $uuid ? "backend-charge::{$uuid}" : "backend-charge::{$lineItem->id}";

            $enteredByStaffId = $lineMetadata['originated_by_staff_id']
                ?? $lineItem->created_by_staff_id
                ?? $billingCycle->created_by_staff_id;

            $enteredByStaffName = $resolveStaffDisplayName($enteredByStaffId);
            $permissions = $this->buildLineItemEditPolicy(
                $enteredByStaffId ? (int) $enteredByStaffId : null,
                $currentStaffId
            );

            $quantity = (float) ($lineItem->quantity ?? 0);

            return [
                'id' => $chargeId,
                'source' => 'backend',
                'persisted' => true,
                'line_item_id' => $lineItem->id,
                'line_item_uuid' => $lineItem->line_item_uuid,
                'billing_cycle_id' => $billingCycle->id,
                'service_key' => $serviceKey,
                'serviceKey' => $serviceKey,
                'service' => [
                    'id' => $lineItem->service_version_id ?? ($serviceSnapshot['id'] ?? null),
                    'code' => $serviceCode,
                    'name' => (string) ($lineItem->service_description ?? ($serviceSnapshot['name'] ?? '')),
                    'unitPrice' => (float) ($lineItem->unit_price_at_time ?? 0),
                    'category' => (string) ($lineMetadata['category'] ?? ($serviceSnapshot['category'] ?? 'General')),
                ],
                'quantity' => $quantity,
                'totalAmount' => (float) ($lineItem->line_total_amount ?? 0),
                'line_item_status' => (string) ($lineItem->line_item_status ?? 'pending'),
                'entered_by_staff_id' => $enteredByStaffId,
                'entered_by_staff_name' => $enteredByStaffName,
                'permissions' => $permissions,
                'audit' => [
                    'originated_by_staff_id' => $enteredByStaffId,
                    'last_adjusted_by_staff_id' => $lineMetadata['last_adjusted_by_staff_id'] ?? null,
                    'last_appended_by_staff_id' => $lineMetadata['last_appended_by_staff_id'] ?? null,
                    'last_adjusted_at' => $lineMetadata['last_adjusted_at'] ?? null,
                    'adjustment_history' => $lineMetadata['adjustment_history'] ?? [],
                ],
            ];
        };

        $metadata = $decodeJsonish($billingCycle->metadata ?? null);
        $paymentMethods = is_array($metadata['payment_methods'] ?? null) ? $metadata['payment_methods'] : [];
        $additionalNotes = (string) ($metadata['additional_notes'] ?? '');

        $taxes = $decodeJsonish($billingCycle->tax_details ?? null);
        $storedDiscountRule = is_array($metadata['discount_rule'] ?? null)
            ? $metadata['discount_rule']
            : [];

        $discountApplied = round((float) ($billingCycle->discount_applied ?? 0), 2);

        $discount = [
            'type' => in_array(($storedDiscountRule['type'] ?? null), ['percentage', 'fixed'], true)
                ? $storedDiscountRule['type']
                : 'fixed',
            'value' => round((float) ($storedDiscountRule['value'] ?? $discountApplied), 2),
            'reason' => $storedDiscountRule['reason'] ?? $billingCycle->discount_reason,
        ];

        $lineItems = $billingCycle->lineItems ?? collect();
        $activeLineItems = $lineItems->filter($isActiveLineItem);
        $chargeItems = $activeLineItems->map($transformLineItem)->values()->toArray();

        $derivedSubtotal = round((float) $activeLineItems->sum(function ($lineItem) {
            return (float) ($lineItem->line_total_amount ?? 0);
        }), 2);

        $derivedDiscount = round((float) $activeLineItems->sum(function ($lineItem) {
            return (float) ($lineItem->discount_amount ?? 0);
        }), 2);

        $derivedTaxableAmount = round(max(0, $derivedSubtotal - $derivedDiscount), 2);

        $derivedTaxes = collect($taxes)->map(function ($tax) use ($derivedTaxableAmount) {
            $rate = round((float) ($tax['rate'] ?? 0), 2);

            return [
                'name' => (string) ($tax['name'] ?? 'Tax'),
                'rate' => $rate,
                'amount' => round($derivedTaxableAmount * ($rate / 100), 2),
            ];
        })->values()->toArray();

        $derivedTaxTotal = round((float) collect($derivedTaxes)->sum('amount'), 2);
        $derivedGrandTotal = round($derivedTaxableAmount + $derivedTaxTotal, 2);

        $totalCharged = round((float) ($billingCycle->subtotal_amount ?? $derivedSubtotal), 2);
        $discountStored = round((float) ($billingCycle->discount_applied ?? $derivedDiscount), 2);
        $totalTax = round((float) ($billingCycle->total_tax_amount ?? $derivedTaxTotal), 2);
        $grandTotal = round((float) ($billingCycle->grand_total_amount ?? $billingCycle->net_amount ?? $derivedGrandTotal), 2);

        if (abs($totalCharged - $derivedSubtotal) > 0.01) {
            $totalCharged = $derivedSubtotal;
        }
        if (abs($discountStored - $derivedDiscount) > 0.01) {
            $discountStored = $derivedDiscount;
        }
        if (abs($totalTax - $derivedTaxTotal) > 0.01) {
            $totalTax = $derivedTaxTotal;
        }
        if (abs($grandTotal - $derivedGrandTotal) > 0.01) {
            $grandTotal = $derivedGrandTotal;
        }
        $totalPaid = round((float) (
            $billingCycle->total_paid_amount
            ?? (
                (float) ($billingCycle->patient_payment_received ?? 0)
                + (float) ($billingCycle->insurance_payment_received ?? 0)
            )
        ), 2);

        $balance = round((float) (
            $billingCycle->balance_amount
            ?? max(0, $grandTotal - $totalPaid)
        ), 2);

        $isPaid = abs($balance) < 0.01;

        $billingData = [
            'subtotal'       => $totalCharged,
            'discountAmount' => $discountStored,
            'taxableAmount'  => round((float) ($billingCycle->taxable_amount ?? max(0, $totalCharged - $discountStored)), 2),
            'taxes'          => !empty($taxes) ? $derivedTaxes : $derivedTaxes,
            'taxTotal'       => $totalTax,
            'grandTotal'     => $grandTotal,
            'totalPaid'      => $totalPaid,
            'balance'        => $balance,
            'isPaid'         => $isPaid,
        ];

        $uiStatus = $this->mapBillingStatusToUI((string) $billingCycle->billing_status);

        $patientUser = $visit->patient->user ?? null;
        $patientName = null;

        if ($patientUser) {
            $patientName = $patientUser->display_name
                ?? trim((string) ($patientUser->first_name ?? '') . ' ' . (string) ($patientUser->last_name ?? ''));
        }

        $attendingStaffName = null;
        $attendingStaffRole = null;
        $attendingStaffDisplay = null;

        if ($billingCycle->created_by_staff_id) {
            $staff = Staff::query()->find($billingCycle->created_by_staff_id);

            if ($staff) {
                $user = User::query()->find($staff->user_id);

                if ($user) {
                    $attendingStaffName = $user->display_name
                        ?? trim((string) ($user->first_name ?? '') . ' ' . (string) ($user->last_name ?? ''));
                }

                $facilityStaffRole = \App\Models\FacilityStaffRole::query()
                    ->where('staff_id', $staff->id)
                    ->where('facility_id', $visit->facility_id)
                    ->first();

                if ($facilityStaffRole) {
                    $attendingStaffRole = $facilityStaffRole->role_code;
                    $formattedRole = ucwords(strtolower(str_replace(['_', '-'], ' ', (string) $attendingStaffRole)));
                    $attendingStaffDisplay = $attendingStaffName
                        ? "{$attendingStaffName} ({$formattedRole})"
                        : $formattedRole;
                } else {
                    $attendingStaffDisplay = $attendingStaffName;
                }
            }
        }

        return [
            'has_billing' => true,

            'visit_id' => $visit->id,
            'visit_uuid' => $visit->visit_uuid,
            'patient_id' => $visit->patient_id,
            'patient_number' => Patient::query()->where('id', $visit->patient_id)->value('patient_uuid'),
            'patient_name' => $patientName ?: 'Unknown',

            'billing_cycle_id' => $billingCycle->id,
            'billing_status' => $billingCycle->billing_status,
            'billing_cycle_uuid' => $billingCycle->billing_cycle_uuid,
            'receipt_number' => "REC-{$billingCycle->id}",

            'attending_staff_id' => $billingCycle->created_by_staff_id,
            'attending_staff_name' => $attendingStaffName,
            'attending_staff_role' => $attendingStaffRole,
            'attending_staff_display' => $attendingStaffDisplay,

            'charge_items' => $chargeItems,
            'discount' => $discount,
            'taxes' => $derivedTaxes,
            'payment_methods' => $paymentMethods,
            'additional_notes' => $additionalNotes,
            'payment_status' => in_array($billingCycle->billing_status, ['pending', 'partially_paid', 'paid_in_full'], true)
                ? $billingCycle->billing_status
                : Visit::query()->where('id', $visit->id)->value('payment_status'),
            'status' => $uiStatus,

            'billing_data' => $billingData,

            'billed_at' => $toIso($billingCycle->billed_at ?? null),
            'created_at' => $toIso($billingCycle->created_at ?? null),
            'updated_at' => $toIso($billingCycle->updated_at ?? null),

            'last_updated' => $toEpochMs($billingCycle->updated_at ?? null),
            'is_dirty' => false,
            'is_processing' => false,

            '_debug' => [
                'total_line_items' => $lineItems->count(),
                'active_line_items' => $activeLineItems->count(),
                'filtered_out_count' => $lineItems->count() - $activeLineItems->count(),
            ],
        ];
    }

    public function getBillingByFacility(
        int $facilityId,
        array $filters = [],
        string $search = '',
        int $perPage = 15,
        int $page = 1
    ): array {
        try {
            $query = BillingCycle::query()
                ->where('facility_id', $facilityId)
                ->withTrashed()
                ->with([
                    'visit' => fn($q) => $q->withTrashed(),
                    'visit.patient.user',
                    'lineItems' => fn($q) => $q->withTrashed(),
                ])
                ->orderByDesc('created_at');

            if (!empty($filters['status'])) {
                if (is_array($filters['status'])) {
                    $query->whereIn('billing_status', $filters['status']);
                } else {
                    $query->where('billing_status', $filters['status']);
                }
            }

            if (!empty($filters['date_from'])) {
                $query->whereDate('created_at', '>=', $filters['date_from']);
            }

            if (!empty($filters['date_to'])) {
                $query->whereDate('created_at', '<=', $filters['date_to']);
            }

            if (!empty($filters['payment_method'])) {
                $query->where('metadata', 'like', '%' . $filters['payment_method'] . '%');
            }

            if (!empty($filters['min_amount'])) {
                $query->where('net_amount', '>=', (float) $filters['min_amount']);
            }

            if (!empty($filters['max_amount'])) {
                $query->where('net_amount', '<=', (float) $filters['max_amount']);
            }

            if (!empty($search)) {
                $query->where(function ($q) use ($search) {
                    $q->where('billing_cycle_uuid', 'LIKE', "%{$search}%")
                        ->orWhere('id', 'LIKE', "%{$search}%")
                        ->orWhere('visit_id', 'LIKE', "%{$search}%")
                        ->orWhereHas('visit.patient.user', function ($userQuery) use ($search) {
                            $userQuery->where('first_name', 'LIKE', "%{$search}%")
                                ->orWhere('last_name', 'LIKE', "%{$search}%")
                                ->orWhere('display_name', 'LIKE', "%{$search}%");
                        });
                });
            }

            $total = $query->count();
            $billingCycles = $query->forPage($page, $perPage)->get();

            $transformedData = $billingCycles->map(function ($billingCycle) {
                $visit = $billingCycle->visit;

                if (!$visit) {
                    Log::warning('Billing cycle missing associated visit', [
                        'billing_cycle_id' => $billingCycle->id,
                    ]);
                    return null;
                }

                return $this->transformBillingData($billingCycle, $visit);
            })->filter()->values()->toArray();

            $totalPages = (int) ceil($total / $perPage);
            $from = ($page - 1) * $perPage + 1;
            $to = min($page * $perPage, $total);

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
            ]);

            return [
                'success' => false,
                'message' => 'An unexpected error occurred while retrieving billing data. Please try again or contact support.',
                'errors' => ['system' => ['Failed to retrieve billing information.']],
                'error' => config('app.debug') ? $e->getMessage() : null,
            ];
        }
    }

    public function getBillingByVisitForFacility(int $visitId, int $facilityId, ?int $currentStaffId = null): array
    {
        try {
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

            $billingCycle = BillingCycle::query()
                ->where('visit_id', $visitId)
                ->where('facility_id', $facilityId)
                ->with(['lineItems' => fn($q) => $q->withTrashed()])
                ->orderByRaw("CASE WHEN billing_status IN ('pending', 'partially_paid') THEN 0 ELSE 1 END")
                ->orderByDesc('created_at')
                ->first();

            if (!$billingCycle) {
                return [
                    'success' => true,
                    'message' => 'No billing record found for this visit.',
                    'data' => [
                        'items' => [],
                        'pagination' => [
                            'current_page' => 1,
                            'per_page' => 1,
                            'total_items' => 0,
                            'total_pages' => 0,
                            'from' => null,
                            'to' => null,
                            'has_previous' => false,
                            'has_next' => false,
                        ],
                        'filters_applied' => [],
                        'search_term' => null,
                        'visit_id' => $visitId,
                        'has_billing' => false,
                    ],
                ];
            }

            $transformedCycle = $this->transformBillingData($billingCycle, $visit, $currentStaffId);

            return [
                'success' => true,
                'message' => 'Billing data retrieved successfully.',
                'data' => [
                    'items' => [$transformedCycle],
                    'pagination' => [
                        'current_page' => 1,
                        'per_page' => 1,
                        'total_items' => 1,
                        'total_pages' => 1,
                        'from' => 1,
                        'to' => 1,
                        'has_previous' => false,
                        'has_next' => false,
                    ],
                    'filters_applied' => [],
                    'search_term' => null,
                ],
            ];
        } catch (Throwable $e) {
            Log::error('Failed to retrieve billing data (facility format)', [
                'visit_id' => $visitId,
                'facility_id' => $facilityId,
                'error_message' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'An unexpected error occurred while retrieving billing data. Please try again or contact support.',
                'errors' => ['system' => ['Failed to retrieve billing information.']],
                'error' => config('app.debug') ? $e->getMessage() : null,
            ];
        }
    }

    public function getBillingStatistics(int $facilityId, array $filters = []): array
    {
        try {
            $query = BillingCycle::query()->where('facility_id', $facilityId);

            if (!empty($filters['date_from'])) {
                $query->whereDate('created_at', '>=', $filters['date_from']);
            }

            if (!empty($filters['date_to'])) {
                $query->whereDate('created_at', '<=', $filters['date_to']);
            }

            $statusTotals = $query->selectRaw('
                billing_status,
                COUNT(*) as count,
                SUM(net_amount) as total_amount,
                SUM(patient_payment_received) as total_patient_paid,
                SUM(insurance_payment_received) as total_insurance_paid
            ')->groupBy('billing_status')->get();

            $statistics = [
                'total_billing_cycles' => $query->count(),
                'total_revenue' => $query->sum('net_amount'),
                'total_patient_payments' => $query->sum('patient_payment_received'),
                'total_insurance_payments' => $query->sum('insurance_payment_received'),
                'total_discounts_applied' => $query->sum('discount_applied'),
                'average_cycle_amount' => $query->avg('net_amount') ?? 0,
                'by_status' => [],
            ];

            foreach ($statusTotals as $statusTotal) {
                $uiStatus = $this->mapBillingStatusToUI((string) $statusTotal->billing_status);

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

    public function adjustBillingLineItem(int $lineItemId, array $data, int $facilityId, int $staffId): array
    {
        try {
            $lineItem = InvoiceLineItem::query()
                ->where('id', $lineItemId)
                ->whereHas('billingCycle', function ($query) use ($facilityId) {
                    $query->where('facility_id', $facilityId);
                })
                ->first();

            if (!$lineItem) {
                return [
                    'success' => false,
                    'message' => 'Billing line item not found for this facility.',
                    'errors' => ['line_item_id' => ['Invalid billing line item.']],
                ];
            }

            $action = (string) ($data['action'] ?? '');
            $quantity = (float) ($data['quantity'] ?? 1);
            $reason = trim((string) ($data['reason'] ?? ''));

            if (!in_array($action, ['increase', 'decrease', 'remove'], true)) {
                return [
                    'success' => false,
                    'message' => 'Invalid billing adjustment action.',
                    'errors' => ['action' => ['Action must be increase, decrease, or remove.']],
                ];
            }

            if ($action !== 'remove' && $quantity <= 0) {
                return [
                    'success' => false,
                    'message' => 'Quantity must be greater than zero.',
                    'errors' => ['quantity' => ['Quantity must be greater than zero for this action.']],
                ];
            }

            $lineMetadata = $this->decodeJsonishToArray($lineItem->metadata ?? null);
            $enteredByStaffId = $lineMetadata['originated_by_staff_id']
                ?? $lineItem->created_by_staff_id;

            if ($this->shouldRequireCrossStaffReason($enteredByStaffId, $staffId) && $reason === '') {
                return [
                    'success' => false,
                    'message' => 'Reason is required when editing an item entered by another staff member.',
                    'errors' => ['reason' => ['Please provide a reason for this cross-staff billing adjustment.']],
                ];
            }

            if ($action === 'increase') {
                $inventoryValidation = $this->validation->validateInventoryAvailability([
                    [
                        'service' => [
                            'code' => $lineItem->service_code,
                            'name' => $lineItem->service_description,
                        ],
                        'quantity' => $quantity,
                    ]
                ], $staffId);

                if (!$inventoryValidation['success']) {
                    return $inventoryValidation;
                }
            }

            $result = $this->processor->processPersistedLineItemAdjustment(
                $lineItem,
                $action,
                $quantity,
                $reason !== '' ? $reason : null,
                $staffId
            );

            return [
                'success' => true,
                'message' => 'Billing line item adjusted successfully.',
                'data' => [
                    'billing_cycle_id' => $result['billing_cycle']->id ?? null,
                    'billing_cycle_uuid' => $result['billing_cycle']->billing_cycle_uuid ?? null,
                    'line_item_id' => $result['line_item']->id ?? null,
                    'line_item_uuid' => $result['line_item']->line_item_uuid ?? null,
                    'billing_status' => $result['billing_cycle']->billing_status ?? null,
                    'total_paid' => $result['billing_cycle']->total_paid_amount ?? 0,
                    'balance' => $result['billing_cycle']->balance_amount ?? 0,
                ],
            ];
        } catch (Throwable $e) {
            Log::error('Failed to adjust billing line item', [
                'line_item_id' => $lineItemId,
                'facility_id' => $facilityId,
                'staff_id' => $staffId,
                'error_message' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'An unexpected error occurred while adjusting the billing item.',
                'errors' => ['system' => ['Billing adjustment failed.']],
                'error' => config('app.debug') ? $e->getMessage() : null,
            ];
        }
    }

    public function shouldRequireCrossStaffReason(?int $enteredByStaffId, ?int $currentStaffId): bool
    {
        if (!$enteredByStaffId || !$currentStaffId) {
            return true;
        }

        return (int) $enteredByStaffId !== (int) $currentStaffId;
    }

    public function buildLineItemEditPolicy(?int $enteredByStaffId, ?int $currentStaffId): array
    {
        $reasonRequired = $this->shouldRequireCrossStaffReason($enteredByStaffId, $currentStaffId);

        return [
            'entered_by_staff_id' => $enteredByStaffId,
            'current_staff_id' => $currentStaffId,
            'requires_reason_on_cross_staff_edit' => true,
            'reason_required' => $reasonRequired,
            'can_edit_without_reason' => !$reasonRequired,
        ];
    }

    public function decodeJsonishToArray($value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value) && trim($value) !== '') {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }
}