<?php

declare(strict_types=1);

namespace App\Services\Ambulance;

use App\Models\Ambulance;
use App\Models\AmbulanceTrip;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Aggregates facility-scoped ambulance fleet and trip metrics for the intelligence dashboard.
 */
class AmbulanceDashboardService
{
    private const ACTIVE_TRIP_STATUSES = [
        'requested',
        'dispatched',
        'en_route',
        'on_scene',
        'patient_contact',
        'depart_scene',
        'at_destination',
    ];

    public function getDashboard(int $facilityId, ?string $tz = null): array
    {
        try {
            $now = $tz ? Carbon::now($tz) : Carbon::now();
            $today = $now->toDateString();
            $yesterday = $now->copy()->subDay()->toDateString();
            $sevenDaysAgo = $now->copy()->subDays(6)->startOfDay();

            $vehiclesQuery = Ambulance::query()->where('facility_id', $facilityId);

            $totalVehicles = (int) (clone $vehiclesQuery)->count();
            $availableVehicles = (int) (clone $vehiclesQuery)->where('status', 'available')->count();
            $inServiceVehicles = (int) (clone $vehiclesQuery)->where('status', 'in_service')->count();
            $maintenanceVehicles = (int) (clone $vehiclesQuery)->where('status', 'maintenance')->count();

            $activeTrips = (int) AmbulanceTrip::query()
                ->where('facility_id', $facilityId)
                ->whereIn('status', self::ACTIVE_TRIP_STATUSES)
                ->count();

            $completedToday = (int) AmbulanceTrip::query()
                ->where('facility_id', $facilityId)
                ->where('status', 'completed')
                ->whereDate('completed_at', $today)
                ->count();

            $completedYesterday = (int) AmbulanceTrip::query()
                ->where('facility_id', $facilityId)
                ->where('status', 'completed')
                ->whereDate('completed_at', $yesterday)
                ->count();

            $dispatchedToday = (int) AmbulanceTrip::query()
                ->where('facility_id', $facilityId)
                ->whereDate('dispatched_at', $today)
                ->count();

            $completedChangePct = $completedYesterday > 0
                ? round((($completedToday - $completedYesterday) / $completedYesterday) * 100, 1)
                : ($completedToday > 0 ? 100.0 : 0.0);

            $tripActivity = $this->buildTripActivity($facilityId, $sevenDaysAgo, $now);
            $fleetStatus = $this->buildFleetStatus($facilityId);
            $recentTrips = $this->buildRecentTrips($facilityId);

            return [
                'success' => true,
                'message' => 'Ambulance dashboard retrieved successfully.',
                'data' => [
                    'summary' => [
                        'total_vehicles' => [
                            'value' => $totalVehicles,
                            'change_label' => 'Registered ambulances at this facility',
                        ],
                        'available_vehicles' => [
                            'value' => $availableVehicles,
                            'change_label' => 'Ready for dispatch',
                        ],
                        'active_trips' => [
                            'value' => $activeTrips,
                            'change_label' => 'In-progress transport requests',
                        ],
                        'completed_trips_today' => [
                            'value' => $completedToday,
                            'change_pct' => $completedChangePct,
                            'change_label' => 'vs yesterday',
                        ],
                        'in_service_vehicles' => [
                            'value' => $inServiceVehicles,
                            'change_label' => 'Currently assigned to trips',
                        ],
                        'maintenance_vehicles' => [
                            'value' => $maintenanceVehicles,
                            'change_label' => 'Out of rotation for service',
                        ],
                        'dispatched_today' => [
                            'value' => $dispatchedToday,
                            'change_label' => 'Trips dispatched today',
                        ],
                    ],
                    'trip_activity' => $tripActivity,
                    'fleet_status' => $fleetStatus,
                    'recent_trips' => $recentTrips,
                ],
            ];
        } catch (Throwable $e) {
            Log::error('Ambulance dashboard aggregation failed', [
                'facility_id' => $facilityId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to build ambulance dashboard.',
            ];
        }
    }

    /**
     * @return array{bucket: string, series: list<array{day: string, date: string, completed: int, dispatched: int, cancelled: int}>, totals: array<string, int|float>}
     */
    private function buildTripActivity(int $facilityId, Carbon $start, Carbon $end): array
    {
        $series = [];
        $cursor = $start->copy()->startOfDay();
        $completedWeek = 0;
        $dispatchedWeek = 0;
        $cancelledWeek = 0;

        while ($cursor->lte($end)) {
            $date = $cursor->toDateString();
            $completed = (int) AmbulanceTrip::query()
                ->where('facility_id', $facilityId)
                ->where('status', 'completed')
                ->whereDate('completed_at', $date)
                ->count();
            $dispatched = (int) AmbulanceTrip::query()
                ->where('facility_id', $facilityId)
                ->whereDate('dispatched_at', $date)
                ->count();
            $cancelled = (int) AmbulanceTrip::query()
                ->where('facility_id', $facilityId)
                ->where('status', 'cancelled')
                ->whereDate('cancelled_at', $date)
                ->count();

            $series[] = [
                'day' => $cursor->format('D'),
                'date' => $date,
                'completed' => $completed,
                'dispatched' => $dispatched,
                'cancelled' => $cancelled,
            ];

            $completedWeek += $completed;
            $dispatchedWeek += $dispatched;
            $cancelledWeek += $cancelled;
            $cursor->addDay();
        }

        $days = max(count($series), 1);

        return [
            'bucket' => '7d',
            'series' => $series,
            'totals' => [
                'completed_week' => $completedWeek,
                'dispatched_week' => $dispatchedWeek,
                'cancelled_week' => $cancelledWeek,
                'avg_completed_per_day' => round($completedWeek / $days, 1),
            ],
        ];
    }

    /**
     * @return array{series: list<array{status: string, label: string, count: int}>}
     */
    private function buildFleetStatus(int $facilityId): array
    {
        $statuses = ['available', 'in_service', 'maintenance', 'out_of_service'];
        $labels = [
            'available' => 'Available',
            'in_service' => 'In service',
            'maintenance' => 'Maintenance',
            'out_of_service' => 'Out of service',
        ];

        $series = [];
        foreach ($statuses as $status) {
            $series[] = [
                'status' => $status,
                'label' => $labels[$status] ?? ucfirst(str_replace('_', ' ', $status)),
                'count' => (int) Ambulance::query()
                    ->where('facility_id', $facilityId)
                    ->where('status', $status)
                    ->count(),
            ];
        }

        return ['series' => $series];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildRecentTrips(int $facilityId, int $limit = 8): array
    {
        return AmbulanceTrip::query()
            ->where('facility_id', $facilityId)
            ->with(['ambulance:id,vehicle_identifier,ambulance_uuid'])
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get()
            ->map(static function (AmbulanceTrip $trip): array {
                return [
                    'id' => $trip->trip_uuid,
                    'trip_uuid' => $trip->trip_uuid,
                    'status' => $trip->status,
                    'priority' => $trip->priority,
                    'trip_type' => $trip->trip_type,
                    'pickup_location' => $trip->pickup_location,
                    'destination_location' => $trip->destination_location,
                    'vehicle_identifier' => $trip->ambulance?->vehicle_identifier,
                    'updated_at' => $trip->updated_at?->toIso8601String(),
                ];
            })
            ->values()
            ->all();
    }
}
