<?php

declare(strict_types=1);

namespace App\Services\Pharmacy;

use App\Models\BillingCycle;
use App\Models\InventoryItem;
use App\Models\MedicationDispense;
use App\Models\Prescription;
use App\Models\Staff;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Aggregates facility-scoped pharmacy operations for the intelligence dashboard.
 *
 * Revenue: Prefer {@see invoice_line_items} rows under {@see billing_cycles} for the facility —
 * those lines are how billing stores billable services/products (inventory_item_id links stocked meds).
 * When `medication_dispenses` is missing (migrations not applied), prescription `dispensed_at` + billing lines are used.
 */
class PharmacyDashboardService
{
    /** Set per request in {@see getDashboard()}. */
    private bool $hasMedicationDispensesTable = false;

    private bool $hasPharmacyBillingPipeline = false;

    public function getDashboard(int $facilityId, ?string $tz = null): array
    {
        try {
            $this->hasMedicationDispensesTable = Schema::hasTable('medication_dispenses');
            $this->hasPharmacyBillingPipeline = Schema::hasTable('invoice_line_items')
                && Schema::hasTable('billing_cycles');

            $now = $tz ? Carbon::now($tz) : Carbon::now();
            $today = $now->toDateString();
            $yesterday = $now->copy()->subDay()->toDateString();
            $startOfMonth = $now->copy()->startOfMonth();
            $thirtyDaysAgo = $now->copy()->subDays(30);

            $totalActiveItems = InventoryItem::query()
                ->where('facility_id', $facilityId)
                ->where('status', 'active')
                ->count();

            $itemsAtMonthStart = InventoryItem::query()
                ->where('facility_id', $facilityId)
                ->where('status', 'active')
                ->where('created_at', '<', $startOfMonth)
                ->count();

            $newItemsThisMonth = InventoryItem::query()
                ->where('facility_id', $facilityId)
                ->where('status', 'active')
                ->where('created_at', '>=', $startOfMonth)
                ->count();

            $stockItemChangePct = $itemsAtMonthStart > 0
                ? round(($newItemsThisMonth / $itemsAtMonthStart) * 100, 1)
                : ($newItemsThisMonth > 0 ? 100.0 : 0.0);

            $stockSnapshot = $this->getInventoryStockSnapshot($facilityId);

            $dispensedToday = $this->countDispenseEventsForDate($facilityId, $today);
            $dispensedYesterday = $this->countDispenseEventsForDate($facilityId, $yesterday);

            $dispensedChangePct = $dispensedYesterday > 0
                ? round((($dispensedToday - $dispensedYesterday) / $dispensedYesterday) * 100, 1)
                : ($dispensedToday > 0 ? 100.0 : 0.0);

            $rxReadyToday = Prescription::query()
                ->where('facility_id', $facilityId)
                ->whereIn('status', [
                    'Active - Ready for Dispensing',
                    'Partially Dispensed',
                    'Draft - Not Yet Finalized',
                ])
                ->count();

            [$dispensingSafetyRate, $dispTotalTodayCount] = $this->dispensingSafetyMetricsForDate($facilityId, $today);

            $pendingCheckouts = BillingCycle::query()
                ->where('facility_id', $facilityId)
                ->whereIn('billing_status', [
                    'draft',
                    'pending_review',
                    'pending_submission',
                    'submitted_to_insurance',
                    'partially_paid',
                    'payment_plan',
                    'disputed',
                ])
                ->count();

            [$revenueToday, $revenueByDay, $revenueSourceLabel] = $this->resolvePharmacyRevenueSeries(
                $facilityId,
                $today,
                $thirtyDaysAgo
            );

            $avgDailyRevenue30 = $revenueByDay->count() > 0
                ? round((float) $revenueByDay->sum() / max(1, $revenueByDay->count()), 2)
                : 0.0;

            $revenueVsAvgPct = $avgDailyRevenue30 > 0
                ? round((($revenueToday - $avgDailyRevenue30) / $avgDailyRevenue30) * 100, 1)
                : ($revenueToday > 0 ? 100.0 : 0.0);

            $uniquePatientsToday = $this->countUniquePatientsFromPharmacySalesToday($facilityId, $today);

            $prescriptionActivity = $this->buildWeeklyPrescriptionActivity($facilityId, $now);
            $inventoryTrends = $this->buildInventoryTrends($facilityId, $now);
            $recentActivity = $this->buildRecentActivity($facilityId);
            $performance = $this->buildPerformance(
                $facilityId,
                $now,
                $dispensingSafetyRate,
                (float) ($prescriptionActivity['totals']['completion_rate_pct'] ?? 0),
                $uniquePatientsToday,
                $revenueToday,
                $avgDailyRevenue30
            );

            return [
                'success' => true,
                'message' => 'Pharmacy dashboard retrieved successfully.',
                'data' => [
                    'summary' => [
                        'total_stock_items' => [
                            'value' => $totalActiveItems,
                            'change_pct' => $stockItemChangePct,
                            'change_label' => 'new SKUs vs start of month (est.)',
                        ],
                        'low_stock_alerts' => [
                            'value' => $stockSnapshot['low_stock'],
                            'change' => null,
                            'change_label' => 'SKUs at or below reorder / safety stock',
                        ],
                        'dispensed_today' => [
                            'value' => $dispensedToday,
                            'change_pct' => $dispensedChangePct,
                            'change_label' => 'vs yesterday',
                            'secondary_label' => $rxReadyToday > 0
                                ? sprintf('queue ~%d active Rx', $rxReadyToday)
                                : null,
                        ],
                        'pending_checkouts' => [
                            'value' => $pendingCheckouts,
                            'change_label' => 'billing cycles awaiting settlement',
                        ],
                        'revenue_today' => [
                            'value' => round($revenueToday, 2),
                            'currency' => 'Facility default',
                            'change_pct_vs_avg_daily' => $revenueVsAvgPct,
                            'change_label' => $revenueSourceLabel,
                        ],
                        'out_of_stock_items' => $stockSnapshot['out_of_stock'],
                    ],
                    'prescription_activity' => $prescriptionActivity,
                    'inventory_trends' => $inventoryTrends,
                    'recent_activity' => $recentActivity,
                    'performance' => $performance,
                    'generated_at' => $now->toIso8601String(),
                    'data_sources' => [
                        'medication_dispenses_table' => $this->hasMedicationDispensesTable,
                        'revenue' => $this->hasPharmacyBillingPipeline
                            ? 'invoice_line_items.net_amount (lines with inventory / medication items)'
                            : ($this->hasMedicationDispensesTable
                                ? 'medication_dispenses charges'
                                : 'unavailable'),
                        'dispense_counts' => $this->hasMedicationDispensesTable
                            ? 'medication_dispenses rows'
                            : 'prescriptions.dispensed_at',
                    ],
                ],
            ];
        } catch (Throwable $e) {
            Log::error('Pharmacy dashboard aggregation failed', [
                'facility_id' => $facilityId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to build pharmacy dashboard.',
                'error' => config('app.debug') ? $e->getMessage() : 'SERVER_ERROR',
            ];
        }
    }

    /**
     * Pharmacy-related invoice lines: child rows of `billing_cycles`, stored in `invoice_line_items`
     * (line_total / net_amount, optional link to `inventory_items` for dispensed product).
     */
    protected function pharmacyInvoiceBaseQuery(int $facilityId)
    {
        $q = DB::table('invoice_line_items as ili')
            ->join('billing_cycles as bc', 'bc.id', '=', 'ili.billing_cycle_id')
            ->leftJoin('inventory_items as ii', 'ii.id', '=', 'ili.inventory_item_id')
            ->where('bc.facility_id', $facilityId);

        if (Schema::hasColumn('invoice_line_items', 'deleted_at')) {
            $q->whereNull('ili.deleted_at');
        }

        $q->where(function ($w): void {
            $w->where('ii.item_category', 'medication')
                ->orWhereNotNull('ili.inventory_item_id');
        });

        return $q;
    }

    /**
     * @return array{0: float, 1: \Illuminate\Support\Collection<int|string, mixed>, 2: string}
     */
    protected function resolvePharmacyRevenueSeries(int $facilityId, string $today, Carbon $thirtyDaysAgo): array
    {
        if ($this->hasPharmacyBillingPipeline) {
            $todayAmt = $this->sumPharmacyInvoiceNetForDate($facilityId, $today);
            $byDay = $this->pharmacyInvoiceNetGroupedByDaySince($facilityId, $thirtyDaysAgo);

            return [
                $todayAmt,
                $byDay,
                'vs 30-day avg daily (billing: invoice_line_items.net_amount)',
            ];
        }

        if ($this->hasMedicationDispensesTable) {
            $todayAmt = (float) (MedicationDispense::query()
                ->where('facility_id', $facilityId)
                ->whereDate('dispensed_at', $today)
                ->selectRaw('COALESCE(SUM(COALESCE(total_cost_to_patient,0) + COALESCE(insurance_payment,0)),0) as t')
                ->value('t') ?? 0);

            $byDay = MedicationDispense::query()
                ->where('facility_id', $facilityId)
                ->where('dispensed_at', '>=', $thirtyDaysAgo)
                ->whereNotNull('dispensed_at')
                ->selectRaw('DATE(dispensed_at) as d, COALESCE(SUM(COALESCE(total_cost_to_patient,0) + COALESCE(insurance_payment,0)),0) as amt')
                ->groupBy('d')
                ->pluck('amt', 'd');

            return [$todayAmt, $byDay, 'vs 30-day avg daily (medication_dispenses)'];
        }

        return [0.0, collect(), 'revenue unavailable (create billing + inventory line items, or run dispense migrations)'];
    }

    protected function sumPharmacyInvoiceNetForDate(int $facilityId, string $date): float
    {
        if (! $this->hasPharmacyBillingPipeline) {
            return 0.0;
        }

        return (float) ($this->pharmacyInvoiceBaseQuery($facilityId)
            ->whereDate('ili.service_performed_at', $date)
            ->sum('ili.net_amount') ?? 0);
    }

    /**
     * @return \Illuminate\Support\Collection<int|string, mixed>
     */
    protected function pharmacyInvoiceNetGroupedByDaySince(int $facilityId, Carbon $since)
    {
        if (! $this->hasPharmacyBillingPipeline) {
            return collect();
        }

        $rows = $this->pharmacyInvoiceBaseQuery($facilityId)
            ->where('ili.service_performed_at', '>=', $since)
            ->selectRaw('DATE(ili.service_performed_at) as d, COALESCE(SUM(ili.net_amount),0) as amt')
            ->groupBy('d')
            ->get();

        return $rows->pluck('amt', 'd');
    }

    protected function countDispenseEventsForDate(int $facilityId, string $date): int
    {
        if ($this->hasMedicationDispensesTable) {
            return (int) MedicationDispense::query()
                ->where('facility_id', $facilityId)
                ->whereDate('dispensed_at', $date)
                ->count();
        }

        return (int) Prescription::query()
            ->where('facility_id', $facilityId)
            ->whereNotNull('dispensed_at')
            ->whereDate('dispensed_at', $date)
            ->count();
    }

    /**
     * @return array{0: float, 1: int} [safety_rate_pct, dispense_count_today]
     */
    protected function dispensingSafetyMetricsForDate(int $facilityId, string $today): array
    {
        if (! $this->hasMedicationDispensesTable) {
            return [100.0, 0];
        }

        $total = (int) MedicationDispense::query()
            ->where('facility_id', $facilityId)
            ->whereDate('dispensed_at', $today)
            ->count();

        $pass = (int) MedicationDispense::query()
            ->where('facility_id', $facilityId)
            ->whereDate('dispensed_at', $today)
            ->where('all_safety_checks_passed', true)
            ->count();

        $rate = $total > 0 ? round(($pass / $total) * 100, 1) : 100.0;

        return [$rate, $total];
    }

    protected function countUniquePatientsFromPharmacySalesToday(int $facilityId, string $today): int
    {
        if ($this->hasPharmacyBillingPipeline) {
            return (int) $this->pharmacyInvoiceBaseQuery($facilityId)
                ->whereDate('ili.service_performed_at', $today)
                ->selectRaw('COUNT(DISTINCT bc.patient_id) as c')
                ->value('c');
        }

        if ($this->hasMedicationDispensesTable) {
            return (int) MedicationDispense::query()
                ->where('facility_id', $facilityId)
                ->whereDate('dispensed_at', $today)
                ->distinct()
                ->count('patient_id');
        }

        return (int) Prescription::query()
            ->where('facility_id', $facilityId)
            ->whereNotNull('dispensed_at')
            ->whereDate('dispensed_at', $today)
            ->distinct()
            ->count('patient_id');
    }

    protected function sumPharmacyInvoiceQuantityForDate(int $facilityId, string $date): float
    {
        if (! $this->hasPharmacyBillingPipeline) {
            return 0.0;
        }

        return (float) ($this->pharmacyInvoiceBaseQuery($facilityId)
            ->whereDate('ili.service_performed_at', $date)
            ->sum('ili.quantity') ?? 0);
    }

    protected function verificationRateLast7Days(int $facilityId, Carbon $now): float
    {
        $sevenDaysAgo = $now->copy()->subDays(7);

        if ($this->hasMedicationDispensesTable) {
            $totalDispenses = MedicationDispense::query()
                ->where('facility_id', $facilityId)
                ->where('dispensed_at', '>=', $sevenDaysAgo)
                ->count();

            $verified = MedicationDispense::query()
                ->where('facility_id', $facilityId)
                ->where('dispensed_at', '>=', $sevenDaysAgo)
                ->whereNotNull('checked_by_staff_id')
                ->count();

            return $totalDispenses > 0 ? round(($verified / $totalDispenses) * 100, 1) : 100.0;
        }

        if ($this->hasPharmacyBillingPipeline && Schema::hasColumn('invoice_line_items', 'coding_reviewed')) {
            $total = (int) $this->pharmacyInvoiceBaseQuery($facilityId)
                ->where('ili.service_performed_at', '>=', $sevenDaysAgo)
                ->count();

            $coded = (int) $this->pharmacyInvoiceBaseQuery($facilityId)
                ->where('ili.service_performed_at', '>=', $sevenDaysAgo)
                ->where('ili.coding_reviewed', true)
                ->count();

            return $total > 0 ? round(($coded / $total) * 100, 1) : 100.0;
        }

        return 100.0;
    }

    /**
     * Current low / out-of-stock SKU counts from latest ledger balances.
     *
     * @return array{low_stock: int, out_of_stock: int, total_units: float}
     */
    protected function getInventoryStockSnapshot(int $facilityId): array
    {
        if (! Schema::hasTable('inventory_ledger') || ! Schema::hasTable('inventory_items')) {
            return [
                'low_stock' => 0,
                'out_of_stock' => 0,
                'total_units' => 0.0,
            ];
        }

        $rows = DB::table('inventory_ledger as l')
            ->joinSub(
                DB::table('inventory_ledger')
                    ->selectRaw('inventory_item_id, MAX(id) as max_id')
                    ->where('facility_id', $facilityId)
                    ->groupBy('inventory_item_id'),
                'x',
                function ($join): void {
                    $join->on('l.id', '=', 'x.max_id');
                }
            )
            ->join('inventory_items as i', 'i.id', '=', 'l.inventory_item_id')
            ->where('l.facility_id', $facilityId)
            ->where('i.facility_id', $facilityId)
            ->whereNull('i.deleted_at')
            ->where('i.status', 'active')
            ->select([
                'l.balance_after_transaction',
                'i.reorder_point',
                'i.safety_stock_level',
            ])
            ->get();

        $low = 0;
        $out = 0;
        $totalUnits = 0.0;

        foreach ($rows as $row) {
            $bal = (float) $row->balance_after_transaction;
            $totalUnits += $bal;
            $threshold = (int) ($row->reorder_point ?? $row->safety_stock_level ?? 0);

            if ($bal <= 0) {
                ++$out;
            } elseif ($threshold > 0 && $bal <= $threshold) {
                ++$low;
            }
        }

        return [
            'low_stock' => $low,
            'out_of_stock' => $out,
            'total_units' => round($totalUnits, 2),
        ];
    }

    protected function buildWeeklyPrescriptionActivity(int $facilityId, Carbon $now): array
    {
        $start = $now->copy()->startOfWeek(Carbon::MONDAY);
        $series = [];
        $totalRx = 0;
        $totalDisp = 0;

        for ($i = 0; $i < 7; ++$i) {
            $day = $start->copy()->addDays($i);
            $d = $day->toDateString();
            $label = $day->format('D');

            $rxCount = Prescription::query()
                ->where('facility_id', $facilityId)
                ->whereDate('created_at', $d)
                ->count();

            $dispCount = $this->countDispenseEventsForDate($facilityId, $d);

            $pending = max(0, $rxCount - $dispCount);

            $series[] = [
                'day' => $label,
                'date' => $d,
                'prescriptions' => $rxCount,
                'dispensed' => $dispCount,
                'pending' => $pending,
            ];

            $totalRx += $rxCount;
            $totalDisp += $dispCount;
        }

        $completion = $totalRx > 0
            ? round(min(100.0, ($totalDisp / max(1, $totalRx)) * 100), 1)
            : ($totalDisp > 0 ? 100.0 : 0.0);

        return [
            'bucket' => 'week',
            'series' => $series,
            'totals' => [
                'prescriptions_week' => $totalRx,
                'dispensed_week' => $totalDisp,
                'completion_rate_pct' => $completion,
                'avg_per_day' => round(($totalRx + $totalDisp) / 7, 1),
            ],
        ];
    }

    protected function buildInventoryTrends(int $facilityId, Carbon $now): array
    {
        $series = [];
        $days = 30;
        $totalDispensedSeries = 0.0;

        for ($i = $days - 1; $i >= 0; --$i) {
            $day = $now->copy()->subDays($i)->startOfDay();
            $d = $day->toDateString();

            if ($this->hasMedicationDispensesTable) {
                $dispensedUnits = (float) (MedicationDispense::query()
                    ->where('facility_id', $facilityId)
                    ->whereDate('dispensed_at', $d)
                    ->sum('quantity_dispensed') ?? 0);
            } else {
                $dispensedUnits = $this->sumPharmacyInvoiceQuantityForDate($facilityId, $d);
            }

            $restockUnits = Schema::hasTable('inventory_ledger')
                ? (float) DB::table('inventory_ledger')
                    ->where('facility_id', $facilityId)
                    ->whereDate('transaction_timestamp', $d)
                    ->whereIn('transaction_type', ['purchase', 'adjustment_increase', 'transfer_in'])
                    ->sum('quantity_change')
                : 0.0;

            $consumptionUnits = Schema::hasTable('inventory_ledger')
                ? abs((float) DB::table('inventory_ledger')
                    ->where('facility_id', $facilityId)
                    ->whereDate('transaction_timestamp', $d)
                    ->whereIn('transaction_type', ['consumption_visit', 'consumption_waste', 'adjustment_decrease'])
                    ->where('quantity_change', '<', 0)
                    ->sum('quantity_change'))
                : 0.0;

            $totalDispensedSeries += $dispensedUnits;

            $series[] = [
                'date' => $d,
                'label' => $day->format('M j'),
                'dispensed_units' => round($dispensedUnits, 2),
                'restock_units' => round(max(0, $restockUnits), 2),
                'consumption_units' => round($consumptionUnits, 2),
            ];
        }

        $current = $this->getInventoryStockSnapshot($facilityId);
        $firstHalf = array_slice($series, 0, (int) floor($days / 2));
        $secondHalf = array_slice($series, (int) floor($days / 2));
        $volFirst = $this->sumColumn($firstHalf, 'dispensed_units')
            + $this->sumColumn($firstHalf, 'restock_units')
            + $this->sumColumn($firstHalf, 'consumption_units');
        $volSecond = $this->sumColumn($secondHalf, 'dispensed_units')
            + $this->sumColumn($secondHalf, 'restock_units')
            + $this->sumColumn($secondHalf, 'consumption_units');
        $growthPct = $volFirst > 0
            ? round((($volSecond - $volFirst) / $volFirst) * 100, 1)
            : 0.0;

        return [
            'days' => $days,
            'series' => $series,
            'footer' => [
                'avg_stock_units' => $current['total_units'],
                'avg_low_stock' => $current['low_stock'],
                'avg_out_of_stock' => $current['out_of_stock'],
                'stock_growth_pct' => $growthPct,
                'avg_daily_dispensed_units' => round($totalDispensedSeries / $days, 2),
                'note' => $this->hasMedicationDispensesTable
                    ? 'Dispensed units from medication_dispenses; inventory from ledger.'
                    : 'Dispensed units estimated from pharmacy invoice_line_items.quantity; ledger when present.',
            ],
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    protected function sumColumn(array $rows, string $key): float
    {
        $sum = 0.0;
        foreach ($rows as $r) {
            $sum += (float) ($r[$key] ?? 0);
        }

        return $sum;
    }

    protected function buildPerformance(
        int $facilityId,
        Carbon $now,
        float $dispensingSafetyRate,
        float $weeklyCompletionPct,
        int $uniquePatientsToday,
        float $revenueToday,
        float $avgDailyRevenue30
    ): array {
        $verificationRate = $this->verificationRateLast7Days($facilityId, $now);

        $revenueProgressPct = $avgDailyRevenue30 > 0
            ? round(min(100, ($revenueToday / $avgDailyRevenue30) * 100), 1)
            : ($revenueToday > 0 ? 100.0 : 0.0);

        $avgScore = ($dispensingSafetyRate + $weeklyCompletionPct + $verificationRate + $revenueProgressPct) / 4;
        $grade = $this->scoreToLetter($avgScore);

        return [
            'dispensing_safety_rate_pct' => $dispensingSafetyRate,
            'prescription_completion_pct' => $weeklyCompletionPct,
            'verification_rate_pct' => $verificationRate,
            'avg_wait_minutes' => null,
            'daily_patients' => $uniquePatientsToday,
            'revenue_target_pct' => $revenueProgressPct,
            'overall_grade' => $grade,
            'overall_label' => $avgScore >= 70 ? 'Solid operations' : 'Review bottlenecks',
        ];
    }

    protected function scoreToLetter(float $score): string
    {
        if ($score >= 93) {
            return 'A';
        }
        if ($score >= 85) {
            return 'A-';
        }
        if ($score >= 78) {
            return 'B+';
        }
        if ($score >= 70) {
            return 'B';
        }
        if ($score >= 60) {
            return 'C';
        }

        return 'D';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function buildRecentActivity(int $facilityId): array
    {
        $events = [];

        if ($this->hasMedicationDispensesTable) {
            $dispenses = MedicationDispense::query()
                ->where('facility_id', $facilityId)
                ->orderByDesc('dispensed_at')
                ->limit(12)
                ->get(['id', 'dispensed_at', 'prescription_details_snapshot', 'prescription_id', 'dispensed_by_staff_id', 'quantity_dispensed']);

            foreach ($dispenses as $d) {
                $meta = $d->prescription_details_snapshot;
                $medName = is_array($meta) ? ($meta['medication_name'] ?? $meta['drug_name'] ?? null) : null;
                $label = $medName ?: ('Prescription #'.$d->prescription_id);

                $staffName = $this->resolveStaffUserName($d->dispensed_by_staff_id);

                $events[] = [
                    'id' => 'dispense-'.$d->id,
                    'type' => 'dispensed',
                    'title' => 'Medication dispensed',
                    'description' => sprintf('%s · qty %s', $label, $d->quantity_dispensed),
                    'occurred_at' => optional($d->dispensed_at)?->toIso8601String(),
                    'actor_name' => $staffName,
                ];
            }
        } elseif ($this->hasPharmacyBillingPipeline) {
            $lines = $this->pharmacyInvoiceBaseQuery($facilityId)
                ->orderByDesc('ili.service_performed_at')
                ->limit(10)
                ->get([
                    'ili.id',
                    'ili.service_performed_at',
                    'ili.service_description',
                    'ili.net_amount',
                    'ili.quantity',
                ]);

            foreach ($lines as $line) {
                $events[] = [
                    'id' => 'inv-line-'.((string) ($line->id ?? uniqid('', true))),
                    'type' => 'checkout',
                    'title' => 'Pharmacy charge posted',
                    'description' => sprintf(
                        '%s · net %s · qty %s',
                        $line->service_description ?? 'Line item',
                        number_format((float) $line->net_amount, 2),
                        $line->quantity ?? '—'
                    ),
                    'occurred_at' => $line->service_performed_at
                        ? Carbon::parse($line->service_performed_at)->toIso8601String()
                        : null,
                    'actor_name' => 'Billing',
                ];
            }
        }

        $prescriptions = Prescription::query()
            ->where('facility_id', $facilityId)
            ->orderByDesc('created_at')
            ->limit(8)
            ->get(['id', 'created_at', 'prescription_number', 'prescribed_by']);

        foreach ($prescriptions as $p) {
            $prescriber = $p->prescribed_by ? User::query()->find($p->prescribed_by) : null;
            $name = $prescriber
                ? ($prescriber->full_name ?? $prescriber->display_name ?? 'Prescriber')
                : 'Prescriber';

            $events[] = [
                'id' => 'rx-'.$p->id,
                'type' => 'prescription',
                'title' => 'Prescription activity',
                'description' => sprintf('Rx %s logged', $p->prescription_number ?? '#'.$p->id),
                'occurred_at' => optional($p->created_at)?->toIso8601String(),
                'actor_name' => $name,
            ];
        }

        $billings = BillingCycle::query()
            ->where('facility_id', $facilityId)
            ->orderByDesc('updated_at')
            ->limit(8)
            ->get(['id', 'updated_at', 'billing_status', 'grand_total_amount', 'patient_payment_received']);

        foreach ($billings as $b) {
            $events[] = [
                'id' => 'bill-'.$b->id,
                'type' => 'checkout',
                'title' => 'Billing cycle updated',
                'description' => sprintf(
                    'Status %s · balance %s',
                    $b->billing_status,
                    number_format((float) $b->grand_total_amount, 2)
                ),
                'occurred_at' => optional($b->updated_at)?->toIso8601String(),
                'actor_name' => 'Billing',
            ];
        }

        usort($events, static function (array $a, array $b): int {
            return strcmp((string) ($b['occurred_at'] ?? ''), (string) ($a['occurred_at'] ?? ''));
        });

        return array_slice($events, 0, 18);
    }

    protected function resolveStaffUserName(?int $staffId): string
    {
        if (!$staffId) {
            return 'Pharmacy staff';
        }

        $staff = Staff::query()->find($staffId);
        if (!$staff || !$staff->user_id) {
            return 'Pharmacy staff';
        }

        $user = User::query()->find($staff->user_id);

        if (! $user) {
            return 'Pharmacy staff';
        }

        return $user->full_name ?? $user->display_name ?? 'Pharmacy staff';
    }
}
