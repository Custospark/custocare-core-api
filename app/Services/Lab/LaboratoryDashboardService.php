<?php

declare(strict_types=1);

namespace App\Services\Lab;

use App\Models\LabRequest;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class LaboratoryDashboardService
{
    public function getDashboard(int $facilityId, ?string $tz = null): array
    {
        try {
            $now = $tz ? Carbon::now($tz) : Carbon::now();
            $today = $now->toDateString();
            $yesterday = $now->copy()->subDay()->toDateString();
            $sevenDaysAgo = $now->copy()->subDays(6)->startOfDay();
            $thirtyDaysAgo = $now->copy()->subDays(30)->startOfDay();

            $pendingRequests = (int) LabRequest::query()
                ->where('facility_id', $facilityId)
                ->where('status', 'pending')
                ->count();

            $inProgressItems = (int) DB::table('lab_request_items as lri')
                ->join('lab_requests as lr', 'lr.id', '=', 'lri.lab_request_id')
                ->where('lr.facility_id', $facilityId)
                ->whereIn('lri.status', ['sample_collected', 'in_progress'])
                ->whereNull('lri.deleted_at')
                ->whereNull('lr.deleted_at')
                ->count();

            $completedResultsToday = (int) DB::table('lab_results as r')
                ->join('lab_request_items as lri', 'lri.id', '=', 'r.lab_request_item_id')
                ->join('lab_requests as lr', 'lr.id', '=', 'lri.lab_request_id')
                ->where('lr.facility_id', $facilityId)
                ->whereDate('r.recorded_at', $today)
                ->whereNotNull('r.value')
                ->where('r.flag', '!=', 'pending')
                ->whereNull('r.deleted_at')
                ->whereNull('lri.deleted_at')
                ->whereNull('lr.deleted_at')
                ->count();

            $completedResultsYesterday = (int) DB::table('lab_results as r')
                ->join('lab_request_items as lri', 'lri.id', '=', 'r.lab_request_item_id')
                ->join('lab_requests as lr', 'lr.id', '=', 'lri.lab_request_id')
                ->where('lr.facility_id', $facilityId)
                ->whereDate('r.recorded_at', $yesterday)
                ->whereNotNull('r.value')
                ->where('r.flag', '!=', 'pending')
                ->whereNull('r.deleted_at')
                ->whereNull('lri.deleted_at')
                ->whereNull('lr.deleted_at')
                ->count();

            $criticalResultsToday = (int) DB::table('lab_results as r')
                ->join('lab_request_items as lri', 'lri.id', '=', 'r.lab_request_item_id')
                ->join('lab_requests as lr', 'lr.id', '=', 'lri.lab_request_id')
                ->where('lr.facility_id', $facilityId)
                ->whereDate('r.recorded_at', $today)
                ->where('r.flag', 'critical')
                ->whereNull('r.deleted_at')
                ->whereNull('lri.deleted_at')
                ->whereNull('lr.deleted_at')
                ->count();

            $avgTurnaroundHours = (float) DB::table('lab_request_items as lri')
                ->join('lab_requests as lr', 'lr.id', '=', 'lri.lab_request_id')
                ->where('lr.facility_id', $facilityId)
                ->whereNotNull('lri.collected_at')
                ->whereNotNull('lri.completed_at')
                ->where('lri.completed_at', '>=', $thirtyDaysAgo)
                ->whereNull('lri.deleted_at')
                ->whereNull('lr.deleted_at')
                ->selectRaw('COALESCE(AVG(TIMESTAMPDIFF(MINUTE, lri.collected_at, lri.completed_at))/60, 0) as avg_hours')
                ->value('avg_hours');

            $revenueToday = $this->sumLaboratoryRevenueForDate($facilityId, $today);
            $revenueYesterday = $this->sumLaboratoryRevenueForDate($facilityId, $yesterday);

            $resultsChangePct = $completedResultsYesterday > 0
                ? round((($completedResultsToday - $completedResultsYesterday) / $completedResultsYesterday) * 100, 1)
                : ($completedResultsToday > 0 ? 100.0 : 0.0);

            $revenueChangePct = $revenueYesterday > 0
                ? round((($revenueToday - $revenueYesterday) / $revenueYesterday) * 100, 1)
                : ($revenueToday > 0 ? 100.0 : 0.0);

            $requestActivity = $this->buildRequestActivity($facilityId, $sevenDaysAgo, $now);
            $resultFlags = $this->buildResultFlags($facilityId, $thirtyDaysAgo);
            $revenueTrend = $this->buildRevenueTrend($facilityId, $now);
            $topBilledServices = $this->buildTopBilledServices($facilityId, $thirtyDaysAgo);
            $recentActivity = $this->buildRecentActivity($facilityId);
            $performance = $this->buildPerformance($facilityId, $now);

            return [
                'success' => true,
                'message' => 'Laboratory dashboard retrieved successfully.',
                'data' => [
                    'summary' => [
                        'pending_requests' => [
                            'value' => $pendingRequests,
                            'change_label' => 'Awaiting laboratory processing',
                        ],
                        'in_progress_items' => [
                            'value' => $inProgressItems,
                            'change_label' => 'Samples collected or processing in progress',
                        ],
                        'completed_results_today' => [
                            'value' => $completedResultsToday,
                            'change_pct' => $resultsChangePct,
                            'change_label' => 'vs yesterday',
                        ],
                        'critical_results_today' => [
                            'value' => $criticalResultsToday,
                            'change_label' => 'Critical findings requiring immediate review',
                        ],
                        'revenue_today' => [
                            'value' => round($revenueToday, 2),
                            'change_pct' => $revenueChangePct,
                            'change_label' => 'Laboratory billable revenue vs yesterday',
                        ],
                        'avg_turnaround_hours' => [
                            'value' => round($avgTurnaroundHours, 2),
                            'change_label' => 'Average sample collection to completion (30-day window)',
                        ],
                    ],
                    'request_activity' => $requestActivity,
                    'result_flags' => $resultFlags,
                    'revenue_trend' => $revenueTrend,
                    'top_billed_services' => $topBilledServices,
                    'recent_activity' => $recentActivity,
                    'performance' => $performance,
                    'generated_at' => $now->toIso8601String(),
                ],
            ];
        } catch (Throwable $e) {
            Log::error('Laboratory dashboard aggregation failed', [
                'facility_id' => $facilityId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to build laboratory dashboard.',
                'error' => config('app.debug') ? $e->getMessage() : 'SERVER_ERROR',
            ];
        }
    }

    protected function sumLaboratoryRevenueForDate(int $facilityId, string $date): float
    {
        if (!Schema::hasTable('invoice_line_items') || !Schema::hasTable('billing_cycles')) {
            return 0.0;
        }

        return (float) $this->laboratoryInvoiceBaseQuery($facilityId)
            ->whereDate('ili.service_performed_at', $date)
            ->sum('ili.net_amount');
    }

    protected function laboratoryInvoiceBaseQuery(int $facilityId)
    {
        return DB::table('invoice_line_items as ili')
            ->join('billing_cycles as bc', 'bc.id', '=', 'ili.billing_cycle_id')
            ->leftJoin('service_catalogs as sc', 'sc.id', '=', 'ili.service_catalog_id')
            ->leftJoin('inventory_items as ii', 'ii.id', '=', 'ili.inventory_item_id')
            ->where('bc.facility_id', $facilityId)
            ->whereNull('ili.deleted_at')
            ->where(function ($q): void {
                $q->whereIn('sc.service_category', ['laboratory_test', 'pathology'])
                    ->orWhere('ii.item_category', 'laboratory_reagent')
                    ->orWhere('ili.service_description', 'like', '%lab%')
                    ->orWhere('ili.service_description', 'like', '%laboratory%');
            });
    }

    protected function buildRevenueTrend(int $facilityId, Carbon $now): array
    {
        $days = 14;
        $start = $now->copy()->subDays($days - 1)->startOfDay();
        $grouped = $this->laboratoryInvoiceBaseQuery($facilityId)
            ->where('ili.service_performed_at', '>=', $start)
            ->selectRaw('DATE(ili.service_performed_at) as d, COALESCE(SUM(ili.net_amount), 0) as amount')
            ->groupBy('d')
            ->pluck('amount', 'd');

        $series = [];
        $total = 0.0;
        for ($i = $days - 1; $i >= 0; --$i) {
            $day = $now->copy()->subDays($i)->startOfDay();
            $date = $day->toDateString();
            $amount = (float) ($grouped[$date] ?? 0);
            $total += $amount;
            $series[] = [
                'date' => $date,
                'label' => $day->format('M j'),
                'revenue' => round($amount, 2),
            ];
        }

        return [
            'days' => $days,
            'series' => $series,
            'total_revenue' => round($total, 2),
            'avg_daily_revenue' => round($total / $days, 2),
        ];
    }

    protected function buildTopBilledServices(int $facilityId, Carbon $since): array
    {
        if (!Schema::hasTable('invoice_line_items') || !Schema::hasTable('billing_cycles')) {
            return [];
        }

        $serviceNameExpr = 'COALESCE(sc.service_name, ii.item_name, ili.service_description, "Laboratory service")';

        $rows = $this->laboratoryInvoiceBaseQuery($facilityId)
            ->where('ili.service_performed_at', '>=', $since)
            ->selectRaw("
                {$serviceNameExpr} as service_name,
                SUM(COALESCE(ili.quantity, 0)) as quantity,
                SUM(COALESCE(ili.net_amount, 0)) as revenue
            ")
            ->groupByRaw($serviceNameExpr)
            ->orderByDesc('revenue')
            ->limit(8)
            ->get();

        return $rows->map(static function ($row): array {
            return [
                'service_name' => (string) $row->service_name,
                'quantity' => round((float) ($row->quantity ?? 0), 2),
                'revenue' => round((float) ($row->revenue ?? 0), 2),
            ];
        })->all();
    }

    protected function buildRequestActivity(int $facilityId, Carbon $start, Carbon $end): array
    {
        $series = [];
        $totalRequested = 0;
        $totalCompleted = 0;

        $cursor = $start->copy();
        while ($cursor <= $end) {
            $date = $cursor->toDateString();
            $requested = (int) LabRequest::query()
                ->where('facility_id', $facilityId)
                ->whereDate('requested_at', $date)
                ->whereNull('deleted_at')
                ->count();

            $completed = (int) DB::table('lab_request_items as lri')
                ->join('lab_requests as lr', 'lr.id', '=', 'lri.lab_request_id')
                ->where('lr.facility_id', $facilityId)
                ->whereDate('lri.completed_at', $date)
                ->whereNull('lri.deleted_at')
                ->whereNull('lr.deleted_at')
                ->count();

            $series[] = [
                'day' => $cursor->format('D'),
                'date' => $date,
                'requested' => $requested,
                'completed' => $completed,
                'pending' => max(0, $requested - $completed),
            ];

            $totalRequested += $requested;
            $totalCompleted += $completed;
            $cursor->addDay();
        }

        $completionRate = $totalRequested > 0
            ? round(min(100, ($totalCompleted / $totalRequested) * 100), 1)
            : 0.0;

        return [
            'bucket' => 'week',
            'series' => $series,
            'totals' => [
                'requested_week' => $totalRequested,
                'completed_week' => $totalCompleted,
                'completion_rate_pct' => $completionRate,
            ],
        ];
    }

    protected function buildResultFlags(int $facilityId, Carbon $since): array
    {
        $rows = DB::table('lab_results as r')
            ->join('lab_request_items as lri', 'lri.id', '=', 'r.lab_request_item_id')
            ->join('lab_requests as lr', 'lr.id', '=', 'lri.lab_request_id')
            ->where('lr.facility_id', $facilityId)
            ->where('r.recorded_at', '>=', $since)
            ->whereNull('r.deleted_at')
            ->whereNull('lri.deleted_at')
            ->whereNull('lr.deleted_at')
            ->select('r.flag', DB::raw('COUNT(*) as count'))
            ->groupBy('r.flag')
            ->get();

        $flagMap = [
            'normal' => 0,
            'abnormal' => 0,
            'critical' => 0,
            'pending' => 0,
        ];

        foreach ($rows as $row) {
            $flag = (string) ($row->flag ?? 'pending');
            $count = (int) ($row->count ?? 0);

            if ($flag === 'normal') {
                $flagMap['normal'] += $count;
            } elseif (in_array($flag, ['abnormal', 'high', 'low'], true)) {
                $flagMap['abnormal'] += $count;
            } elseif ($flag === 'critical') {
                $flagMap['critical'] += $count;
            } else {
                $flagMap['pending'] += $count;
            }
        }

        return $flagMap;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function buildRecentActivity(int $facilityId): array
    {
        $events = [];

        $requests = LabRequest::query()
            ->where('facility_id', $facilityId)
            ->orderByDesc('requested_at')
            ->limit(8)
            ->get(['id', 'status', 'priority', 'requested_at', 'patient_id']);

        foreach ($requests as $request) {
            $events[] = [
                'id' => 'request-'.$request->id,
                'type' => 'request',
                'title' => 'Lab request '.$request->status,
                'description' => sprintf(
                    '%s priority · Patient #%d',
                    $request->priority,
                    (int) $request->patient_id
                ),
                'occurred_at' => optional($request->requested_at)?->toIso8601String(),
            ];
        }

        $results = DB::table('lab_results as r')
            ->join('lab_request_items as lri', 'lri.id', '=', 'r.lab_request_item_id')
            ->join('lab_tests as lt', 'lt.id', '=', 'lri.lab_test_id')
            ->join('lab_requests as lr', 'lr.id', '=', 'lri.lab_request_id')
            ->where('lr.facility_id', $facilityId)
            ->whereNull('r.deleted_at')
            ->whereNull('lri.deleted_at')
            ->whereNull('lr.deleted_at')
            ->orderByDesc('r.recorded_at')
            ->limit(8)
            ->get([
                'r.id',
                'r.flag',
                'r.recorded_at',
                'lt.name as test_name',
            ]);

        foreach ($results as $result) {
            $events[] = [
                'id' => 'result-'.$result->id,
                'type' => 'result',
                'title' => 'Lab result recorded',
                'description' => sprintf(
                    '%s (%s)',
                    $result->test_name ?? 'Test',
                    strtoupper((string) $result->flag)
                ),
                'occurred_at' => $result->recorded_at
                    ? Carbon::parse($result->recorded_at)->toIso8601String()
                    : null,
            ];
        }

        usort($events, static function (array $a, array $b): int {
            return strcmp((string) ($b['occurred_at'] ?? ''), (string) ($a['occurred_at'] ?? ''));
        });

        return array_slice($events, 0, 12);
    }

    protected function buildPerformance(int $facilityId, Carbon $now): array
    {
        $windowStart = $now->copy()->subDays(30)->startOfDay();

        $totalResults = (int) DB::table('lab_results as r')
            ->join('lab_request_items as lri', 'lri.id', '=', 'r.lab_request_item_id')
            ->join('lab_requests as lr', 'lr.id', '=', 'lri.lab_request_id')
            ->where('lr.facility_id', $facilityId)
            ->where('r.recorded_at', '>=', $windowStart)
            ->whereNull('r.deleted_at')
            ->whereNull('lri.deleted_at')
            ->whereNull('lr.deleted_at')
            ->count();

        $verifiedResults = (int) DB::table('lab_results as r')
            ->join('lab_request_items as lri', 'lri.id', '=', 'r.lab_request_item_id')
            ->join('lab_requests as lr', 'lr.id', '=', 'lri.lab_request_id')
            ->where('lr.facility_id', $facilityId)
            ->where('r.recorded_at', '>=', $windowStart)
            ->whereNotNull('r.verified_at')
            ->whereNull('r.deleted_at')
            ->whereNull('lri.deleted_at')
            ->whereNull('lr.deleted_at')
            ->count();

        $verificationRate = $totalResults > 0
            ? round(($verifiedResults / $totalResults) * 100, 1)
            : 0.0;

        $avgTurnaroundHours = (float) DB::table('lab_request_items as lri')
            ->join('lab_requests as lr', 'lr.id', '=', 'lri.lab_request_id')
            ->where('lr.facility_id', $facilityId)
            ->whereNotNull('lri.collected_at')
            ->whereNotNull('lri.completed_at')
            ->where('lri.completed_at', '>=', $windowStart)
            ->whereNull('lri.deleted_at')
            ->whereNull('lr.deleted_at')
            ->selectRaw('COALESCE(AVG(TIMESTAMPDIFF(MINUTE, lri.collected_at, lri.completed_at))/60, 0) as avg_hours')
            ->value('avg_hours');

        $criticalOpen = (int) DB::table('lab_results as r')
            ->join('lab_request_items as lri', 'lri.id', '=', 'r.lab_request_item_id')
            ->join('lab_requests as lr', 'lr.id', '=', 'lri.lab_request_id')
            ->where('lr.facility_id', $facilityId)
            ->where('r.flag', 'critical')
            ->where(function ($q): void {
                $q->whereNull('r.verified_at')
                    ->orWhere('r.is_critical_alert_sent', false);
            })
            ->whereNull('r.deleted_at')
            ->whereNull('lri.deleted_at')
            ->whereNull('lr.deleted_at')
            ->count();

        $score = max(0, min(100, ($verificationRate * 0.6) + ((24 - min(24, $avgTurnaroundHours)) / 24 * 40)));

        return [
            'verification_rate_pct' => $verificationRate,
            'avg_turnaround_hours' => round($avgTurnaroundHours, 2),
            'critical_open_count' => $criticalOpen,
            'overall_grade' => $this->scoreToLetter($score),
            'overall_label' => $score >= 70 ? 'Solid lab operations' : 'Improve turnaround and verification',
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
}

