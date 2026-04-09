<?php

namespace App\Services\Patient\Analytics;

use App\Models\Patient;
use App\Models\Visit;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class PatientAnalyticsService
{
    /**
     * Get complete dashboard data for a facility.
     */
    public function getDashboard(
        int $facilityId,
        string $period = 'week',
        ?string $dateFrom = null,
        ?string $dateTo = null
    ): array {
        try {
            [$startDate, $endDate] = $this->resolveDateRange($period, $dateFrom, $dateTo);
            [$previousStart, $previousEnd] = $this->getPreviousPeriodRange($startDate, $endDate);

            $visitsQuery = $this->baseVisitsQuery($facilityId, $startDate, $endDate);
            $previousVisitsQuery = $this->baseVisitsQuery($facilityId, $previousStart, $previousEnd);

            $patientIds = (clone $visitsQuery)
                ->whereNotNull('patient_id')
                ->distinct()
                ->pluck('patient_id');

            $previousPatientIds = (clone $previousVisitsQuery)
                ->whereNotNull('patient_id')
                ->distinct()
                ->pluck('patient_id');

            return [
                'period' => [
                    'label' => $this->getPeriodLabel($period, $startDate, $endDate),
                    'start_date' => $startDate->toDateString(),
                    'end_date' => $endDate->toDateString(),
                ],
                'kpi' => $this->buildKpi($visitsQuery, $previousVisitsQuery, $patientIds, $previousPatientIds),
                'patient_trends' => $this->buildPatientTrends($facilityId, $startDate, $endDate),
                'patient_flow' => $this->buildPatientFlow($facilityId, $visitsQuery),
                'demographics' => $this->buildDemographics($patientIds),
                'visit_types' => $this->buildVisitTypes($visitsQuery),
                'retention' => $this->buildRetention($facilityId, $startDate, $endDate, $patientIds),
                'revenue' => $this->buildRevenue($visitsQuery),
                'alerts' => $this->buildAlerts($facilityId, $visitsQuery, $previousVisitsQuery),
            ];
        } catch (Throwable $e) {
            Log::error('Dashboard service failed.', [
                'facility_id' => $facilityId,
                'period' => $period,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    protected function baseVisitsQuery(
        int $facilityId,
        CarbonInterface $startDate,
        CarbonInterface $endDate
    ): Builder {
        return Visit::query()
            ->where('facility_id', $facilityId)
            ->whereBetween('arrived_at', [$startDate, $endDate]);
    }

    protected function resolveDateRange(string $period, ?string $dateFrom, ?string $dateTo): array
    {
        $now = Carbon::now();

        return match ($period) {
            'today' => [
                $now->copy()->startOfDay(),
                $now->copy()->endOfDay(),
            ],
            'week' => [
                $now->copy()->startOfWeek(),
                $now->copy()->endOfWeek(),
            ],
            'month' => [
                $now->copy()->startOfMonth(),
                $now->copy()->endOfMonth(),
            ],
            'custom' => [
                Carbon::parse($dateFrom)->startOfDay(),
                Carbon::parse($dateTo)->endOfDay(),
            ],
            default => [
                $now->copy()->startOfWeek(),
                $now->copy()->endOfWeek(),
            ],
        };
    }

    protected function getPreviousPeriodRange(CarbonInterface $start, CarbonInterface $end): array
    {
        $daysDiff = $start->diffInDays($end) + 1;

        return [
            $start->copy()->subDays($daysDiff),
            $start->copy()->subSecond(),
        ];
    }

    protected function getPeriodLabel(string $period, CarbonInterface $start, CarbonInterface $end): string
    {
        return match ($period) {
            'today' => 'Today',
            'week' => $start->format('M d') . ' - ' . $end->format('M d, Y'),
            'month' => $start->format('F Y'),
            default => $start->format('M d, Y') . ' - ' . $end->format('M d, Y'),
        };
    }

  protected function buildKpi(
    Builder $visitsQuery,
    Builder $previousVisitsQuery,
    Collection $patientIds,
    Collection $previousPatientIds
): array {
    $totalPatients = $patientIds->count();
    $previousTotalPatients = $previousPatientIds->count();
    $patientGrowth = $this->percentageChange($previousTotalPatients, $totalPatients);

    // FIX: Get patients whose FIRST EVER visit is within the date range
    $newPatients = 0;
    $returningPatients = 0;
    
    if ($patientIds->isNotEmpty()) {
        // Get first visit date for each patient
        $firstVisitDates = Visit::query()
            ->where('facility_id', $visitsQuery->getModel()->getAttribute('facility_id'))
            ->whereIn('patient_id', $patientIds)
            ->selectRaw('patient_id, MIN(arrived_at) as first_visit')
            ->groupBy('patient_id')
            ->get()
            ->keyBy('patient_id');
        
        // Get the date range boundaries
        $startDate = $visitsQuery->getQuery()->wheres[1]['values'][0] ?? null;
        $endDate = $visitsQuery->getQuery()->wheres[1]['values'][1] ?? null;
        
        foreach ($patientIds as $patientId) {
            $firstVisit = $firstVisitDates[$patientId] ?? null;
            if ($firstVisit && $firstVisit->first_visit) {
                $firstVisitDate = Carbon::parse($firstVisit->first_visit);
                // Patient is NEW if their first ever visit is within the date range
                if ($firstVisitDate->between($startDate, $endDate)) {
                    $newPatients++;
                } else {
                    $returningPatients++;
                }
            }
        }
    }
    
    $newPatientRate = $totalPatients > 0
        ? round(($newPatients / $totalPatients) * 100, 1)
        : 0;

    $activeVisits = (clone $visitsQuery)
        ->whereIn('status', ['active', 'in_progress'])
        ->count();

    $completedVisits = (clone $visitsQuery)
        ->where('status', 'completed')
        ->count();

    $cancelledMissed = (clone $visitsQuery)
        ->whereIn('status', ['cancelled', 'no_show'])
        ->count();

    return [
        'total_patients' => [
            'value' => $totalPatients,
            'previous_value' => $previousTotalPatients,
            'change_percentage' => $patientGrowth,
            'trend' => $patientGrowth >= 0 ? 'up' : 'down',
        ],
        'new_vs_returning' => [
            'new' => $newPatients,
            'returning' => $returningPatients,
            'new_rate' => $newPatientRate,
        ],
        'active_visits' => $activeVisits,
        'completed_visits' => $completedVisits,
        'cancelled_missed' => $cancelledMissed,
    ];
}

 protected function buildPatientTrends(
    int $facilityId,
    CarbonInterface $startDate,
    CarbonInterface $endDate
): array {
    $dailyRaw = Visit::query()
        ->where('facility_id', $facilityId)
        ->whereBetween('arrived_at', [$startDate, $endDate])
        ->whereNotNull('patient_id')
        ->selectRaw('DATE(arrived_at) as date, COUNT(DISTINCT patient_id) as count')
        ->groupBy(DB::raw('DATE(arrived_at)'))
        ->orderBy('date')
        ->pluck('count', 'date');

    $dailyVisits = collect();
    $cursor = $startDate->copy()->startOfDay();

    while ($cursor->lte($endDate)) {
        $date = $cursor->toDateString();

        $dailyVisits->push([
            'date' => $date,
            'patients' => (int) ($dailyRaw[$date] ?? 0),
        ]);

        $cursor->addDay();
    }

    $weeklyVisits = Visit::query()
        ->where('facility_id', $facilityId)
        ->whereBetween('arrived_at', [$startDate, $endDate])
        ->whereNotNull('patient_id')
        ->selectRaw('YEAR(arrived_at) as year_num, WEEK(arrived_at, 1) as week_num, MIN(DATE(arrived_at)) as week_start, COUNT(DISTINCT patient_id) as count')
        ->groupBy('year_num', 'week_num')
        ->orderBy('year_num')
        ->orderBy('week_num')
        ->get()
        ->map(fn ($row) => [
            'week' => $row->week_start,
            'patients' => (int) $row->count,
        ])
        ->values();

    // FIX: Get patients whose FIRST EVER visit is within date range
    // Get all patients who visited in this period with their first ever visit date
    $patientsWithFirstVisit = DB::table('visits as v1')
        ->join(
            DB::raw('(SELECT patient_id, MIN(arrived_at) as first_visit 
                      FROM visits 
                      WHERE facility_id = ' . (int) $facilityId . ' 
                        AND patient_id IS NOT NULL 
                      GROUP BY patient_id) as first_visits'),
            'v1.patient_id',
            '=',
            'first_visits.patient_id'
        )
        ->where('v1.facility_id', $facilityId)
        ->whereBetween('v1.arrived_at', [$startDate, $endDate])
        ->select(
            'v1.patient_id',
            DB::raw('DATE(v1.arrived_at) as visit_date'),
            'first_visits.first_visit'
        )
        ->get();

    // Filter to only include patients whose first ever visit is within the date range
    // AND that first visit date matches the current visit date
    $newPatientGrowthRaw = $patientsWithFirstVisit
        ->filter(function ($visit) use ($startDate, $endDate) {
            $firstVisitDate = Carbon::parse($visit->first_visit);
            $visitDate = Carbon::parse($visit->visit_date);
            // Patient is NEW if their first ever visit is within the date range
            // AND this visit is their first visit
            return $firstVisitDate->between($startDate, $endDate) 
                && $firstVisitDate->toDateString() === $visitDate->toDateString();
        })
        ->groupBy('visit_date')
        ->map(fn ($group) => $group->count());

    $newPatientGrowth = collect();
    $cursor = $startDate->copy()->startOfDay();

    while ($cursor->lte($endDate)) {
        $date = $cursor->toDateString();

        $newPatientGrowth->push([
            'date' => $date,
            'new_patients' => (int) ($newPatientGrowthRaw[$date] ?? 0),
        ]);

        $cursor->addDay();
    }

    $peakDayRaw = Visit::query()
        ->where('facility_id', $facilityId)
        ->whereBetween('arrived_at', [$startDate, $endDate])
        ->selectRaw('DAYOFWEEK(arrived_at) as dow, COUNT(*) as count')
        ->groupBy('dow')
        ->pluck('count', 'dow');

    $peakDays = collect(range(1, 7))
        ->map(fn (int $dow) => [
            'day' => $this->getDayName($dow),
            'count' => (int) ($peakDayRaw[$dow] ?? 0),
        ])
        ->sortByDesc('count')
        ->values();

    return [
        'daily' => $dailyVisits->all(),
        'weekly' => $weeklyVisits->all(),
        'new_patient_growth' => $newPatientGrowth->all(),
        'peak_days' => $peakDays->all(),
    ];
}

    protected function buildPatientFlow(int $facilityId, Builder $visitsQuery): array
    {
        $visits = (clone $visitsQuery)->get([
            'arrived_at',
            'registered_at',
            'clinical_care_started_at',
            'clinical_care_ended_at',
        ]);

        if ($visits->isEmpty()) {
            return [
                'average_waiting_minutes' => 0,
                'average_consultation_minutes' => 0,
                'average_arrival_to_consultation_minutes' => 0,
                'queue_length' => 0,
            ];
        }

        $waitingTimes = [];
        $consultationTimes = [];
        $arrivalToConsultationTimes = [];

        foreach ($visits as $visit) {
            $arrival = $visit->arrived_at ? Carbon::parse($visit->arrived_at) : null;
            $registered = $visit->registered_at ? Carbon::parse($visit->registered_at) : null;
            $careStart = $visit->clinical_care_started_at ? Carbon::parse($visit->clinical_care_started_at) : null;
            $careEnd = $visit->clinical_care_ended_at ? Carbon::parse($visit->clinical_care_ended_at) : null;

            if ($arrival && $careStart && $careStart->gte($arrival)) {
                $waitingTimes[] = $arrival->diffInMinutes($careStart);
                $arrivalToConsultationTimes[] = $arrival->diffInMinutes($careStart);
            } elseif ($registered && $careStart && $careStart->gte($registered)) {
                $waitingTimes[] = $registered->diffInMinutes($careStart);

                if ($arrival && $careStart->gte($arrival)) {
                    $arrivalToConsultationTimes[] = $arrival->diffInMinutes($careStart);
                }
            }

            if ($careStart && $careEnd && $careEnd->gte($careStart)) {
                $consultationTimes[] = $careStart->diffInMinutes($careEnd);
            }
        }

        $queueLength = Visit::query()
            ->where('facility_id', $facilityId)
            ->whereIn('current_phase', ['waiting_triage', 'waiting_provider', 'registration'])
            ->whereIn('status', ['active', 'in_progress'])
            ->count();

        return [
            'average_waiting_minutes' => $this->averageMinutes($waitingTimes),
            'average_consultation_minutes' => $this->averageMinutes($consultationTimes),
            'average_arrival_to_consultation_minutes' => $this->averageMinutes($arrivalToConsultationTimes),
            'queue_length' => $queueLength,
        ];
    }

    protected function buildDemographics(Collection $patientIds): array
    {
        if ($patientIds->isEmpty()) {
            return [
                'age_groups' => [],
                'gender_distribution' => [],
                'insurance_vs_cash' => [
                    'insurance' => 0,
                    'cash' => 0,
                ],
            ];
        }

        $patients = Patient::query()
            ->whereIn('id', $patientIds)
            ->get([
                'date_of_birth',
                'biological_sex',
                'payment_responsibility',
            ]);

        $ageGroups = [
            '0-17' => 0,
            '18-35' => 0,
            '36-50' => 0,
            '51-65' => 0,
            '65+' => 0,
        ];

        $now = Carbon::now();

        foreach ($patients as $patient) {
            if (!$patient->date_of_birth) {
                continue;
            }

            $age = $now->diffInYears(Carbon::parse($patient->date_of_birth));

            if ($age < 18) {
                $ageGroups['0-17']++;
            } elseif ($age <= 35) {
                $ageGroups['18-35']++;
            } elseif ($age <= 50) {
                $ageGroups['36-50']++;
            } elseif ($age <= 65) {
                $ageGroups['51-65']++;
            } else {
                $ageGroups['65+']++;
            }
        }

        $gender = $patients
            ->groupBy(fn ($patient) => $patient->biological_sex ?: 'unknown')
            ->map(fn (Collection $items) => $items->count());

        $insuranceCash = [
            'insurance' => $patients->whereIn('payment_responsibility', ['insurance', 'government'])->count(),
            'cash' => $patients->whereIn('payment_responsibility', ['self_pay', 'cash'])->count(),
        ];

        return [
            'age_groups' => collect($ageGroups)
                ->map(fn ($count, $group) => [
                    'group' => $group,
                    'count' => $count,
                ])
                ->values()
                ->all(),
            'gender_distribution' => $gender
                ->map(fn ($count, $genderValue) => [
                    'gender' => $genderValue,
                    'count' => $count,
                ])
                ->values()
                ->all(),
            'insurance_vs_cash' => $insuranceCash,
        ];
    }

    protected function buildVisitTypes(Builder $visitsQuery): array
    {
        $typeCounts = (clone $visitsQuery)
            ->selectRaw('visit_type, COUNT(*) as count')
            ->groupBy('visit_type')
            ->get()
            ->map(fn ($row) => [
                'type' => $row->visit_type,
                'count' => (int) $row->count,
            ])
            ->values();

        $diagnosisRows = (clone $visitsQuery)
            ->whereNotNull('diagnosis_codes')
            ->get(['diagnosis_codes']);

        $conditions = [];

        foreach ($diagnosisRows as $row) {
            $codes = $this->decodeJsonArray($row->diagnosis_codes);

            foreach ($codes as $code) {
                $condition = is_array($code)
                    ? ($code['code'] ?? $code['description'] ?? 'unknown')
                    : (string) $code;

                $condition = trim((string) $condition);
                if ($condition === '') {
                    $condition = 'unknown';
                }

                $conditions[$condition] = ($conditions[$condition] ?? 0) + 1;
            }
        }

        arsort($conditions);

        $topConditions = collect(array_slice($conditions, 0, 10, true))
            ->map(fn ($count, $condition) => [
                'condition' => $condition,
                'count' => $count,
            ])
            ->values()
            ->all();

        return [
            'visit_types' => $typeCounts->all(),
            'most_treated_conditions' => $topConditions,
        ];
    }

    protected function buildRetention(
        int $facilityId,
        CarbonInterface $startDate,
        CarbonInterface $endDate,
        Collection $currentPatientIds
    ): array {
        $repeatPatients = Visit::query()
            ->where('facility_id', $facilityId)
            ->whereBetween('arrived_at', [$startDate, $endDate])
            ->whereNotNull('patient_id')
            ->selectRaw('patient_id, COUNT(*) as visit_count')
            ->groupBy('patient_id')
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->count();

        $totalUniquePatients = $currentPatientIds->count();

        $repeatRate = $totalUniquePatients > 0
            ? round(($repeatPatients / $totalUniquePatients) * 100, 1)
            : 0;

        $missed = Visit::query()
            ->where('facility_id', $facilityId)
            ->whereBetween('arrived_at', [$startDate, $endDate])
            ->whereIn('status', ['cancelled', 'no_show'])
            ->count();

        $totalVisits = Visit::query()
            ->where('facility_id', $facilityId)
            ->whereBetween('arrived_at', [$startDate, $endDate])
            ->count();

        $missedRate = $totalVisits > 0
            ? round(($missed / $totalVisits) * 100, 1)
            : 0;

        $followupScheduled = Visit::query()
            ->where('facility_id', $facilityId)
            ->whereBetween('arrived_at', [$startDate, $endDate])
            ->whereNotNull('followup_scheduled_at')
            ->count();

        $completedVisits = Visit::query()
            ->where('facility_id', $facilityId)
            ->whereBetween('arrived_at', [$startDate, $endDate])
            ->where('status', 'completed')
            ->count();

        $followupCompliance = $completedVisits > 0
            ? round(($followupScheduled / $completedVisits) * 100, 1)
            : 0;

        $returningPatients = 0;

        if ($currentPatientIds->isNotEmpty()) {
            $returningPatients = Visit::query()
                ->where('facility_id', $facilityId)
                ->whereIn('patient_id', $currentPatientIds->all())
                ->where('arrived_at', '<', $startDate)
                ->distinct()
                ->count('patient_id');
        }

        $returningPercentage = $totalUniquePatients > 0
            ? round(($returningPatients / $totalUniquePatients) * 100, 1)
            : 0;

        return [
            'repeat_visit_rate' => $repeatRate,
            'missed_appointment_rate' => $missedRate,
            'follow_up_compliance' => $followupCompliance,
            'returning_patients_percentage' => $returningPercentage,
        ];
    }

    protected function buildRevenue(Builder $visitsQuery): array
    {
        $visits = (clone $visitsQuery)->get([
            'estimated_total_charges',
            'patient_id',
            'procedure_codes',
        ]);

        $totalVisits = $visits->count();

        if ($totalVisits === 0) {
            return [
                'revenue_per_patient' => 0,
                'average_revenue_per_visit' => 0,
                'top_paying_services' => [],
            ];
        }

        $totalRevenue = (float) $visits->sum(
            fn ($visit) => (float) ($visit->estimated_total_charges ?? 0)
        );

        $uniquePatients = $visits
            ->whereNotNull('patient_id')
            ->unique('patient_id')
            ->count();

        $revenuePerPatient = $uniquePatients > 0
            ? round($totalRevenue / $uniquePatients, 2)
            : 0;

        $avgPerVisit = round($totalRevenue / $totalVisits, 2);

        $serviceRevenue = [];

        foreach ($visits as $visit) {
            $codes = $this->decodeJsonArray($visit->procedure_codes);

            if (empty($codes)) {
                continue;
            }

            $amount = (float) ($visit->estimated_total_charges ?? 0);
            $share = $amount / count($codes);

            foreach ($codes as $code) {
                $service = is_array($code)
                    ? ($code['code'] ?? $code['description'] ?? 'unknown')
                    : (string) $code;

                $service = trim((string) $service);
                if ($service === '') {
                    $service = 'unknown';
                }

                $serviceRevenue[$service] = ($serviceRevenue[$service] ?? 0) + $share;
            }
        }

        arsort($serviceRevenue);

        $topServices = collect(array_slice($serviceRevenue, 0, 5, true))
            ->map(fn ($revenue, $service) => [
                'service' => $service,
                'revenue' => round($revenue, 2),
            ])
            ->values()
            ->all();

        return [
            'revenue_per_patient' => $revenuePerPatient,
            'average_revenue_per_visit' => $avgPerVisit,
            'top_paying_services' => $topServices,
        ];
    }

    protected function buildAlerts(
        int $facilityId,
        Builder $visitsQuery,
        Builder $previousVisitsQuery
    ): array {
        $alerts = [];

        $flow = $this->buildPatientFlow($facilityId, $visitsQuery);

        $avgWait = $flow['average_waiting_minutes'] ?? 0;
        if ($avgWait > 30) {
            $alerts[] = [
                'type' => 'high_waiting_time',
                'severity' => 'warning',
                'message' => "Average waiting time is {$avgWait} minutes, above normal threshold (30 mins).",
                'value' => $avgWait,
            ];
        }

        $totalVisits = (clone $visitsQuery)->count();
        $missed = (clone $visitsQuery)
            ->whereIn('status', ['cancelled', 'no_show'])
            ->count();

        $missedRate = $totalVisits > 0
            ? round(($missed / $totalVisits) * 100, 1)
            : 0;

        if ($missedRate > 20) {
            $alerts[] = [
                'type' => 'high_missed_rate',
                'severity' => 'warning',
                'message' => "Missed appointment rate is {$missedRate}%, above 20% threshold.",
                'value' => $missedRate,
            ];
        }

        $currentPatients = (clone $visitsQuery)
            ->whereNotNull('patient_id')
            ->distinct()
            ->count('patient_id');

        $previousPatients = (clone $previousVisitsQuery)
            ->whereNotNull('patient_id')
            ->distinct()
            ->count('patient_id');

        $drop = $previousPatients > 0
            ? round((($previousPatients - $currentPatients) / $previousPatients) * 100, 1)
            : 0;

        if ($drop > 30) {
            $alerts[] = [
                'type' => 'patient_drop',
                'severity' => 'danger',
                'message' => "Patient volume dropped by {$drop}% compared to previous period.",
                'value' => $drop,
            ];
        }

        $queueLength = $flow['queue_length'] ?? 0;
        if ($queueLength > 10) {
            $alerts[] = [
                'type' => 'overcrowding',
                'severity' => 'danger',
                'message' => "Queue length is {$queueLength}, causing potential overcrowding.",
                'value' => $queueLength,
            ];
        }

        return $alerts;
    }

    protected function percentageChange(float $previous, float $current): float
    {
        if ($previous == 0.0 && $current == 0.0) {
            return 0;
        }

        if ($previous == 0.0) {
            return 100;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

    protected function averageMinutes(array $values): float
    {
        if (empty($values)) {
            return 0;
        }

        return round((float) collect($values)->average(), 1);
    }

    protected function decodeJsonArray(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (blank($value)) {
            return [];
        }

        $decoded = json_decode((string) $value, true);

        return is_array($decoded) ? $decoded : [];
    }

    protected function getDayName(int $dow): string
    {
        return match ($dow) {
            1 => 'Sunday',
            2 => 'Monday',
            3 => 'Tuesday',
            4 => 'Wednesday',
            5 => 'Thursday',
            6 => 'Friday',
            7 => 'Saturday',
            default => 'Unknown',
        };
    }
}
