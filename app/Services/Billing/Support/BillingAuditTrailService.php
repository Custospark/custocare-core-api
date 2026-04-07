<?php

namespace App\Services\Billing\Support;

use App\Models\AuditLog;
use App\Models\BillingCycle;
use App\Models\FacilityStaffRole;
use App\Models\FinancialAdjustment;
use App\Models\InvoiceLineItem;
use App\Models\Staff;
use App\Models\User;
use App\Services\Contracts\AuditLogServiceInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class BillingAuditTrailService
{
    public function __construct(
        protected AuditLogServiceInterface $auditLogService
    ) {
    }

    public function snapshotBillingCycle(BillingCycle $billingCycle): array
    {
        return [
            'billing_cycle_id' => $billingCycle->id,
            'billing_cycle_uuid' => $billingCycle->billing_cycle_uuid,
            'billing_status' => (string) $billingCycle->billing_status,
            'subtotal_amount' => round((float) ($billingCycle->subtotal_amount ?? 0), 2),
            'discount_applied' => round((float) ($billingCycle->discount_applied ?? 0), 2),
            'total_tax_amount' => round((float) ($billingCycle->total_tax_amount ?? 0), 2),
            'grand_total_amount' => round((float) ($billingCycle->grand_total_amount ?? 0), 2),
            'patient_payment_received' => round((float) ($billingCycle->patient_payment_received ?? 0), 2),
            'insurance_payment_received' => round((float) ($billingCycle->insurance_payment_received ?? 0), 2),
            'total_paid_amount' => round((float) ($billingCycle->total_paid_amount ?? 0), 2),
            'balance_amount' => round((float) ($billingCycle->balance_amount ?? 0), 2),
        ];
    }

    public function snapshotLineItem(InvoiceLineItem $lineItem): array
    {
        return [
            'line_item_id' => $lineItem->id,
            'line_item_uuid' => $lineItem->line_item_uuid,
            'service_code' => (string) $lineItem->service_code,
            'service_description' => (string) $lineItem->service_description,
            'quantity' => round((float) ($lineItem->quantity ?? 0), 2),
            'unit_price_at_time' => round((float) ($lineItem->unit_price_at_time ?? 0), 2),
            'line_total_amount' => round((float) ($lineItem->line_total_amount ?? 0), 2),
            'discount_amount' => round((float) ($lineItem->discount_amount ?? 0), 2),
            'net_amount' => round((float) ($lineItem->net_amount ?? 0), 2),
            'line_item_status' => (string) ($lineItem->line_item_status ?? 'pending'),
        ];
    }

    public function logBillingCycleEvent(
        BillingCycle $billingCycle,
        string $eventKey,
        string $title,
        ?int $staffId,
        ?string $reason,
        array $before = [],
        array $after = [],
        array $extraMetadata = []
    ): void {
        $this->writeAuditLog([
            'operation' => empty($before) ? 'create' : 'update',
            'entity_type' => 'billing_cycle',
            'entity_id' => $billingCycle->id,
            'entity_identifier' => $billingCycle->billing_cycle_uuid,
            'performed_by_type' => $staffId ? 'staff' : 'system',
            'performed_by_id' => $staffId,
            'performed_by_identifier' => $this->resolveStaffIdentifier($staffId),
            'performed_by_role' => $this->resolveFacilityRoleCode($staffId, $billingCycle->facility_id),
            'request_id' => $this->resolveRequestId(),
            'session_id' => (request() && method_exists(request(), 'hasSession') && request()->hasSession()) ? request()->session()->getId() : null,
            'user_ip' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
            'compliance_reason' => 'billing',
            'justification' => $reason,
            'facility_id' => $billingCycle->facility_id,
            'patient_id' => $billingCycle->patient_id,
            'phi_accessed' => false,
            'result' => 'success',
            'previous_values' => !empty($before) ? $before : null,
            'new_values' => !empty($after) ? $after : null,
            'changed_fields' => $this->resolveChangedFields($before, $after),
            'metadata' => array_merge([
                'event_key' => $eventKey,
                'scope' => 'billing_cycle',
                'title' => $title,
                'reason' => $reason,
                'billing_cycle_id' => $billingCycle->id,
                'billing_cycle_uuid' => $billingCycle->billing_cycle_uuid,
            ], $extraMetadata),
        ]);
    }

    public function logLineItemEvent(
        InvoiceLineItem $lineItem,
        BillingCycle $billingCycle,
        string $eventKey,
        string $title,
        ?int $staffId,
        ?string $reason,
        array $before = [],
        array $after = [],
        array $extraMetadata = []
    ): void {
        $this->writeAuditLog([
            'operation' => empty($before) ? 'create' : 'update',
            'entity_type' => 'invoice_line_item',
            'entity_id' => $lineItem->id,
            'entity_identifier' => $lineItem->line_item_uuid,
            'performed_by_type' => $staffId ? 'staff' : 'system',
            'performed_by_id' => $staffId,
            'performed_by_identifier' => $this->resolveStaffIdentifier($staffId),
            'performed_by_role' => $this->resolveFacilityRoleCode($staffId, $billingCycle->facility_id),
            'request_id' => $this->resolveRequestId(),
            'session_id' => (request() && method_exists(request(), 'hasSession') && request()->hasSession()) ? request()->session()->getId() : null,
            'user_ip' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
            'compliance_reason' => 'billing',
            'justification' => $reason,
            'facility_id' => $billingCycle->facility_id,
            'patient_id' => $billingCycle->patient_id,
            'phi_accessed' => false,
            'result' => 'success',
            'previous_values' => !empty($before) ? $before : null,
            'new_values' => !empty($after) ? $after : null,
            'changed_fields' => $this->resolveChangedFields($before, $after),
            'metadata' => array_merge([
                'event_key' => $eventKey,
                'scope' => 'line_item',
                'title' => $title,
                'reason' => $reason,
                'billing_cycle_id' => $billingCycle->id,
                'billing_cycle_uuid' => $billingCycle->billing_cycle_uuid,
                'line_item_id' => $lineItem->id,
                'line_item_uuid' => $lineItem->line_item_uuid,
                'service_code' => $lineItem->service_code,
                'service_name' => $lineItem->service_description,
            ], $extraMetadata),
        ]);
    }

    public function logFinancialAdjustmentEvent(
        FinancialAdjustment $adjustment,
        BillingCycle $billingCycle,
        string $eventKey,
        string $title,
        ?int $staffId,
        ?string $reason,
        array $before = [],
        array $after = [],
        array $extraMetadata = []
    ): void {
        $this->writeAuditLog([
            'operation' => 'create',
            'entity_type' => 'financial_adjustment',
            'entity_id' => $adjustment->id,
            'entity_identifier' => $adjustment->reference_number,
            'performed_by_type' => $staffId ? 'staff' : 'system',
            'performed_by_id' => $staffId,
            'performed_by_identifier' => $this->resolveStaffIdentifier($staffId),
            'performed_by_role' => $this->resolveFacilityRoleCode($staffId, $billingCycle->facility_id),
            'request_id' => $this->resolveRequestId(),
            'session_id' => (request() && method_exists(request(), 'hasSession') && request()->hasSession()) ? request()->session()->getId() : null,
            'user_ip' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
            'compliance_reason' => 'billing',
            'justification' => $reason,
            'facility_id' => $billingCycle->facility_id,
            'patient_id' => $billingCycle->patient_id,
            'phi_accessed' => false,
            'result' => 'success',
            'previous_values' => !empty($before) ? $before : null,
            'new_values' => !empty($after) ? $after : null,
            'changed_fields' => $this->resolveChangedFields($before, $after),
            'metadata' => array_merge([
                'event_key' => $eventKey,
                'scope' => 'billing_cycle',
                'title' => $title,
                'reason' => $reason,
                'billing_cycle_id' => $billingCycle->id,
                'billing_cycle_uuid' => $billingCycle->billing_cycle_uuid,
                'financial_adjustment_id' => $adjustment->id,
                'reference_number' => $adjustment->reference_number,
                'adjustment_type' => $adjustment->adjustment_type,
            ], $extraMetadata),
        ]);
    }

    public function buildBillingCycleAuditBundle(BillingCycle $billingCycle): array
    {
        $logs = AuditLog::query()
            ->where('facility_id', $billingCycle->facility_id)
            ->where('compliance_reason', 'billing')
            ->where(function ($query) use ($billingCycle) {
                $query
                    ->where(function ($subQuery) use ($billingCycle) {
                        $subQuery
                            ->where('entity_type', 'billing_cycle')
                            ->where('entity_id', $billingCycle->id);
                    })
                    ->orWhere('metadata->billing_cycle_id', $billingCycle->id);
            })
            ->orderByDesc('created_at')
            ->get();

        $timeline = $logs
            ->map(fn (AuditLog $log) => $this->formatAuditLogForUi($log))
            ->values()
            ->all();

        $lineItemMap = $logs
            ->groupBy(function (AuditLog $log) {
                $metadata = is_array($log->metadata) ? $log->metadata : [];

                if (!empty($metadata['line_item_id'])) {
                    return (int) $metadata['line_item_id'];
                }

                if ($log->entity_type === 'invoice_line_item' && $log->entity_id) {
                    return (int) $log->entity_id;
                }

                return 0;
            })
            ->filter(fn ($group, $lineItemId) => (int) $lineItemId > 0)
            ->map(function ($group) {
                return collect($group)
                    ->map(fn (AuditLog $log) => $this->formatAuditLogForUi($log))
                    ->values()
                    ->all();
            })
            ->toArray();

        return [
            'timeline' => $timeline,
            'line_items' => $lineItemMap,
            'summary' => [
                'count' => count($timeline),
                'last_event_at' => $timeline[0]['performed_at'] ?? null,
                'last_event' => $timeline[0] ?? null,
            ],
        ];
    }

    public function formatAuditLogForUi(AuditLog $log): array
    {
        $metadata = is_array($log->metadata) ? $log->metadata : [];
        $displayName = $this->resolveDisplayNameFromLog($log);

        return [
            'id' => $log->id,
            'audit_uuid' => $log->audit_uuid,
            'event_key' => $metadata['event_key'] ?? strtolower($log->entity_type . '_' . $log->operation),
            'scope' => $metadata['scope'] ?? ($log->entity_type === 'invoice_line_item' ? 'line_item' : 'billing_cycle'),
            'title' => $metadata['title'] ?? Str::headline(str_replace('_', ' ', $metadata['event_key'] ?? $log->operation)),
            'action' => $metadata['action'] ?? Str::headline(str_replace('_', ' ', $metadata['event_key'] ?? $log->operation)),
            'description' => $metadata['description'] ?? null,
            'reason' => $metadata['reason'] ?? $log->justification,
            'why' => $metadata['reason'] ?? $log->justification,
            'performed_at' => optional($log->created_at)->toIso8601String(),
            'performed_at_unix_ms' => $log->created_at ? ($log->created_at->getTimestamp() * 1000) : null,
            'performed_by' => [
                'type' => $log->performed_by_type,
                'id' => $log->performed_by_id,
                'identifier' => $log->performed_by_identifier,
                'name' => $displayName,
                'role' => $log->performed_by_role,
                'display' => $log->performed_by_role
                    ? trim($displayName . ' (' . Str::headline(str_replace('_', ' ', $log->performed_by_role)) . ')')
                    : $displayName,
            ],
            'entity' => [
                'type' => $log->entity_type,
                'id' => $log->entity_id,
                'identifier' => $log->entity_identifier,
                'billing_cycle_id' => $metadata['billing_cycle_id'] ?? null,
                'line_item_id' => $metadata['line_item_id'] ?? null,
                'financial_adjustment_id' => $metadata['financial_adjustment_id'] ?? null,
            ],
            'changed_fields' => $log->changed_fields ?? [],
            'before' => $log->previous_values ?? [],
            'after' => $log->new_values ?? [],
            'result' => $log->result,
        ];
    }

    protected function writeAuditLog(array $payload): void
    {
        $result = $this->auditLogService->createAuditLog($payload);

        if (empty($result['success'])) {
            Log::warning('Billing audit log creation failed.', [
                'entity_type' => $payload['entity_type'] ?? null,
                'entity_id' => $payload['entity_id'] ?? null,
                'message' => $result['message'] ?? null,
                'errors' => $result['errors'] ?? null,
            ]);
        }
    }

    protected function resolveChangedFields(array $before, array $after): array
    {
        $fields = collect(array_unique(array_merge(array_keys($before), array_keys($after))))
            ->filter(function ($key) use ($before, $after) {
                return ($before[$key] ?? null) !== ($after[$key] ?? null);
            })
            ->values()
            ->all();

        return !empty($fields) ? $fields : [];
    }

    protected function resolveRequestId(): string
    {
        return (string) (
            request()?->header('X-Request-Id')
            ?: request()?->attributes?->get('request_id')
            ?: Str::uuid()
        );
    }

    protected function resolveStaffIdentifier(?int $staffId): ?string
    {
        if (!$staffId) {
            return null;
        }

        return Staff::query()->where('id', $staffId)->value('staff_uuid');
    }

    protected function resolveDisplayNameFromLog(AuditLog $log): string
    {
        if (!empty($log->performed_by_identifier) && $log->performed_by_type !== 'staff') {
            return (string) $log->performed_by_identifier;
        }

        if (!$log->performed_by_id || $log->performed_by_type !== 'staff') {
            return $log->performed_by_identifier ?: 'System';
        }

        $staff = Staff::query()->find($log->performed_by_id);
        if (!$staff) {
            return $log->performed_by_identifier ?: 'Unknown Staff';
        }

        $user = User::query()->find($staff->user_id);
        if (!$user) {
            return $log->performed_by_identifier ?: 'Unknown Staff';
        }

        return trim((string) ($user->display_name ?: (($user->first_name ?? '') . ' ' . ($user->last_name ?? ''))));
    }

    protected function resolveFacilityRoleCode(?int $staffId, ?int $facilityId): ?string
    {
        if (!$staffId || !$facilityId) {
            return null;
        }

        return FacilityStaffRole::query()
            ->where('staff_id', $staffId)
            ->where('facility_id', $facilityId)
            ->value('role_code');
    }
}
