<?php

declare(strict_types=1);

namespace App\Services\Referral;

use App\Models\Referral;
use App\Models\Visit;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Aggregates facility-scoped referral metrics for the intelligence dashboard.
 */
class ReferralDashboardService
{
    public function getDashboard(int $facilityId, ?string $tz = null): array
    {
        try {
            $now = $tz ? Carbon::now($tz) : Carbon::now();
            $today = $now->toDateString();
            $yesterday = $now->copy()->subDay()->toDateString();
            $sevenDaysAgo = $now->copy()->subDays(6)->startOfDay();

            $pendingIncoming = (int) Referral::query()
                ->where('receiving_facility_id', $facilityId)
                ->where('status', 'pending')
                ->count();

            $pendingOutgoing = (int) Referral::query()
                ->where('facility_id', $facilityId)
                ->where('status', 'pending')
                ->count();

            $acceptedActive = (int) Referral::query()
                ->where(function ($q) use ($facilityId): void {
                    $q->where('facility_id', $facilityId)
                        ->orWhere('receiving_facility_id', $facilityId);
                })
                ->where('status', 'accepted')
                ->count();

            $completedToday = (int) Referral::query()
                ->where(function ($q) use ($facilityId): void {
                    $q->where('facility_id', $facilityId)
                        ->orWhere('receiving_facility_id', $facilityId);
                })
                ->where('status', 'completed')
                ->whereDate('completed_date', $today)
                ->count();

            $completedYesterday = (int) Referral::query()
                ->where(function ($q) use ($facilityId): void {
                    $q->where('facility_id', $facilityId)
                        ->orWhere('receiving_facility_id', $facilityId);
                })
                ->where('status', 'completed')
                ->whereDate('completed_date', $yesterday)
                ->count();

            $completedChangePct = $completedYesterday > 0
                ? round((($completedToday - $completedYesterday) / $completedYesterday) * 100, 1)
                : ($completedToday > 0 ? 100.0 : 0.0);

            $queueVisits = (int) Visit::query()
                ->where('facility_id', $facilityId)
                ->where('care_delivery_workflow', 'referral')
                ->whereIn('status', ['active', 'in_progress'])
                ->count();

            $referralActivity = $this->buildReferralActivity($facilityId, $sevenDaysAgo, $now);
            $statusBreakdown = $this->buildStatusBreakdown($facilityId);
            $recentReferrals = $this->buildRecentReferrals($facilityId);

            return [
                'success' => true,
                'message' => 'Referral dashboard retrieved successfully.',
                'data' => [
                    'summary' => [
                        'queue_visits' => [
                            'value' => $queueVisits,
                            'change_label' => 'Visits on referral workflow queue',
                        ],
                        'pending_incoming' => [
                            'value' => $pendingIncoming,
                            'change_label' => 'Awaiting acceptance at this facility',
                        ],
                        'pending_outgoing' => [
                            'value' => $pendingOutgoing,
                            'change_label' => 'Sent from this facility, awaiting response',
                        ],
                        'accepted_active' => [
                            'value' => $acceptedActive,
                            'change_label' => 'Accepted referrals in progress',
                        ],
                        'completed_today' => [
                            'value' => $completedToday,
                            'change_pct' => $completedChangePct,
                            'change_label' => 'vs yesterday',
                        ],
                    ],
                    'referral_activity' => $referralActivity,
                    'status_breakdown' => $statusBreakdown,
                    'recent_referrals' => $recentReferrals,
                ],
            ];
        } catch (Throwable $e) {
            Log::error('Referral dashboard aggregation failed', [
                'facility_id' => $facilityId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to build referral dashboard.',
            ];
        }
    }

    /**
     * @return array{bucket: string, series: list<array{day: string, date: string, created: int, completed: int, rejected: int}>, totals: array<string, int|float>}
     */
    private function buildReferralActivity(int $facilityId, Carbon $start, Carbon $end): array
    {
        $series = [];
        $cursor = $start->copy()->startOfDay();
        $createdWeek = 0;
        $completedWeek = 0;
        $rejectedWeek = 0;

        while ($cursor->lte($end)) {
            $date = $cursor->toDateString();

            $created = (int) Referral::query()
                ->where(function ($q) use ($facilityId): void {
                    $q->where('facility_id', $facilityId)
                        ->orWhere('receiving_facility_id', $facilityId);
                })
                ->whereDate('referral_date', $date)
                ->count();

            $completed = (int) Referral::query()
                ->where(function ($q) use ($facilityId): void {
                    $q->where('facility_id', $facilityId)
                        ->orWhere('receiving_facility_id', $facilityId);
                })
                ->where('status', 'completed')
                ->whereDate('completed_date', $date)
                ->count();

            $rejected = (int) Referral::query()
                ->where(function ($q) use ($facilityId): void {
                    $q->where('facility_id', $facilityId)
                        ->orWhere('receiving_facility_id', $facilityId);
                })
                ->where('status', 'rejected')
                ->whereDate('response_date', $date)
                ->count();

            $series[] = [
                'day' => $cursor->format('D'),
                'date' => $date,
                'created' => $created,
                'completed' => $completed,
                'rejected' => $rejected,
            ];

            $createdWeek += $created;
            $completedWeek += $completed;
            $rejectedWeek += $rejected;
            $cursor->addDay();
        }

        $days = max(count($series), 1);

        return [
            'bucket' => '7d',
            'series' => $series,
            'totals' => [
                'created_week' => $createdWeek,
                'completed_week' => $completedWeek,
                'rejected_week' => $rejectedWeek,
                'avg_created_per_day' => round($createdWeek / $days, 1),
            ],
        ];
    }

    /**
     * @return array{series: list<array{status: string, label: string, count: int}>}
     */
    private function buildStatusBreakdown(int $facilityId): array
    {
        $statuses = ['pending', 'accepted', 'rejected', 'completed', 'cancelled'];
        $labels = [
            'pending' => 'Pending',
            'accepted' => 'Accepted',
            'rejected' => 'Rejected',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
        ];

        $series = [];
        foreach ($statuses as $status) {
            $series[] = [
                'status' => $status,
                'label' => $labels[$status] ?? ucfirst($status),
                'count' => (int) Referral::query()
                    ->where(function ($q) use ($facilityId): void {
                        $q->where('facility_id', $facilityId)
                            ->orWhere('receiving_facility_id', $facilityId);
                    })
                    ->where('status', $status)
                    ->count(),
            ];
        }

        return ['series' => $series];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildRecentReferrals(int $facilityId, int $limit = 8): array
    {
        return Referral::query()
            ->where(function ($q) use ($facilityId): void {
                $q->where('facility_id', $facilityId)
                    ->orWhere('receiving_facility_id', $facilityId);
            })
            ->with([
                'patient.user:id,first_name,last_name',
                'referringFacility:id,facility_name',
                'receivingFacility:id,facility_name',
            ])
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get()
            ->map(static function (Referral $referral): array {
                $patient = $referral->patient;
                $patientName = $patient?->user
                    ? trim(($patient->user->first_name ?? '').' '.($patient->user->last_name ?? ''))
                    : null;

                return [
                    'id' => $referral->referral_uuid,
                    'referral_uuid' => $referral->referral_uuid,
                    'status' => $referral->status,
                    'priority' => $referral->priority,
                    'referral_type' => $referral->referral_type,
                    'patient_name' => $patientName ?: ('Patient #'.$referral->patient_id),
                    'referring_facility_name' => $referral->referringFacility?->facility_name,
                    'receiving_facility_name' => $referral->receivingFacility?->facility_name,
                    'referral_date' => $referral->referral_date?->toIso8601String(),
                    'updated_at' => $referral->updated_at?->toIso8601String(),
                ];
            })
            ->values()
            ->all();
    }
}
