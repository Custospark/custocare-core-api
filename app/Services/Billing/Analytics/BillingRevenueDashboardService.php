<?php

namespace App\Services\Billing\Analytics;

use App\Models\BillingCycle;
use App\Models\Patient;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class BillingRevenueDashboardService
{
    public function getDashboard(int $facilityId, array $filters = []): array
    {
        try {
            $normalized = $this->normalizeFilters($filters);

            $activeCycles = $this->getActiveBillingCycles(
                $facilityId,
                $normalized['date_from'],
                $normalized['date_to']
            );

            $previousPeriodCycles = $this->getActiveBillingCycles(
                $facilityId,
                $normalized['previous_date_from'],
                $normalized['previous_date_to']
            );

            $adjustments = $this->getFinancialAdjustments(
                $facilityId,
                $normalized['date_from'],
                $normalized['date_to']
            );

            $lineItems = $this->getRevenueLineItems($activeCycles->pluck('id')->all());

            $data = [
                'filters' => [
                    'facility_id' => $facilityId,
                    'date_from' => $normalized['date_from']->toDateString(),
                    'date_to' => $normalized['date_to']->toDateString(),
                    'group_by' => $normalized['group_by'],
                    'top' => $normalized['top'],
                ],
                'snapshot' => $this->buildSnapshot(
                    $activeCycles,
                    $previousPeriodCycles,
                    $adjustments
                ),
                'revenue_trend' => $this->buildRevenueTrend(
                    $activeCycles,
                    $normalized['group_by']
                ),
                'billing_activity' => $this->buildBillingActivity(
                    $activeCycles,
                    $normalized['group_by']
                ),
                'revenue_breakdown' => $this->buildRevenueBreakdown(
                    $lineItems,
                    $adjustments,
                    $normalized['top']
                ),
                'financial_leakages' => $this->buildFinancialLeakages(
                    $adjustments,
                    $normalized['group_by'],
                    $normalized['top'],
                    $activeCycles
                ),
                'performance_by_day' => $this->buildPerformanceByDay($activeCycles),
                'staff_contribution' => $this->buildStaffContribution(
                    $activeCycles,
                    $normalized['top']
                ),
                'collections' => $this->buildCollections(
                    $activeCycles,
                    $normalized['date_to']
                ),
                'payment_mix' => $this->buildPaymentMix($activeCycles),
                'inventory_leakage' => $this->buildInventoryLeakage(
                    $adjustments,
                    $lineItems,
                    $normalized['top']
                ),
            ];

            return [
                'success' => true,
                'message' => 'Billing revenue dashboard data retrieved successfully.',
                'data' => $data,
            ];
        } catch (Throwable $e) {
            Log::error('Billing revenue dashboard generation failed.', [
                'facility_id' => $facilityId,
                'filters' => $filters,
                'error_message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to generate billing revenue dashboard.',
                'errors' => [
                    'system' => ['An unexpected error occurred while generating the dashboard.'],
                ],
                'error' => config('app.debug') ? $e->getMessage() : null,
            ];
        }
    }

    protected function normalizeFilters(array $filters): array
    {
        $groupBy = in_array($filters['group_by'] ?? 'day', ['day', 'week', 'month'], true)
                                                                                            ? ($filters['group_by'] ?? 'day')
                                                                                            : 'day';

        $dateFrom = !empty($filters['date_from'])
            ? Carbon::parse($filters['date_from'])->startOfDay()
            : now()->startOfMonth();

        $dateTo = !empty($filters['date_to'])
            ? Carbon::parse($filters['date_to'])->endOfDay()
            : now()->endOfDay();

        if ($dateTo->lt($dateFrom)) {
            [$dateFrom, $dateTo] = [$dateTo->copy()->startOfDay(), $dateFrom->copy()->endOfDay()];
        }

        $periodDays = $dateFrom->diffInDays($dateTo) + 1;
        $previousDateTo = $dateFrom->copy()->subDay()->endOfDay();
        $previousDateFrom = $previousDateTo->copy()->subDays($periodDays - 1)->startOfDay();

        return [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'previous_date_from' => $previousDateFrom,
            'previous_date_to' => $previousDateTo,
            'group_by' => $groupBy,
            'top' => max(1, min((int) ($filters['top'] ?? 10), 25)),
        ];
    }

    protected function getActiveBillingCycles(int $facilityId, CarbonInterface $from, CarbonInterface $to): Collection
    {
        return DB::table('billing_cycles')
            ->where('facility_id', $facilityId)
            ->whereNull('deleted_at')
            ->whereBetween(DB::raw('COALESCE(billed_at, created_at)'), [$from, $to])
            ->orderBy('id')
            ->get()
            ->map(fn ($row) => $this->normalizeBillingCycleRow($row));
    }

    protected function getFinancialAdjustments(int $facilityId, CarbonInterface $from, CarbonInterface $to): Collection
    {
        return DB::table('financial_adjustments')
            ->where('facility_id', $facilityId)
            ->whereBetween(DB::raw('COALESCE(completed_at, created_at)'), [$from, $to])
            ->orderByDesc('id')
            ->get()
            ->map(function ($row) {
                $row = (array) $row;

                $row['refund_methods'] = $this->decodeJsonish($row['refund_methods'] ?? null, []);
                $row['affected_line_items'] = $this->decodeJsonish($row['affected_line_items'] ?? null, []);
                $row['inventory_restored'] = $this->decodeJsonish($row['inventory_restored'] ?? null, []);
                $row['original_billing_snapshot'] = $this->decodeJsonish($row['original_billing_snapshot'] ?? null, []);
                $row['metadata'] = $this->decodeJsonish($row['metadata'] ?? null, []);
                $row['effective_amount'] = $this->getEffectiveAdjustmentAmount($row);
                $row['event_at'] = !empty($row['completed_at'])
                    ? Carbon::parse($row['completed_at'])
                    : Carbon::parse($row['created_at']);

                return $row;
            });
    }

    protected function getRevenueLineItems(array $billingCycleIds): Collection
    {
        if (empty($billingCycleIds)) {
            return collect();
        }

        return DB::table('invoice_line_items')
            ->leftJoin('service_catalogs', 'service_catalogs.id', '=', 'invoice_line_items.service_catalog_id')
            ->leftJoin('inventory_items', 'inventory_items.id', '=', 'invoice_line_items.inventory_item_id')
            ->whereIn('invoice_line_items.billing_cycle_id', $billingCycleIds)
            ->whereNull('invoice_line_items.deleted_at')
            ->where('invoice_line_items.quantity', '>', 0)
            ->where('invoice_line_items.net_amount', '>', 0)
            ->where('invoice_line_items.line_item_status', '!=', 'written_off')
            ->select([
                'invoice_line_items.*',
                'service_catalogs.service_name as catalog_service_name',
                'service_catalogs.service_category as catalog_service_category',
                'inventory_items.item_name as inventory_item_name',
                'inventory_items.item_category as inventory_item_category',
                'inventory_items.item_code as inventory_item_code',
            ])
            ->get()
            ->map(function ($row) {
                $row = (array) $row;
                $row['service_version_snapshot'] = $this->decodeJsonish($row['service_version_snapshot'] ?? null, []);
                $row['metadata'] = $this->decodeJsonish($row['metadata'] ?? null, []);

                return $row;
            });
    }

    protected function normalizeBillingCycleRow(object $row): array
    {
        $data = (array) $row;
        $metadata = $this->decodeJsonish($data['metadata'] ?? null, []);
        $taxDetails = $this->decodeJsonish($data['tax_details'] ?? null, []);
        $paymentMethods = $this->decodeJsonish($metadata['payment_methods'] ?? null, []);

        $eventAt = !empty($data['billed_at'])
            ? Carbon::parse($data['billed_at'])
            : Carbon::parse($data['created_at']);

        $validatedTotalPaid = $this->safeNumber(
            $metadata['validated_total_paid']
                ?? $data['total_paid_amount']
                ?? 0
        );

        $rawTotalPaidBeforeCap = $this->safeNumber(
            $metadata['raw_total_paid_before_cap']
                ?? ($data['patient_payment_received'] + $data['insurance_payment_received'])
        );

        return [
            ...$data,
            'metadata_decoded' => is_array($metadata) ? $metadata : [],
            'tax_details_decoded' => is_array($taxDetails) ? $taxDetails : [],
            'payment_methods_decoded' => is_array($paymentMethods) ? $paymentMethods : [],
            'event_at' => $eventAt,
            'validated_total_paid' => round($validatedTotalPaid, 2),
            'raw_total_paid_before_cap' => round($rawTotalPaidBeforeCap, 2),
        ];
    }

    protected function buildSnapshot(
        Collection $activeCycles,
        Collection $previousPeriodCycles,
        Collection $adjustments
    ): array {
        $grossBilled = round($activeCycles->sum(fn ($row) => $this->safeNumber($row['total_amount_charged'] ?? 0)), 2);
        $netRevenue = round($activeCycles->sum(fn ($row) => $this->safeNumber($row['total_paid_amount'] ?? 0)), 2);
        $totalCollections = round($activeCycles->sum(fn ($row) => $this->safeNumber($row['total_paid_amount'] ?? 0)), 2);
        $outstandingBalance = round($activeCycles->sum(fn ($row) => $this->safeNumber($row['balance_amount'] ?? 0)), 2);
        $totalInvoices = $activeCycles->count();

        $billValues = $activeCycles
            ->map(fn ($row) => $this->safeNumber($row['grand_total_amount'] ?? 0))
            ->filter(fn ($value) => $value >= 0)
            ->values();

        $previousNetRevenue = round($previousPeriodCycles->sum(fn ($row) => $this->safeNumber($row['grand_total_amount'] ?? 0)), 2);
        $revenueGrowth = $this->percentageChange($previousNetRevenue, $netRevenue);

        $totalLeakage = round($adjustments->sum(fn ($row) => $this->safeNumber($row['effective_amount'] ?? 0)), 2);
        $leakagePercentage = $grossBilled > 0
            ? round(($totalLeakage / $grossBilled) * 100, 2)
            : 0.0;

        return [
            'gross_billed_amount' => $grossBilled,
            'net_revenue' => $netRevenue,
            'total_collections' => $totalCollections,
            'outstanding_balance' => $outstandingBalance,
            'total_invoices' => $totalInvoices,
            'average_bill_value' => round($billValues->avg() ?? 0, 2),
            'median_bill_value' => round($this->median($billValues), 2),
            'min_bill_value' => round($billValues->min() ?? 0, 2),
            'max_bill_value' => round($billValues->max() ?? 0, 2),
            'previous_period_net_revenue' => $previousNetRevenue,
            'revenue_growth_percentage' => $revenueGrowth,
            'total_leakage_amount' => $totalLeakage,
            'leakage_percentage' => $leakagePercentage,
        ];
    }

    protected function buildRevenueTrend(Collection $activeCycles, string $groupBy): array
    {
        $grouped = $activeCycles->groupBy(fn ($row) => $this->getPeriodKey($row['event_at'], $groupBy));

        return $grouped
            ->map(function (Collection $rows, string $periodKey) use ($groupBy) {
                return [
                    'period_key' => $periodKey,
                    'period_label' => $this->getPeriodLabel($rows->first()['event_at'], $groupBy),
                    'gross_billed_amount' => round($rows->sum(fn ($row) => $this->safeNumber($row['total_amount_charged'] ?? 0)), 2),
                    'net_revenue' => round($rows->sum(fn ($row) => $this->safeNumber($row['total_paid_amount'] ?? 0)), 2),
                    'total_collections' => round($rows->sum(fn ($row) => $this->safeNumber($row['total_paid_amount'] ?? 0)), 2),
                    'outstanding_balance' => round($rows->sum(fn ($row) => $this->safeNumber($row['balance_amount'] ?? 0)), 2),
                    'invoice_count' => $rows->count(),
                ];
            })
            ->sortBy('period_key')
            ->values()
            ->toArray();
    }

    protected function buildBillingActivity(Collection $activeCycles, string $groupBy): array
    {
        $grouped = $activeCycles->groupBy(fn ($row) => $this->getPeriodKey($row['event_at'], $groupBy));

        return $grouped
            ->map(function (Collection $rows, string $periodKey) use ($groupBy) {
                $values = $rows->map(fn ($row) => $this->safeNumber($row['grand_total_amount'] ?? 0))->values();

                return [
                    'period_key' => $periodKey,
                    'period_label' => $this->getPeriodLabel($rows->first()['event_at'], $groupBy),
                    'invoice_count' => $rows->count(),
                    'average_bill_value' => round($values->avg() ?? 0, 2),
                    'median_bill_value' => round($this->median($values), 2),
                    'min_bill_value' => round($values->min() ?? 0, 2),
                    'max_bill_value' => round($values->max() ?? 0, 2),
                ];
            })
            ->sortBy('period_key')
            ->values()
            ->toArray();
    }

    protected function buildRevenueBreakdown(Collection $lineItems, Collection $adjustments, int $top): array
    {
        $refundMetricsByService = $this->buildRefundMetricsByService($adjustments);

        $serviceRows = $lineItems
            ->groupBy(function ($row) {
                $name = $row['service_description']
                    ?: $row['catalog_service_name']
                    ?: $row['inventory_item_name']
                    ?: $row['service_code']
                    ?: 'Unknown Service';

                $category = $row['catalog_service_category']
                    ?: $row['inventory_item_category']
                    ?: ($row['metadata']['category'] ?? 'uncategorized');

                return strtoupper((string) ($row['service_code'] ?? 'UNKNOWN')) . '||' . $name . '||' . $category;
            })
            ->map(function (Collection $rows, string $groupKey) use ($refundMetricsByService) {
                [$serviceCode, $serviceName, $category] = explode('||', $groupKey);
                $revenue = round($rows->sum(fn ($row) => $this->safeNumber($row['net_amount'] ?? 0)), 2);
                $quantity = round($rows->sum(fn ($row) => $this->safeNumber($row['quantity'] ?? 0)), 2);
                $refundMeta = $refundMetricsByService[$serviceCode] ?? ['refund_count' => 0, 'refund_amount' => 0];

                return [
                    'service_code' => $serviceCode,
                    'service_name' => $serviceName,
                    'category' => $category,
                    'revenue' => $revenue,
                    'quantity_sold' => $quantity,
                    'average_unit_price' => round($rows->avg(fn ($row) => $this->safeNumber($row['unit_price_at_time'] ?? 0)) ?? 0, 2),
                    'refund_count' => (int) $refundMeta['refund_count'],
                    'refund_amount' => round((float) $refundMeta['refund_amount'], 2),
                ];
            })
            ->sortByDesc('revenue')
            ->values();

        $totalRevenue = round($serviceRows->sum('revenue'), 2);

        $byService = $serviceRows
            ->map(function (array $row) use ($totalRevenue) {
                $row['share_percentage'] = $totalRevenue > 0
                    ? round(($row['revenue'] / $totalRevenue) * 100, 2)
                    : 0.0;

                return $row;
            })
            ->take($top)
            ->values()
            ->toArray();

        $byCategory = $serviceRows
            ->groupBy('category')
            ->map(function (Collection $rows, string $category) use ($totalRevenue) {
                $revenue = round($rows->sum('revenue'), 2);

                return [
                    'category' => $category,
                    'revenue' => $revenue,
                    'quantity_sold' => round($rows->sum('quantity_sold'), 2),
                    'share_percentage' => $totalRevenue > 0
                        ? round(($revenue / $totalRevenue) * 100, 2)
                        : 0.0,
                ];
            })
            ->sortByDesc('revenue')
            ->values()
            ->toArray();

        return [
            'by_service' => $byService,
            'by_category' => $byCategory,
        ];
    }

    protected function buildFinancialLeakages(
        Collection $adjustments,
        string $groupBy,
        int $top,
        Collection $activeCycles
    ): array {
        $refunds = $adjustments->filter(fn ($row) => in_array($row['adjustment_type'], ['full_refund', 'partial_refund', 'line_item_refund'], true));
        $voids = $adjustments->filter(fn ($row) => $row['adjustment_type'] === 'void_transaction');

        $grossBilled = round($activeCycles->sum(fn ($row) => $this->safeNumber($row['total_amount_charged'] ?? 0)), 2);
        $totalLeakage = round($adjustments->sum(fn ($row) => $this->safeNumber($row['effective_amount'] ?? 0)), 2);

        $summary = [
            'total_refund_amount' => round($refunds->sum(fn ($row) => $this->safeNumber($row['effective_amount'] ?? 0)), 2),
            'total_voided_amount' => round($voids->sum(fn ($row) => $this->safeNumber($row['effective_amount'] ?? 0)), 2),
            'total_leakage_amount' => $totalLeakage,
            'refund_transaction_count' => $refunds->count(),
            'void_transaction_count' => $voids->count(),
            'average_refund_size' => round($refunds->avg(fn ($row) => $this->safeNumber($row['effective_amount'] ?? 0)) ?? 0, 2),
            'leakage_rate_percentage' => $grossBilled > 0
                ? round(($totalLeakage / $grossBilled) * 100, 2)
                : 0.0,
        ];

        $byReason = $adjustments
            ->groupBy('adjustment_reason')
            ->map(function (Collection $rows, string $reason) {
                return [
                    'reason' => $reason,
                    'count' => $rows->count(),
                    'amount' => round($rows->sum(fn ($row) => $this->safeNumber($row['effective_amount'] ?? 0)), 2),
                ];
            })
            ->sortByDesc('amount')
            ->values()
            ->toArray();

        $trend = $adjustments
            ->groupBy(fn ($row) => $this->getPeriodKey($row['event_at'], $groupBy))
            ->map(function (Collection $rows, string $periodKey) use ($groupBy) {
                return [
                    'period_key' => $periodKey,
                    'period_label' => $this->getPeriodLabel($rows->first()['event_at'], $groupBy),
                    'refund_amount' => round(
                        $rows->filter(fn ($row) => in_array($row['adjustment_type'], ['full_refund', 'partial_refund', 'line_item_refund'], true))
                            ->sum(fn ($row) => $this->safeNumber($row['effective_amount'] ?? 0)),
                        2
                    ),
                    'void_amount' => round(
                        $rows->filter(fn ($row) => $row['adjustment_type'] === 'void_transaction')
                            ->sum(fn ($row) => $this->safeNumber($row['effective_amount'] ?? 0)),
                        2
                    ),
                    'total_leakage_amount' => round($rows->sum(fn ($row) => $this->safeNumber($row['effective_amount'] ?? 0)), 2),
                    'count' => $rows->count(),
                ];
            })
            ->sortBy('period_key')
            ->values()
            ->toArray();

        $topCases = $adjustments
            ->sortByDesc(fn ($row) => $this->safeNumber($row['effective_amount'] ?? 0))
            ->take($top)
            ->map(function ($row) {
                $snapshot = is_array($row['original_billing_snapshot'] ?? null) ? $row['original_billing_snapshot'] : [];
            $patientId= isset($row['visit_id']) && $row['visit_id'] 
                                ? BillingCycle::where('visit_id', (int) $row['visit_id'])->value('patient_id') 
                                    : null;  
                return [
                    'adjustment_id' => (int) $row['id'],
                    'reference_number' => $row['reference_number'],
                    'adjustment_type' => $row['adjustment_type'],
                    'adjustment_reason' => $row['adjustment_reason'],
                    'amount' => round($this->safeNumber($row['effective_amount'] ?? 0), 2),
                        'patient_id' => Patient::where('id',$patientId)->value('patient_uuid'),//We are returning patient number instead to avoid returning sensitive patient id.          
                    'visit_id' => $row['visit_id'] ? (int) $row['visit_id'] : null,
                    'billing_cycle_id' => $row['billing_cycle_id'] ? (int) $row['billing_cycle_id'] : null,
                    'billing_cycle_uuid' => $snapshot['billing_cycle_uuid'] ?? null,
                    'completed_at' => optional($row['event_at'])->toIso8601String(),
                ];
            })
            ->values()
            ->toArray();

        return [
            'summary' => $summary,
            'by_reason' => $byReason,
            'trend' => $trend,
            'top_cases' => $topCases,
        ];
    }

    protected function buildPerformanceByDay(Collection $activeCycles): array
    {
        $days = [
            1 => 'Monday',
            2 => 'Tuesday',
            3 => 'Wednesday',
            4 => 'Thursday',
            5 => 'Friday',
            6 => 'Saturday',
            7 => 'Sunday',
        ];

        $grouped = $activeCycles->groupBy(fn ($row) => $row['event_at']->dayOfWeekIso);

        return collect($days)
            ->map(function (string $dayName, int $dayNumber) use ($grouped) {
                $rows = $grouped->get($dayNumber, collect());

                return [
                    'day_of_week_number' => $dayNumber,
                    'day_of_week' => $dayName,
                    'invoice_count' => $rows->count(),
                    'net_revenue' => round($rows->sum(fn ($row) => $this->safeNumber($row['total_paid_amount'] ?? 0)), 2),
                    'gross_billed_amount' => round($rows->sum(fn ($row) => $this->safeNumber($row['total_amount_charged'] ?? 0)), 2),
                    'average_bill_value' => round($rows->avg(fn ($row) => $this->safeNumber($row['total_paid_amount'] ?? 0)) ?? 0, 2),
                ];
            })
            ->values()
            ->toArray();
    }

    protected function buildStaffContribution(Collection $activeCycles, int $top): array
    {
        if ($activeCycles->isEmpty()) {
            return [];
        }

        $cycleIds = $activeCycles->pluck('id')->all();

        $rows = DB::table('invoice_line_items')
            ->join('billing_cycles', 'billing_cycles.id', '=', 'invoice_line_items.billing_cycle_id')
            ->whereIn('invoice_line_items.billing_cycle_id', $cycleIds)
            ->whereNull('invoice_line_items.deleted_at')
            ->where('invoice_line_items.quantity', '>', 0)
            ->where('invoice_line_items.net_amount', '>', 0)
            ->select([
                'invoice_line_items.billing_cycle_id',
                'invoice_line_items.staff_performed_id',
                'billing_cycles.created_by_staff_id',
                'invoice_line_items.net_amount',
            ])
            ->get()
            ->map(fn ($row) => [
                'billing_cycle_id' => (int) $row->billing_cycle_id,
                'staff_id' => (int) ($row->staff_performed_id ?: $row->created_by_staff_id),
                'net_amount' => $this->safeNumber($row->net_amount),
            ])
            ->filter(fn ($row) => $row['staff_id'] > 0);

        $staffIds = $rows->pluck('staff_id')->unique()->values()->all();

        $staffMap = DB::table('staff')
            ->leftJoin('users', 'users.id', '=', 'staff.user_id')
            ->whereIn('staff.id', $staffIds)
            ->select([
                'staff.id',
                'staff.staff_uuid',
                'users.display_name',
                'users.first_name',
                'users.last_name',
            ])
            ->get()
            ->mapWithKeys(function ($row) {
                $name = trim((string) ($row->display_name ?: (($row->first_name ?? '') . ' ' . ($row->last_name ?? ''))));

                return [
                    (int) $row->id => [
                        'staff_id' => (int) $row->id,
                        'staff_uuid' => $row->staff_uuid,
                        'staff_name' => $name ?: 'Unknown Staff',
                    ],
                ];
            });

        return $rows
            ->groupBy('staff_id')
            ->map(function (Collection $group, int $staffId) use ($staffMap) {
                $revenue = round($group->sum('net_amount'), 2);
                $invoiceCount = $group->pluck('billing_cycle_id')->unique()->count();

                return [
                    'staff_id' => $staffId,
                    'staff_uuid' => $staffMap[$staffId]['staff_uuid'] ?? null,
                    'staff_name' => $staffMap[$staffId]['staff_name'] ?? 'Unknown Staff',
                    'invoice_count' => $invoiceCount,
                    'net_revenue' => $revenue,
                    'average_bill_value' => $invoiceCount > 0 ? round($revenue / $invoiceCount, 2) : 0.0,
                ];
            })
            ->sortByDesc('net_revenue')
            ->take($top)
            ->values()
            ->toArray();
    }
protected function buildCollections(Collection $activeCycles, CarbonInterface $asOf): array
{
    $outstandingCycles = $activeCycles
        ->filter(fn ($row) => $this->safeNumber($row['balance_amount'] ?? 0) > 0);

    $agingBuckets = [
        '0_30' => ['label' => '0-30 days', 'min' => 0, 'max' => 30, 'amount' => 0.0, 'count' => 0],
        '31_60' => ['label' => '31-60 days', 'min' => 31, 'max' => 60, 'amount' => 0.0, 'count' => 0],
        '61_90' => ['label' => '61-90 days', 'min' => 61, 'max' => 90, 'amount' => 0.0, 'count' => 0],
        '90_plus' => ['label' => '90+ days', 'min' => 91, 'max' => null, 'amount' => 0.0, 'count' => 0],
    ];

    foreach ($outstandingCycles as $row) {
        $daysOutstanding = $this->resolveDaysOutstanding($row, $asOf);
        $amount = round($this->safeNumber($row['balance_amount'] ?? 0), 2);

        if ($daysOutstanding <= 30) {
            $agingBuckets['0_30']['amount'] += $amount;
            $agingBuckets['0_30']['count']++;
        } elseif ($daysOutstanding <= 60) {
            $agingBuckets['31_60']['amount'] += $amount;
            $agingBuckets['31_60']['count']++;
        } elseif ($daysOutstanding <= 90) {
            $agingBuckets['61_90']['amount'] += $amount;
            $agingBuckets['61_90']['count']++;
        } else {
            $agingBuckets['90_plus']['amount'] += $amount;
            $agingBuckets['90_plus']['count']++;
        }
    }

    // Calculate total revenue using grand_total_amount
    $totalRevenue = round(
        $activeCycles->sum(fn ($row) => $this->safeNumber($row['net_amount'] ?? 0)), 
        2
    );
    
    // Calculate total collections - sum of all payments received regardless of status
    $totalCollections = round(
        $activeCycles->sum(fn ($row) => $this->safeNumber($row['total_paid_amount'] ?? 0)),
        2
    );
    
    // Alternative: Calculate collections from paid statuses
    // First, let's see what statuses we actually have
    $statusCounts = [];
    
    // Define statuses that indicate payment has been received (based on your enum)
    $paidStatuses = [
        'partially_paid',
        'paid_in_full', 
        'payment_plan',
    ];
    
    $collectionsFromPaidStatuses = 0;
    $cyclesWithPaidStatuses = 0;
    
    foreach ($activeCycles as $cycle) {
        $status = $cycle['billing_status'] ?? '';
        $statusCounts[$status] = ($statusCounts[$status] ?? 0) + 1;
        
        // Check if this status should be considered "paid"
        if (in_array($status, $paidStatuses)) {
            $collectionsFromPaidStatuses += $this->safeNumber($cycle['total_paid_amount'] ?? 0);
            $cyclesWithPaidStatuses++;
        }
    }
    
    $collectionsFromPaidStatuses = round($collectionsFromPaidStatuses, 2);
    
    $outstandingBalance = round(
        $outstandingCycles->sum(fn ($row) => $this->safeNumber($row['balance_amount'] ?? 0)), 
        2
    );

    $statusDistribution = $activeCycles
        ->groupBy('billing_status')
        ->map(function (Collection $rows, string $status) {
            return [
                'billing_status' => $status,
                'count' => $rows->count(),
                'amount' => round($rows->sum(fn ($row) => $this->safeNumber($row['net_amount'] ?? 0)), 2),
            ];
        })
        ->sortByDesc('count')
        ->values()
        ->toArray();

    // Debug information - check your logs to see what's happening
    \Illuminate\Support\Facades\Log::info('Collection Debug Info', [
        'total_cycles_count' => $activeCycles->count(),
        'total_revenue' => $totalRevenue,
        'total_collections_all' => $totalCollections,
        'collections_from_paid_statuses' => $collectionsFromPaidStatuses,
        'cycles_with_paid_statuses' => $cyclesWithPaidStatuses,
        'outstanding_cycles_count' => $outstandingCycles->count(),
        'outstanding_balance' => $outstandingBalance,
        'status_counts' => $statusCounts,
        'paid_statuses_used' => $paidStatuses,
        'sample_cycle' => $activeCycles->first() ? [
            'billing_status' => $activeCycles->first()['billing_status'] ?? 'null',
            'grand_total_amount' => $activeCycles->first()['grand_total_amount'] ?? 0,
            'total_paid_amount' => $activeCycles->first()['total_paid_amount'] ?? 0,
            'balance_amount' => $activeCycles->first()['balance_amount'] ?? 0,
        ] : null,
    ]);

    return [
        'summary' => [
            'total_outstanding_balance' => $outstandingBalance,
            'pending_invoices_count' => $outstandingCycles->count(),
            'average_days_outstanding' => round(
                $outstandingCycles->avg(fn ($row) => $this->resolveDaysOutstanding($row, $asOf)) ?? 0,
                2
            ),
            // Try both methods to see which one works
            'collection_rate_percentage' => $totalRevenue > 0
                ? round(($totalCollections / $totalRevenue) * 100, 2)
                : 0.0,
            'collection_rate_from_paid_statuses' => $totalRevenue > 0
                ? round(($collectionsFromPaidStatuses / $totalRevenue) * 100, 2)
                : 0.0,
        ],
        'aging' => array_values(array_map(function (array $bucket) {
            $bucket['amount'] = round($bucket['amount'], 2);
            return $bucket;
        }, $agingBuckets)),
        'status_distribution' => $statusDistribution,
    ];
}

    protected function buildPaymentMix(Collection $activeCycles): array
    {
        $paymentMethodSummary = [];
        $patientContribution = round($activeCycles->sum(fn ($row) => $this->safeNumber($row['patient_payment_received'] ?? 0)), 2);
        $insuranceContribution = round($activeCycles->sum(fn ($row) => $this->safeNumber($row['insurance_payment_received'] ?? 0)), 2);

        $overpaymentOccurrences = 0;
        $overpaymentAmount = 0.0;

        foreach ($activeCycles as $row) {
            $paymentMethods = is_array($row['payment_methods_decoded'] ?? null)
                ? $row['payment_methods_decoded']
                : [];

            foreach ($paymentMethods as $method) {
                $type = strtolower((string) ($method['type'] ?? 'other'));
                $amount = round($this->safeNumber($method['amount'] ?? 0), 2);

                if (!isset($paymentMethodSummary[$type])) {
                    $paymentMethodSummary[$type] = [
                        'payment_method' => $type,
                        'amount' => 0.0,
                        'count' => 0,
                    ];
                }

                $paymentMethodSummary[$type]['amount'] += $amount;
                $paymentMethodSummary[$type]['count']++;
            }

            $rawPaid = $this->safeNumber($row['raw_total_paid_before_cap'] ?? 0);
            $totalPaidAmount = $this->safeNumber($row['subtotal_amount'] ?? 0);

            if ($rawPaid > $totalPaidAmount) {
                $overpaymentOccurrences++;
                $overpaymentAmount += ($rawPaid - $totalPaidAmount);
            }
        }

        $totalPaymentMix = collect($paymentMethodSummary)->sum('amount');

        return [
            'methods' => collect($paymentMethodSummary)
                ->map(function (array $row) use ($totalPaymentMix) {
                    $row['amount'] = round($row['amount'], 2);
                    $row['share_percentage'] = $totalPaymentMix > 0
                        ? round(($row['amount'] / $totalPaymentMix) * 100, 2)
                        : 0.0;

                    return $row;
                })
                ->sortByDesc('amount')
                ->values()
                ->toArray(),
            'summary' => [
                'patient_contribution' => $patientContribution,
                'insurance_contribution' => $insuranceContribution,
                'overpayment_occurrences' => $overpaymentOccurrences,
                'overpayment_amount' => round($overpaymentAmount, 2),
            ],
        ];
    }

    protected function buildInventoryLeakage(Collection $adjustments, Collection $lineItems, int $top): array
    {
        $inventoryBilling = $lineItems
            ->filter(fn ($row) => !empty($row['inventory_item_id']))
            ->groupBy('inventory_item_id')
            ->map(fn (Collection $rows) => [
                'billed_quantity' => round($rows->sum(fn ($row) => $this->safeNumber($row['quantity'] ?? 0)), 2),
                'billed_amount' => round($rows->sum(fn ($row) => $this->safeNumber($row['net_amount'] ?? 0)), 2),
            ]);

        $inventoryLeakage = [];

        foreach ($adjustments as $adjustment) {
            $affectedItems = is_array($adjustment['affected_line_items'] ?? null)
                ? $adjustment['affected_line_items']
                : [];
            $restoredItems = is_array($adjustment['inventory_restored'] ?? null)
                ? $adjustment['inventory_restored']
                : [];

            $restoredMap = collect($restoredItems)->keyBy(function ($row) {
                return (string) ($row['line_item_id'] ?? $row['matched_reference_id'] ?? uniqid('restore_', true));
            });

            foreach ($affectedItems as $item) {
                $matchedType = strtolower((string) ($item['matched_reference_type'] ?? ''));
                $matchedId = $item['matched_reference_id'] ?? null;

                if ($matchedType !== 'inventory' && empty($matchedId)) {
                    continue;
                }

                $inventoryItemId = $matchedId ?: null;
                $serviceCode = (string) ($item['service_code'] ?? 'UNKNOWN');
                $serviceName = (string) ($item['service_description'] ?? $serviceCode);
                $refundQty = round($this->safeNumber($item['refund_quantity'] ?? 0), 2);
                $refundAmount = round($this->safeNumber($item['refund_subtotal'] ?? 0), 2);

                $restored = $restoredMap->get((string) ($item['line_item_id'] ?? ''), null);
                $restoredQty = round($this->safeNumber($restored['quantity_restored'] ?? 0), 2);

                $lookupKey = (string) ($inventoryItemId ?: $serviceCode);

                if (!isset($inventoryLeakage[$lookupKey])) {
                    $inventoryLeakage[$lookupKey] = [
                        'inventory_item_id' => $inventoryItemId ? (int) $inventoryItemId : null,
                        'item_code' => $serviceCode,
                        'item_name' => $serviceName,
                        'billed_quantity' => round($this->safeNumber($inventoryBilling[$inventoryItemId]['billed_quantity'] ?? 0), 2),
                        'billed_amount' => round($this->safeNumber($inventoryBilling[$inventoryItemId]['billed_amount'] ?? 0), 2),
                        'refunded_quantity' => 0.0,
                        'restored_quantity' => 0.0,
                        'leakage_amount' => 0.0,
                        'adjustment_count' => 0,
                    ];
                }

                $inventoryLeakage[$lookupKey]['refunded_quantity'] += $refundQty;
                $inventoryLeakage[$lookupKey]['restored_quantity'] += $restoredQty;
                $inventoryLeakage[$lookupKey]['leakage_amount'] += $refundAmount;
                $inventoryLeakage[$lookupKey]['adjustment_count']++;
            }
        }

        return collect($inventoryLeakage)
            ->map(function (array $row) {
                $row['refunded_quantity'] = round($row['refunded_quantity'], 2);
                $row['restored_quantity'] = round($row['restored_quantity'], 2);
                $row['leakage_amount'] = round($row['leakage_amount'], 2);

                return $row;
            })
            ->sortByDesc('leakage_amount')
            ->take($top)
            ->values()
            ->toArray();
    }

    protected function buildRefundMetricsByService(Collection $adjustments): array
    {
        $metrics = [];

        foreach ($adjustments as $adjustment) {
            $affectedItems = is_array($adjustment['affected_line_items'] ?? null)
                ? $adjustment['affected_line_items']
                : [];

            foreach ($affectedItems as $item) {
                $serviceCode = strtoupper((string) ($item['service_code'] ?? 'UNKNOWN'));
                $refundAmount = round($this->safeNumber($item['refund_subtotal'] ?? 0), 2);

                if (!isset($metrics[$serviceCode])) {
                    $metrics[$serviceCode] = [
                        'refund_count' => 0,
                        'refund_amount' => 0.0,
                    ];
                }

                $metrics[$serviceCode]['refund_count']++;
                $metrics[$serviceCode]['refund_amount'] += $refundAmount;
            }
        }

        return $metrics;
    }

    protected function getEffectiveAdjustmentAmount(array $adjustment): float
    {
        $type = (string) ($adjustment['adjustment_type'] ?? '');

        if ($type === 'void_transaction') {
            return round($this->safeNumber($adjustment['original_amount'] ?? 0), 2);
        }

        return round($this->safeNumber($adjustment['adjustment_amount'] ?? 0), 2);
    }

    protected function resolveDaysOutstanding(array $cycle, CarbonInterface $asOf): int
    {
        if (!empty($cycle['days_outstanding'])) {
            return (int) $cycle['days_outstanding'];
        }

        if (!empty($cycle['payment_due_date'])) {
            $dueDate = Carbon::parse($cycle['payment_due_date']);
            return max(0, $dueDate->diffInDays($asOf, false) * -1);
        }

        return max(0, Carbon::parse($cycle['event_at'])->diffInDays($asOf));
    }

    protected function getPeriodKey(CarbonInterface $date, string $groupBy): string
    {
        return match ($groupBy) {
            'week' => $date->copy()->startOfWeek()->format('Y-m-d'),
            'month' => $date->format('Y-m'),
            default => $date->format('Y-m-d'),
        };
    }

    protected function getPeriodLabel(CarbonInterface $date, string $groupBy): string
    {
        return match ($groupBy) {
            'week' => $date->copy()->startOfWeek()->format('d M Y') . ' - ' . $date->copy()->endOfWeek()->format('d M Y'),
            'month' => $date->format('M Y'),
            default => $date->format('d M Y'),
        };
    }

    protected function percentageChange(float $previous, float $current): float
    {
        if ($previous == 0.0 && $current == 0.0) {
            return 0.0;
        }

        if ($previous == 0.0) {
            return 100.0;
        }

        return round((($current - $previous) / $previous) * 100, 2);
    }

    protected function median(Collection $values): float
    {
        $sorted = $values->map(fn ($v) => (float) $v)->sort()->values();
        $count = $sorted->count();

        if ($count === 0) {
            return 0.0;
        }

        $middle = intdiv($count, 2);

        if ($count % 2 === 0) {
            return round((($sorted[$middle - 1] ?? 0) + ($sorted[$middle] ?? 0)) / 2, 2);
        }

        return round((float) ($sorted[$middle] ?? 0), 2);
    }

    protected function safeNumber(mixed $value): float
    {
        return round((float) ($value ?? 0), 2);
    }

    protected function decodeJsonish(mixed $value, mixed $default = []): mixed
    {
        if ($value === null || $value === '') {
            return $default;
        }

        if (is_array($value)) {
            return $value;
        }

        if (is_object($value)) {
            return (array) $value;
        }

        if (!is_string($value)) {
            return $default;
        }

        $decoded = json_decode($value, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return $default;
        }

        if (is_string($decoded)) {
            $decodedTwice = json_decode($decoded, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decodedTwice;
            }
        }

        return $decoded;
    }
}
