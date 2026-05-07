<?php

declare(strict_types=1);

namespace App\Services\Nursing;

use App\Models\FacilityShiftHandover;
use App\Models\FacilityTask;
use App\Models\NursingMedicationAdministration;
use App\Models\NursingMedicationDose;
use App\Models\NursingTreatmentLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Aggregates facility-scoped nursing operations for the intelligence dashboard.
 */
class NursingDashboardService
{
    private bool $hasFacilityTasks = false;

    private bool $hasNursingDoses = false;

    private bool $hasNursingAdmins = false;

    private bool $hasTreatmentLogs = false;

    private bool $hasShiftHandovers = false;

    /**
     * @return array{success: bool, message?: string, data?: array<string, mixed>, error?: string}
     */
    public function getDashboard(int $facilityId, ?string $tz = null): array
    {
        try {
            $this->hasFacilityTasks = Schema::hasTable('facility_tasks');
            $this->hasNursingDoses = Schema::hasTable('nursing_medication_doses');
            $this->hasNursingAdmins = Schema::hasTable('nursing_medication_administrations');
            $this->hasTreatmentLogs = Schema::hasTable('nursing_treatment_logs');
            $this->hasShiftHandovers = Schema::hasTable('facility_shift_handovers');

            $now = $tz ? Carbon::now($tz) : Carbon::now();
            $today = $now->toDateString();
            $yesterday = $now->copy()->subDay()->toDateString();
            $thirtyDaysAgo = $now->copy()->subDays(30);

            $openTasks = $this->countOpenTasks($facilityId);
            $openTasksYesterday = $this->countOpenTasksAsOfDate($facilityId, $yesterday, $now);

            $openTasksChangePct = $openTasksYesterday > 0
                ? round((($openTasks - $openTasksYesterday) / $openTasksYesterday) * 100, 1)
                : ($openTasks > 0 ? 100.0 : 0.0);

            $missedMedAlerts = $this->countMissedMedicationAlerts($facilityId, $now);

            $adminsToday = $this->countAdministrationsForDate($facilityId, $today);
            $adminsYesterday = $this->countAdministrationsForDate($facilityId, $yesterday);

            $adminsChangePct = $adminsYesterday > 0
                ? round((($adminsToday - $adminsYesterday) / $adminsYesterday) * 100, 1)
                : ($adminsToday > 0 ? 100.0 : 0.0);

            $pendingDosesToday = $this->countPendingDosesScheduledForDate($facilityId, $today, $now);

            $treatmentLogsToday = $this->countTreatmentLogsForDate($facilityId, $today);
            $treatmentByDay = $this->treatmentLogsGroupedByDaySince($facilityId, $thirtyDaysAgo);
            $avgDailyTreatment30 = $treatmentByDay->count() > 0
                ? round((float) $treatmentByDay->sum() / max(1, $treatmentByDay->count()), 2)
                : 0.0;

            $treatmentVsAvgPct = $avgDailyTreatment30 > 0
                ? round((($treatmentLogsToday - $avgDailyTreatment30) / $avgDailyTreatment30) * 100, 1)
                : ($treatmentLogsToday > 0 ? 100.0 : 0.0);

            $unackHandovers = $this->countUnacknowledgedHandovers($facilityId);

            $overdueTasks = $this->countOverdueOpenTasks($facilityId, $now);

            $activityWeek = $this->buildWeeklyDoseActivity($facilityId, $now);
            $volumeTrends = $this->buildCareVolumeTrends($facilityId, $now);
            $recentActivity = $this->buildRecentActivity($facilityId);
            [$onTimePct, $taskCompletionPct, $docPct, $dailyTouchpoints] = $this->computePerformanceInputs(
                $facilityId,
                $now,
                $today
            );

            $avgDailyAdmins30 = $this->avgDailyAdministrationsSince($facilityId, $thirtyDaysAgo);
            $workloadPct = $avgDailyAdmins30 > 0
                ? round(min(100.0, ($adminsToday / $avgDailyAdmins30) * 100), 1)
                : ($adminsToday > 0 ? 100.0 : 0.0);

            $performance = $this->buildPerformance(
                $onTimePct,
                $taskCompletionPct,
                $docPct,
                $workloadPct,
                $dailyTouchpoints
            );

            return [
                'success' => true,
                'message' => 'Nursing dashboard retrieved successfully.',
                'data' => [
                    'summary' => [
                        'open_tasks' => [
                            'value' => $openTasks,
                            'change_pct' => $openTasksChangePct,
                            'change_label' => 'vs snapshot yesterday (open workload)',
                        ],
                        'missed_medication_alerts' => [
                            'value' => $missedMedAlerts,
                            'change' => null,
                            'change_label' => 'pending doses past scheduled time',
                        ],
                        'administrations_today' => [
                            'value' => $adminsToday,
                            'change_pct' => $adminsChangePct,
                            'change_label' => 'vs yesterday',
                            'secondary_label' => $pendingDosesToday > 0
                                ? sprintf('~%d dose rows still due today', $pendingDosesToday)
                                : null,
                        ],
                        'pending_review' => [
                            'value' => $unackHandovers,
                            'change_label' => 'shift handovers awaiting acknowledgement',
                        ],
                        'treatment_logs_today' => [
                            'value' => round((float) $treatmentLogsToday, 2),
                            'currency' => null,
                            'change_pct_vs_avg_daily' => $treatmentVsAvgPct,
                            'change_label' => 'vs 30-day avg daily (non-Rx procedures)',
                        ],
                        'overdue_tasks' => $overdueTasks,
                    ],
                    'dose_activity' => $activityWeek,
                    'care_volume_trends' => $volumeTrends,
                    'recent_activity' => $recentActivity,
                    'performance' => $performance,
                    'generated_at' => $now->toIso8601String(),
                    'data_sources' => [
                        'facility_tasks_table' => $this->hasFacilityTasks,
                        'nursing_medication_doses_table' => $this->hasNursingDoses,
                        'nursing_medication_administrations_table' => $this->hasNursingAdmins,
                        'nursing_treatment_logs_table' => $this->hasTreatmentLogs,
                        'facility_shift_handovers_table' => $this->hasShiftHandovers,
                    ],
                ],
            ];
        } catch (Throwable $e) {
            Log::error('Nursing dashboard aggregation failed', [
                'facility_id' => $facilityId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to build nursing dashboard.',
                'error' => config('app.debug') ? $e->getMessage() : 'SERVER_ERROR',
            ];
        }
    }

    protected function countOpenTasks(int $facilityId): int
    {
        if (! $this->hasFacilityTasks) {
            return 0;
        }

        return (int) FacilityTask::query()
            ->where('facility_id', $facilityId)
            ->whereIn('status', ['pending', 'in_progress'])
            ->count();
    }

    /**
     * Approximate yesterday's open workload: tasks that were open before end of yesterday
     * (still pending/in_progress and created before end of yesterday, or without completed_at before then).
     */
    protected function countOpenTasksAsOfDate(int $facilityId, string $yesterdayDate, Carbon $now): int
    {
        if (! $this->hasFacilityTasks) {
            return 0;
        }

        $endYesterday = Carbon::parse($yesterdayDate.' 23:59:59', $now->timezone);

        return (int) FacilityTask::query()
            ->where('facility_id', $facilityId)
            ->whereIn('status', ['pending', 'in_progress'])
            ->where('created_at', '<=', $endYesterday)
            ->count();
    }

    protected function countOverdueOpenTasks(int $facilityId, Carbon $now): int
    {
        if (! $this->hasFacilityTasks) {
            return 0;
        }

        return (int) FacilityTask::query()
            ->where('facility_id', $facilityId)
            ->whereIn('status', ['pending', 'in_progress'])
            ->whereNotNull('due_at')
            ->where('due_at', '<', $now)
            ->count();
    }

    protected function countMissedMedicationAlerts(int $facilityId, Carbon $now): int
    {
        if (! $this->hasNursingDoses) {
            return 0;
        }

        return (int) NursingMedicationDose::query()
            ->where('facility_id', $facilityId)
            ->where('status', 'pending')
            ->where('scheduled_for', '<', $now)
            ->count();
    }

    protected function countAdministrationsForDate(int $facilityId, string $date): int
    {
        if (! $this->hasNursingAdmins) {
            return 0;
        }

        return (int) NursingMedicationAdministration::query()
            ->where('facility_id', $facilityId)
            ->whereDate('administered_at', $date)
            ->count();
    }

    protected function countPendingDosesScheduledForDate(int $facilityId, string $today, Carbon $now): int
    {
        if (! $this->hasNursingDoses) {
            return 0;
        }

        return (int) NursingMedicationDose::query()
            ->where('facility_id', $facilityId)
            ->where('status', 'pending')
            ->whereDate('scheduled_for', $today)
            ->where('scheduled_for', '>=', $now)
            ->count();
    }

    protected function countTreatmentLogsForDate(int $facilityId, string $date): float
    {
        if (! $this->hasTreatmentLogs) {
            return 0.0;
        }

        return (float) NursingTreatmentLog::query()
            ->where('facility_id', $facilityId)
            ->where(function ($q) use ($date): void {
                $q->whereDate('performed_at', $date)
                    ->orWhere(function ($q2) use ($date): void {
                        $q2->whereNull('performed_at')->whereDate('created_at', $date);
                    });
            })
            ->count();
    }

    /**
     * @return \Illuminate\Support\Collection<int|string, mixed>
     */
    protected function treatmentLogsGroupedByDaySince(int $facilityId, Carbon $since)
    {
        if (! $this->hasTreatmentLogs) {
            return collect();
        }

        $rows = NursingTreatmentLog::query()
            ->where('facility_id', $facilityId)
            ->where(function ($q) use ($since): void {
                $q->where('performed_at', '>=', $since)
                    ->orWhere(function ($q2) use ($since): void {
                        $q2->whereNull('performed_at')->where('created_at', '>=', $since);
                    });
            })
            ->get(['performed_at', 'created_at']);

        $byDay = [];
        foreach ($rows as $row) {
            $ts = $row->performed_at ?? $row->created_at;
            if ($ts) {
                $d = Carbon::parse($ts)->toDateString();
                $byDay[$d] = ($byDay[$d] ?? 0) + 1;
            }
        }

        return collect($byDay);
    }

    protected function countUnacknowledgedHandovers(int $facilityId): int
    {
        if (! $this->hasShiftHandovers) {
            return 0;
        }

        return (int) FacilityShiftHandover::query()
            ->where('facility_id', $facilityId)
            ->whereNull('acknowledged_at')
            ->count();
    }

    protected function buildWeeklyDoseActivity(int $facilityId, Carbon $now): array
    {
        $start = $now->copy()->startOfWeek(Carbon::MONDAY);
        $series = [];
        $totalScheduled = 0;
        $totalAdministered = 0;

        for ($i = 0; $i < 7; ++$i) {
            $day = $start->copy()->addDays($i);
            $d = $day->toDateString();
            $label = $day->format('D');

            $scheduled = $this->hasNursingDoses
                ? (int) NursingMedicationDose::query()
                    ->where('facility_id', $facilityId)
                    ->whereDate('scheduled_for', $d)
                    ->count()
                : 0;

            $administered = $this->countAdministrationsForDate($facilityId, $d);

            $pendingEst = max(0, $scheduled - $administered);

            $series[] = [
                'day' => $label,
                'date' => $d,
                'doses_scheduled' => $scheduled,
                'administered' => $administered,
                'pending' => $pendingEst,
            ];

            $totalScheduled += $scheduled;
            $totalAdministered += $administered;
        }

        $completion = $totalScheduled > 0
            ? round(min(100.0, ($totalAdministered / max(1, $totalScheduled)) * 100), 1)
            : ($totalAdministered > 0 ? 100.0 : 0.0);

        return [
            'bucket' => 'week',
            'series' => $series,
            'totals' => [
                'doses_scheduled_week' => $totalScheduled,
                'administrations_week' => $totalAdministered,
                'completion_rate_pct' => $completion,
                'avg_per_day' => round(($totalScheduled + $totalAdministered) / 7, 1),
            ],
        ];
    }

    protected function buildCareVolumeTrends(int $facilityId, Carbon $now): array
    {
        $series = [];
        $days = 30;
        $totalAdminUnits = 0.0;

        for ($i = $days - 1; $i >= 0; --$i) {
            $day = $now->copy()->subDays($i)->startOfDay();
            $d = $day->toDateString();

            $adminUnits = $this->hasNursingAdmins
                ? (float) (NursingMedicationAdministration::query()
                    ->where('facility_id', $facilityId)
                    ->whereDate('administered_at', $d)
                    ->selectRaw('COALESCE(SUM(COALESCE(quantity_given,0)),0) as t')
                    ->value('t') ?? 0)
                : 0.0;

            $scheduledDoses = $this->hasNursingDoses
                ? (int) NursingMedicationDose::query()
                    ->where('facility_id', $facilityId)
                    ->whereDate('scheduled_for', $d)
                    ->count()
                : 0;

            $exceptions = $this->hasNursingDoses
                ? (int) NursingMedicationDose::query()
                    ->where('facility_id', $facilityId)
                    ->whereDate('scheduled_for', $d)
                    ->whereIn('status', ['missed', 'skipped'])
                    ->count()
                : 0;

            $totalAdminUnits += $adminUnits;

            $series[] = [
                'date' => $d,
                'label' => $day->format('M j'),
                'administered_units' => round($adminUnits, 3),
                'scheduled_doses' => $scheduledDoses,
                'exceptions' => $exceptions,
            ];
        }

        $missedNow = $this->countMissedMedicationAlerts($facilityId, $now);
        $pendingFuture = $this->hasNursingDoses
            ? (int) NursingMedicationDose::query()
                ->where('facility_id', $facilityId)
                ->where('status', 'pending')
                ->where('scheduled_for', '>=', $now)
                ->count()
            : 0;

        $firstHalf = array_slice($series, 0, (int) floor($days / 2));
        $secondHalf = array_slice($series, (int) floor($days / 2));
        $volFirst = $this->sumColumn($firstHalf, 'administered_units') + $this->sumColumn($firstHalf, 'exceptions');
        $volSecond = $this->sumColumn($secondHalf, 'administered_units') + $this->sumColumn($secondHalf, 'exceptions');
        $trendPct = $volFirst > 0
            ? round((($volSecond - $volFirst) / $volFirst) * 100, 1)
            : 0.0;

        return [
            'days' => $days,
            'series' => $series,
            'footer' => [
                'pending_upcoming_doses' => $pendingFuture,
                'overdue_pending_doses' => $missedNow,
                'activity_growth_pct' => $trendPct,
                'avg_daily_admin_units' => round($totalAdminUnits / $days, 3),
                'note' => $this->hasNursingAdmins
                    ? 'Administered units sum quantity_given; scheduled/exceptions from nursing doses.'
                    : 'Nursing medication tables not available — zeros shown.',
            ],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    protected function sumColumn(array $rows, string $key): float
    {
        $sum = 0.0;
        foreach ($rows as $r) {
            $sum += (float) ($r[$key] ?? 0);
        }

        return $sum;
    }

    /**
     * @return array{0: float, 1: float, 2: float, 3: int}
     */
    protected function computePerformanceInputs(int $facilityId, Carbon $now, string $today): array
    {
        $sevenDaysAgo = $now->copy()->subDays(7);

        $onTimePct = $this->onTimeAdministrationRate($facilityId, $sevenDaysAgo, $now);

        $taskCompletionPct = $this->weeklyTaskCompletionRate($facilityId, $now);

        $docPct = $this->administrationDocumentationRate($facilityId, $sevenDaysAgo);

        $dailyTouchpoints = $this->countDistinctPatientsTouchedToday($facilityId, $today);

        return [$onTimePct, $taskCompletionPct, $docPct, $dailyTouchpoints];
    }

    protected function onTimeAdministrationRate(int $facilityId, Carbon $since, Carbon $until): float
    {
        if (! $this->hasNursingAdmins || ! $this->hasNursingDoses) {
            return 100.0;
        }

        $rows = NursingMedicationAdministration::query()
            ->where('facility_id', $facilityId)
            ->whereBetween('administered_at', [$since, $until])
            ->whereNotNull('nursing_medication_dose_id')
            ->with('dose:id,scheduled_for')
            ->get(['id', 'nursing_medication_dose_id', 'administered_at']);

        if ($rows->isEmpty()) {
            return 100.0;
        }

        $onTime = 0;
        foreach ($rows as $row) {
            $sched = $row->dose?->scheduled_for;
            if (! $sched) {
                ++$onTime;

                continue;
            }
            $diffMin = abs(Carbon::parse($row->administered_at)->diffInMinutes(Carbon::parse($sched)));

            if ($diffMin <= 120) {
                ++$onTime;
            }
        }

        return round(($onTime / max(1, $rows->count())) * 100, 1);
    }

    protected function weeklyTaskCompletionRate(int $facilityId, Carbon $now): float
    {
        if (! $this->hasFacilityTasks) {
            return 100.0;
        }

        $start = $now->copy()->startOfWeek(Carbon::MONDAY);
        $completed = (int) FacilityTask::query()
            ->where('facility_id', $facilityId)
            ->whereIn('status', ['completed'])
            ->whereNotNull('completed_at')
            ->where('completed_at', '>=', $start)
            ->count();

        $den = max(1, $completed + (int) FacilityTask::query()
            ->where('facility_id', $facilityId)
            ->whereIn('status', ['pending', 'in_progress'])
            ->count());

        return round(min(100.0, ($completed / $den) * 100), 1);
    }

    protected function administrationDocumentationRate(int $facilityId, Carbon $since): float
    {
        if (! $this->hasNursingAdmins) {
            return 100.0;
        }

        $total = (int) NursingMedicationAdministration::query()
            ->where('facility_id', $facilityId)
            ->where('administered_at', '>=', $since)
            ->count();

        if ($total === 0) {
            return 100.0;
        }

        $withNotes = (int) NursingMedicationAdministration::query()
            ->where('facility_id', $facilityId)
            ->where('administered_at', '>=', $since)
            ->whereNotNull('notes')
            ->where('notes', '!=', '')
            ->count();

        return round(($withNotes / $total) * 100, 1);
    }

    protected function countDistinctPatientsTouchedToday(int $facilityId, string $today): int
    {
        $patientIds = [];

        if ($this->hasNursingAdmins) {
            $p = NursingMedicationAdministration::query()
                ->join('visits', 'visits.id', '=', 'nursing_medication_administrations.visit_id')
                ->where('nursing_medication_administrations.facility_id', $facilityId)
                ->whereDate('nursing_medication_administrations.administered_at', $today)
                ->distinct()
                ->pluck('visits.patient_id');
            foreach ($p as $id) {
                if ($id) {
                    $patientIds[(int) $id] = true;
                }
            }
        }

        if ($this->hasTreatmentLogs) {
            $p2 = NursingTreatmentLog::query()
                ->where('facility_id', $facilityId)
                ->where(function ($q) use ($today): void {
                    $q->whereDate('performed_at', $today)
                        ->orWhere(function ($q2) use ($today): void {
                            $q2->whereNull('performed_at')->whereDate('created_at', $today);
                        });
                })
                ->whereNotNull('patient_id')
                ->pluck('patient_id');
            foreach ($p2 as $id) {
                $patientIds[(int) $id] = true;
            }
        }

        return count($patientIds);
    }

    protected function avgDailyAdministrationsSince(int $facilityId, Carbon $since): float
    {
        if (! $this->hasNursingAdmins) {
            return 0.0;
        }

        $byDay = NursingMedicationAdministration::query()
            ->where('facility_id', $facilityId)
            ->where('administered_at', '>=', $since)
            ->whereNotNull('administered_at')
            ->selectRaw('DATE(administered_at) as d, COUNT(*) as c')
            ->groupBy('d')
            ->pluck('c', 'd');

        if ($byDay->isEmpty()) {
            return 0.0;
        }

        return round((float) $byDay->sum() / max(1, $byDay->count()), 3);
    }

    protected function buildPerformance(
        float $onTimePct,
        float $taskCompletionPct,
        float $documentationPct,
        float $workloadPct,
        int $dailyTouchpoints
    ): array {
        $avgScore = ($onTimePct + $taskCompletionPct + $documentationPct + min(100.0, $workloadPct)) / 4;
        $grade = $this->scoreToLetter($avgScore);

        return [
            'medication_on_time_pct' => $onTimePct,
            'task_completion_pct' => $taskCompletionPct,
            'documentation_rate_pct' => $documentationPct,
            'avg_wait_minutes' => null,
            'daily_touchpoints' => $dailyTouchpoints,
            'workload_vs_avg_pct' => $workloadPct,
            'overall_grade' => $grade,
            'overall_label' => $avgScore >= 70 ? 'Solid nursing operations' : 'Review workload & overdue items',
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

        if ($this->hasFacilityTasks) {
            $tasks = FacilityTask::query()
                ->where('facility_id', $facilityId)
                ->orderByDesc('updated_at')
                ->limit(10)
                ->get(['id', 'title', 'status', 'updated_at', 'completed_at', 'assigned_to_user_id']);

            foreach ($tasks as $t) {
                $when = $t->completed_at ?? $t->updated_at;
                $actor = $t->assigned_to_user_id
                    ? $this->resolveUserName((int) $t->assigned_to_user_id)
                    : null;

                $events[] = [
                    'id' => 'task-'.$t->id,
                    'type' => 'task',
                    'title' => 'Facility task · '.$t->status,
                    'description' => $t->title ?? 'Task update',
                    'occurred_at' => optional($when)?->toIso8601String(),
                    'actor_name' => $actor,
                ];
            }
        }

        if ($this->hasNursingAdmins) {
            $admins = NursingMedicationAdministration::query()
                ->where('facility_id', $facilityId)
                ->orderByDesc('administered_at')
                ->limit(10)
                ->with('prescriptionItem:id,medication_name')
                ->get(['id', 'administered_at', 'administered_by_user_id', 'outcome', 'prescription_item_id']);

            foreach ($admins as $a) {
                $med = $a->prescriptionItem?->medication_name ?? 'Medication';
                $actor = $a->administered_by_user_id
                    ? $this->resolveUserName((int) $a->administered_by_user_id)
                    : null;

                $events[] = [
                    'id' => 'admin-'.$a->id,
                    'type' => 'medication',
                    'title' => 'Medication administration',
                    'description' => sprintf('%s · %s', $med, $a->outcome ?? 'recorded'),
                    'occurred_at' => optional($a->administered_at)?->toIso8601String(),
                    'actor_name' => $actor,
                ];
            }
        }

        if ($this->hasTreatmentLogs) {
            $logs = NursingTreatmentLog::query()
                ->where('facility_id', $facilityId)
                ->orderByDesc('created_at')
                ->limit(8)
                ->get(['id', 'created_at', 'performed_at', 'title', 'category', 'logged_by_user_id']);

            foreach ($logs as $log) {
                $when = $log->performed_at ?? $log->created_at;
                $actor = $log->logged_by_user_id
                    ? $this->resolveUserName((int) $log->logged_by_user_id)
                    : null;

                $events[] = [
                    'id' => 'tx-'.$log->id,
                    'type' => 'treatment',
                    'title' => 'Treatment log · '.($log->category ?? 'general'),
                    'description' => $log->title ?? 'Treatment recorded',
                    'occurred_at' => optional($when)?->toIso8601String(),
                    'actor_name' => $actor,
                ];
            }
        }

        if ($this->hasShiftHandovers) {
            $hands = FacilityShiftHandover::query()
                ->where('facility_id', $facilityId)
                ->orderByDesc('handed_over_at')
                ->limit(8)
                ->get(['id', 'handed_over_at', 'shift_label', 'status', 'handed_over_by_user_id']);

            foreach ($hands as $h) {
                $actor = $h->handed_over_by_user_id
                    ? $this->resolveUserName((int) $h->handed_over_by_user_id)
                    : null;

                $events[] = [
                    'id' => 'ho-'.$h->id,
                    'type' => 'handover',
                    'title' => 'Shift handover · '.($h->status ?? 'logged'),
                    'description' => $h->shift_label ?? 'Handover recorded',
                    'occurred_at' => optional($h->handed_over_at)?->toIso8601String(),
                    'actor_name' => $actor,
                ];
            }
        }

        usort($events, static function (array $a, array $b): int {
            return strcmp((string) ($b['occurred_at'] ?? ''), (string) ($a['occurred_at'] ?? ''));
        });

        return array_slice($events, 0, 18);
    }

    protected function resolveUserName(int $userId): string
    {
        $user = User::query()->find($userId);

        if (! $user) {
            return 'Staff';
        }

        return $user->full_name ?? $user->display_name ?? 'Staff';
    }
}
