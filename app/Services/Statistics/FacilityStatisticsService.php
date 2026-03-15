<?php

declare(strict_types=1);

namespace App\Services\Statistics;

use App\Models\Facility;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * FacilityStatisticsService
 * 
 * Provides comprehensive platform-wide facility statistics and analytics for administrators.
 * All queries are optimized for performance with proper indexing.
 */
class FacilityStatisticsService
{
    /**
     * Get all dashboard statistics in a single call
     *
     * @param array $filters
     * @return array
     */
    public function getDashboardStatistics(array $filters = []): array
    {
        return [
            'key_metrics' => $this->getKeyMetrics(),
            'facility_type_distribution' => $this->getFacilityTypeDistribution(),
            'facility_tier_distribution' => $this->getFacilityTierDistribution(),
            'nature_distribution' => $this->getNatureDistribution(),
            'operational_status_distribution' => $this->getOperationalStatusDistribution(),
            'geographic_distribution' => $this->getGeographicDistribution(),
            'capacity_metrics' => $this->getCapacityMetrics(),
            'service_availability' => $this->getServiceAvailability(),
            'specialty_services' => $this->getSpecialtyServices(),
            'emergency_capabilities' => $this->getEmergencyCapabilities(),
            'accreditation_stats' => $this->getAccreditationStats(),
            'license_expiry_metrics' => $this->getLicenseExpiryMetrics(),
            'performance_metrics' => $this->getPerformanceMetrics(),
            'data_residency_distribution' => $this->getDataResidencyDistribution(),
            'facility_growth_trends' => $this->getFacilityGrowthTrends(),
            'timestamp' => now()->toIso8601String(),
        ];
    }

    /**
     * Get key metrics for the dashboard
     *
     * @return array
     */
    public function getKeyMetrics(): array
    {
        $totalFacilities = (int) Facility::count();
        $activeFacilities = (int) Facility::where('operational_status', 'fully_operational')->count();
        
        // Facilities added in last 30 days
        $newFacilities = (int) Facility::where('created_at', '>=', now()->subDays(30))->count();
        $newFacilitiesLastMonth = (int) Facility::whereBetween('created_at', [
            now()->subDays(60),
            now()->subDays(30)
        ])->count();

        // Facilities with emergency departments
        $withEmergencyDept = (int) Facility::where('has_emergency_department', true)->count();
        
        // Total bed capacity
        $totalBeds = (int) (Facility::sum('bed_capacity') ?? 0);
        
        // Facilities with intensive care
        $withICU = (int) Facility::where('has_intensive_care', true)->count();
        
        // 24/7 facilities
        $open247 = (int) Facility::where('is_24_7', true)->count();

        // Calculate changes
        $newChange = $newFacilitiesLastMonth > 0 
            ? round((($newFacilities - $newFacilitiesLastMonth) / $newFacilitiesLastMonth) * 100, 1)
            : ($newFacilities > 0 ? 100 : 0);

        $activeRate = $totalFacilities > 0 ? round(($activeFacilities / $totalFacilities) * 100, 1) : 0;

        return [
            [
                'label' => 'Total Facilities',
                'value' => number_format($totalFacilities),
                'change' => null,
                'subtext' => 'All time',
                'icon' => 'Building2',
                'color' => 'text-blue-500',
                'bgColor' => 'bg-blue-50',
            ],
            [
                'label' => 'Active Facilities',
                'value' => number_format($activeFacilities),
                'change' => $activeRate,
                'subtext' => 'of total',
                'icon' => 'CheckCircle',
                'color' => 'text-green-500',
                'bgColor' => 'bg-green-50',
            ],
            [
                'label' => 'New Facilities (30d)',
                'value' => $newFacilities,
                'change' => $newChange,
                'subtext' => 'vs previous 30d',
                'icon' => 'PlusCircle',
                'color' => 'text-indigo-500',
                'bgColor' => 'bg-indigo-50',
            ],
            [
                'label' => 'Emergency Dept.',
                'value' => number_format($withEmergencyDept),
                'change' => $totalFacilities > 0 ? round(($withEmergencyDept / $totalFacilities) * 100, 1) : 0,
                'subtext' => 'of facilities',
                'icon' => 'Ambulance',
                'color' => 'text-red-500',
                'bgColor' => 'bg-red-50',
            ],
            [
                'label' => 'Total Bed Capacity',
                'value' => number_format($totalBeds),
                'change' => null,
                'subtext' => 'across all facilities',
                'icon' => 'Bed',
                'color' => 'text-purple-500',
                'bgColor' => 'bg-purple-50',
            ],
            [
                'label' => '24/7 Facilities',
                'value' => number_format($open247),
                'change' => $totalFacilities > 0 ? round(($open247 / $totalFacilities) * 100, 1) : 0,
                'subtext' => 'of facilities',
                'icon' => 'Clock',
                'color' => 'text-yellow-500',
                'bgColor' => 'bg-yellow-50',
            ],
        ];
    }

    /**
     * Get facility type distribution
     *
     * @return array
     */
    public function getFacilityTypeDistribution(): array
    {
        $distribution = Facility::select('facility_type', DB::raw('COUNT(*) as count'))
            ->groupBy('facility_type')
            ->orderByDesc('count')
            ->get();

        $colors = [
            'hospital' => '#3B82F6',
            'clinic' => '#10B981',
            'urgent_care' => '#F59E0B',
            'emergency_department' => '#EF4444',
            'ambulatory_surgery_center' => '#8B5CF6',
            'diagnostic_center' => '#EC4899',
            'rehabilitation_center' => '#14B8A6',
            'long_term_care' => '#F97316',
            'hospice' => '#6B7280',
            'community_health_center' => '#84CC16',
            'specialty_center' => '#A855F7',
            'telehealth_hub' => '#06B6D4',
            'laboratory' => '#D946EF',
            'pharmacy' => '#F43F5E',
        ];

        return $distribution->map(function ($item) use ($colors) {
            return [
                'type' => $item->facility_type,
                'type_label' => $this->formatFacilityType($item->facility_type),
                'count' => (int) $item->count,
                'color' => $colors[$item->facility_type] ?? '#6B7280',
            ];
        })->toArray();
    }

    /**
     * Get facility tier distribution
     *
     * @return array
     */
    public function getFacilityTierDistribution(): array
    {
        $distribution = Facility::select('facility_tier', DB::raw('COUNT(*) as count'))
            ->whereNotNull('facility_tier')
            ->groupBy('facility_tier')
            ->orderByDesc('count')
            ->get();

        $colors = [
            'tertiary' => '#EF4444',
            'secondary' => '#F59E0B',
            'primary' => '#10B981',
            'specialized' => '#8B5CF6',
        ];

        return $distribution->map(function ($item) use ($colors) {
            return [
                'tier' => $item->facility_tier,
                'tier_label' => ucfirst($item->facility_tier),
                'count' => (int) $item->count,
                'color' => $colors[$item->facility_tier] ?? '#6B7280',
            ];
        })->toArray();
    }

    /**
     * Get nature of facility distribution
     *
     * @return array
     */
    public function getNatureDistribution(): array
    {
        $distribution = Facility::select('nature_of_facility', DB::raw('COUNT(*) as count'))
            ->groupBy('nature_of_facility')
            ->orderByDesc('count')
            ->get();

        $colors = [
            'government' => '#3B82F6',
            'private' => '#10B981',
            'faith_based' => '#8B5CF6',
            'ngo' => '#F59E0B',
            'military' => '#EF4444',
            'academic' => '#EC4899',
            'public_private_partnership' => '#14B8A6',
        ];

        return $distribution->map(function ($item) use ($colors) {
            return [
                'nature' => $item->nature_of_facility,
                'nature_label' => $this->formatNatureOfFacility($item->nature_of_facility),
                'count' => (int) $item->count,
                'color' => $colors[$item->nature_of_facility] ?? '#6B7280',
            ];
        })->toArray();
    }

    /**
     * Get operational status distribution
     *
     * @return array
     */
    public function getOperationalStatusDistribution(): array
    {
        $distribution = Facility::select('operational_status', DB::raw('COUNT(*) as count'))
            ->groupBy('operational_status')
            ->orderByDesc('count')
            ->get();

        $colors = [
            'fully_operational' => '#10B981',
            'limited_services' => '#F59E0B',
            'emergency_only' => '#EF4444',
            'temporarily_closed' => '#6B7280',
            'permanently_closed' => '#374151',
            'under_construction' => '#8B5CF6',
        ];

        return $distribution->map(function ($item) use ($colors) {
            return [
                'status' => $item->operational_status,
                'status_label' => $this->formatOperationalStatus($item->operational_status),
                'count' => (int) $item->count,
                'color' => $colors[$item->operational_status] ?? '#6B7280',
            ];
        })->toArray();
    }

    /**
     * Get geographic distribution
     *
     * @return array
     */
    public function getGeographicDistribution(): array
    {
        // By country
        $byCountry = Facility::whereNotNull('country_code')
            ->select('country_code', DB::raw('COUNT(*) as count'))
            ->groupBy('country_code')
            ->orderByDesc('count')
            ->get()
            ->map(function ($item) {
                return [
                    'country_code' => $item->country_code,
                    'country_name' => $this->getCountryName($item->country_code),
                    'count' => (int) $item->count,
                ];
            });

        // By state/province (top 10)
        $byState = Facility::whereNotNull('state_province')
            ->select('state_province', DB::raw('COUNT(*) as count'))
            ->groupBy('state_province')
            ->orderByDesc('count')
            ->limit(10)
            ->get()
            ->map(function ($item) {
                return [
                    'state' => $item->state_province,
                    'count' => (int) $item->count,
                ];
            });

        // By city (top 10)
        $byCity = Facility::whereNotNull('city')
            ->select('city', DB::raw('COUNT(*) as count'))
            ->groupBy('city')
            ->orderByDesc('count')
            ->limit(10)
            ->get()
            ->map(function ($item) {
                return [
                    'city' => $item->city,
                    'count' => (int) $item->count,
                ];
            });

        return [
            'by_country' => $byCountry,
            'by_state' => $byState,
            'by_city' => $byCity,
        ];
    }

    /**
     * Get capacity metrics
     *
     * @return array
     */
    public function getCapacityMetrics(): array
    {
        // Bed capacity distribution
        $bedCapacityRanges = [
            '0-50' => [0, 50],
            '51-100' => [51, 100],
            '101-200' => [101, 200],
            '201-500' => [201, 500],
            '500+' => [501, 999999],
        ];

        $bedDistribution = [];
        foreach ($bedCapacityRanges as $range => [$min, $max]) {
            $count = (int) Facility::whereNotNull('bed_capacity')
                ->whereBetween('bed_capacity', [$min, $max])
                ->count();
            
            $bedDistribution[] = [
                'range' => $range,
                'count' => $count,
                'color' => $this->getCapacityRangeColor($range),
            ];
        }

        // Facilities with bed capacity vs without
        $withBeds = (int) Facility::whereNotNull('bed_capacity')->where('bed_capacity', '>', 0)->count();
        $withoutBeds = (int) (Facility::count() - $withBeds);

        // Average bed capacity
        $avgBedCapacity = round((float) (Facility::avg('bed_capacity') ?? 0), 1);

        // Total capacity by tier
        $capacityByTier = Facility::whereNotNull('bed_capacity')
            ->select('facility_tier', DB::raw('SUM(bed_capacity) as total_beds'))
            ->groupBy('facility_tier')
            ->get()
            ->mapWithKeys(function ($item) {
                return [$item->facility_tier => (int) $item->total_beds];
            });

        return [
            'bed_distribution' => $bedDistribution,
            'facilities_with_beds' => $withBeds,
            'facilities_without_beds' => $withoutBeds,
            'avg_bed_capacity' => $avgBedCapacity,
            'total_beds_by_tier' => $capacityByTier,
        ];
    }

    /**
     * Get service availability statistics - FIXED LINE 396
     *
     * @return array
     */
    public function getServiceAvailability(): array
    {
        // Most common services
        $facilities = Facility::whereNotNull('available_services')->get();
        
        $serviceCounts = [];
        foreach ($facilities as $facility) {
            // FIX: Check if available_services is a string or already an array
            $services = [];
            if (!empty($facility->available_services)) {
                if (is_string($facility->available_services)) {
                    $services = json_decode($facility->available_services, true) ?? [];
                } elseif (is_array($facility->available_services)) {
                    $services = $facility->available_services;
                }
            }
            
            foreach ($services as $service) {
                $serviceCounts[$service] = ($serviceCounts[$service] ?? 0) + 1;
            }
        }

        arsort($serviceCounts);
        $topServices = array_slice($serviceCounts, 0, 10, true);

        $topServicesFormatted = [];
        $totalFacilities = (int) Facility::count();
        foreach ($topServices as $service => $count) {
            $topServicesFormatted[] = [
                'service' => $service,
                'count' => $count,
                'percentage' => $totalFacilities > 0 ? round(((float) $count / (float) $totalFacilities) * 100, 1) : 0,
            ];
        }

        return [
            'top_services' => $topServicesFormatted,
            'total_unique_services' => count($serviceCounts),
            'avg_services_per_facility' => $facilities->count() > 0 
                ? round(array_sum($serviceCounts) / (float) $facilities->count(), 1)
                : 0,
        ];
    }

    /**
     * Get specialty services statistics
     *
     * @return array
     */
    public function getSpecialtyServices(): array
    {
        $facilities = Facility::whereNotNull('specialty_services')->get();
        
        $specialtyCounts = [];
        foreach ($facilities as $facility) {
            // FIX: Check if specialty_services is a string or already an array
            $specialties = [];
            if (!empty($facility->specialty_services)) {
                if (is_string($facility->specialty_services)) {
                    $specialties = json_decode($facility->specialty_services, true) ?? [];
                } elseif (is_array($facility->specialty_services)) {
                    $specialties = $facility->specialty_services;
                }
            }
            
            foreach ($specialties as $specialty) {
                $specialtyCounts[$specialty] = ($specialtyCounts[$specialty] ?? 0) + 1;
            }
        }

        arsort($specialtyCounts);
        $topSpecialties = array_slice($specialtyCounts, 0, 10, true);

        $topSpecialtiesFormatted = [];
        $totalFacilities = (int) Facility::count();
        foreach ($topSpecialties as $specialty => $count) {
            $topSpecialtiesFormatted[] = [
                'specialty' => $specialty,
                'count' => $count,
                'percentage' => $totalFacilities > 0 ? round(((float) $count / (float) $totalFacilities) * 100, 1) : 0,
            ];
        }

        return [
            'top_specialties' => $topSpecialtiesFormatted,
            'total_unique_specialties' => count($specialtyCounts),
            'facilities_with_specialties' => $facilities->count(),
        ];
    }

    /**
     * Get emergency capabilities statistics
     *
     * @return array
     */
    public function getEmergencyCapabilities(): array
    {
        $total = (int) Facility::count();

        $withEmergencyDept = (int) Facility::where('has_emergency_department', true)->count();
        $withTraumaCenter = (int) Facility::where('has_trauma_center', true)->count();
        
        // Trauma center levels
        $traumaLevels = Facility::whereNotNull('trauma_center_level')
            ->select('trauma_center_level', DB::raw('COUNT(*) as count'))
            ->groupBy('trauma_center_level')
            ->orderBy('trauma_center_level')
            ->get()
            ->map(function ($item) {
                return [
                    'level' => 'Level ' . $item->trauma_center_level,
                    'count' => (int) $item->count,
                ];
            });

        $withICU = (int) Facility::where('has_intensive_care', true)->count();
        $withNICU = (int) Facility::where('has_neonatal_icu', true)->count();
        $withCathLab = (int) Facility::where('has_cardiac_cath_lab', true)->count();

        return [
            'emergency_dept' => [
                'count' => $withEmergencyDept,
                'percentage' => $total > 0 ? round(((float) $withEmergencyDept / (float) $total) * 100, 1) : 0,
            ],
            'trauma_center' => [
                'count' => $withTraumaCenter,
                'percentage' => $total > 0 ? round(((float) $withTraumaCenter / (float) $total) * 100, 1) : 0,
                'by_level' => $traumaLevels,
            ],
            'intensive_care' => [
                'count' => $withICU,
                'percentage' => $total > 0 ? round(((float) $withICU / (float) $total) * 100, 1) : 0,
            ],
            'neonatal_icu' => [
                'count' => $withNICU,
                'percentage' => $total > 0 ? round(((float) $withNICU / (float) $total) * 100, 1) : 0,
            ],
            'cardiac_cath_lab' => [
                'count' => $withCathLab,
                'percentage' => $total > 0 ? round(((float) $withCathLab / (float) $total) * 100, 1) : 0,
            ],
        ];
    }

    /**
     * Get accreditation statistics
     *
     * @return array
     */
    public function getAccreditationStats(): array
    {
        $facilities = Facility::whereNotNull('accreditations')->get();
        
        $accreditationCounts = [];
        foreach ($facilities as $facility) {
            // FIX: Check if accreditations is a string or already an array
            $accreditations = [];
            if (!empty($facility->accreditations)) {
                if (is_string($facility->accreditations)) {
                    $accreditations = json_decode($facility->accreditations, true) ?? [];
                } elseif (is_array($facility->accreditations)) {
                    $accreditations = $facility->accreditations;
                }
            }
            
            foreach ($accreditations as $accreditation) {
                $accreditationCounts[$accreditation] = ($accreditationCounts[$accreditation] ?? 0) + 1;
            }
        }

        arsort($accreditationCounts);
        $topAccreditations = array_slice($accreditationCounts, 0, 10, true);

        $topAccreditationsFormatted = [];
        $totalFacilities = (int) Facility::count();
        foreach ($topAccreditations as $accreditation => $count) {
            $topAccreditationsFormatted[] = [
                'accreditation' => $accreditation,
                'count' => $count,
                'percentage' => $totalFacilities > 0 ? round(((float) $count / (float) $totalFacilities) * 100, 1) : 0,
            ];
        }

        return [
            'top_accreditations' => $topAccreditationsFormatted,
            'accredited_facilities' => $facilities->count(),
            'percentage_accredited' => $totalFacilities > 0 
                ? round(((float) $facilities->count() / (float) $totalFacilities) * 100, 1)
                : 0,
        ];
    }

    /**
     * Get license expiry metrics
     *
     * @return array
     */
    public function getLicenseExpiryMetrics(): array
    {
        $now = now();
        
        // Expiring in next 30 days
        $expiringSoon = (int) Facility::whereNotNull('license_expiry_date')
            ->whereBetween('license_expiry_date', [$now, $now->copy()->addDays(30)])
            ->count();

        // Expiring in 31-90 days
        $expiringMedium = (int) Facility::whereNotNull('license_expiry_date')
            ->whereBetween('license_expiry_date', [$now->copy()->addDays(31), $now->copy()->addDays(90)])
            ->count();

        // Expiring after 90 days
        $expiringLater = (int) Facility::whereNotNull('license_expiry_date')
            ->where('license_expiry_date', '>', $now->copy()->addDays(90))
            ->count();

        // Already expired
        $expired = (int) Facility::whereNotNull('license_expiry_date')
            ->where('license_expiry_date', '<', $now)
            ->count();

        // No license date
        $noLicense = (int) Facility::whereNull('license_expiry_date')->count();

        return [
            'expiring_soon' => $expiringSoon,
            'expiring_medium' => $expiringMedium,
            'expiring_later' => $expiringLater,
            'expired' => $expired,
            'no_license_date' => $noLicense,
            'total_with_license' => (int) Facility::whereNotNull('license_expiry_date')->count(),
        ];
    }

    /**
     * Get performance metrics
     *
     * @return array
     */
    public function getPerformanceMetrics(): array
    {
        // Average wait time by facility type
        $avgWaitTimeByType = Facility::whereNotNull('average_wait_time_minutes')
            ->select('facility_type', DB::raw('AVG(average_wait_time_minutes) as avg_wait'))
            ->groupBy('facility_type')
            ->get()
            ->mapWithKeys(function ($item) {
                return [$item->facility_type => round((float) $item->avg_wait, 1)];
            });

        // Average patient satisfaction by tier
        $avgSatisfactionByTier = Facility::whereNotNull('patient_satisfaction_score')
            ->select('facility_tier', DB::raw('AVG(patient_satisfaction_score) as avg_score'))
            ->groupBy('facility_tier')
            ->get()
            ->mapWithKeys(function ($item) {
                return [$item->facility_tier => round((float) $item->avg_score, 2)];
            });

        // Monthly patient volume by tier
        $volumeByTier = Facility::whereNotNull('monthly_patient_volume')
            ->select('facility_tier', DB::raw('SUM(monthly_patient_volume) as total_volume'))
            ->groupBy('facility_tier')
            ->get()
            ->mapWithKeys(function ($item) {
                return [$item->facility_tier => (int) $item->total_volume];
            });

        return [
            'avg_wait_time_overall' => round((float) (Facility::avg('average_wait_time_minutes') ?? 0), 1),
            'avg_wait_time_by_type' => $avgWaitTimeByType,
            'avg_satisfaction_overall' => round((float) (Facility::avg('patient_satisfaction_score') ?? 0), 2),
            'avg_satisfaction_by_tier' => $avgSatisfactionByTier,
            'total_monthly_volume' => (int) (Facility::sum('monthly_patient_volume') ?? 0),
            'volume_by_tier' => $volumeByTier,
            'facilities_with_performance_data' => (int) Facility::whereNotNull('average_wait_time_minutes')->count(),
        ];
    }

    /**
     * Get data residency distribution
     *
     * @return array
     */
    public function getDataResidencyDistribution(): array
    {
        $distribution = Facility::whereNotNull('data_residency_region')
            ->select('data_residency_region', DB::raw('COUNT(*) as count'))
            ->groupBy('data_residency_region')
            ->orderByDesc('count')
            ->get();

        return $distribution->map(function ($item) {
            return [
                'region' => $item->data_residency_region,
                'count' => (int) $item->count,
            ];
        })->toArray();
    }

    /**
     * Get facility growth trends
     *
     * @return array
     */
    public function getFacilityGrowthTrends(): array
    {
        $months = 12;
        $trends = [];

        for ($i = $months - 1; $i >= 0; $i--) {
            $month = now()->subMonths($i);
            $monthStart = $month->copy()->startOfMonth();
            $monthEnd = $month->copy()->endOfMonth();

            $newFacilities = (int) Facility::whereBetween('created_at', [$monthStart, $monthEnd])->count();
            
            $cumulativeTotal = (int) Facility::where('created_at', '<=', $monthEnd)->count();

            $trends[] = [
                'month' => $month->format('M Y'),
                'month_key' => $month->format('Y-m'),
                'new_facilities' => $newFacilities,
                'cumulative_total' => $cumulativeTotal,
            ];
        }

        return $trends;
    }

    /**
     * Format facility type for display
     *
     * @param string|null $type
     * @return string
     */
    private function formatFacilityType(?string $type): string
    {
        return match($type) {
            'hospital' => 'Hospital',
            'clinic' => 'Clinic',
            'urgent_care' => 'Urgent Care',
            'emergency_department' => 'Emergency Department',
            'ambulatory_surgery_center' => 'Ambulatory Surgery Center',
            'diagnostic_center' => 'Diagnostic Center',
            'rehabilitation_center' => 'Rehabilitation Center',
            'long_term_care' => 'Long-Term Care',
            'hospice' => 'Hospice',
            'community_health_center' => 'Community Health Center',
            'specialty_center' => 'Specialty Center',
            'telehealth_hub' => 'Telehealth Hub',
            'laboratory' => 'Laboratory',
            'pharmacy' => 'Pharmacy',
            default => ucfirst(str_replace('_', ' ', $type ?? 'unknown')),
        };
    }

    /**
     * Format nature of facility for display
     *
     * @param string|null $nature
     * @return string
     */
    private function formatNatureOfFacility(?string $nature): string
    {
        return match($nature) {
            'government' => 'Government',
            'private' => 'Private',
            'faith_based' => 'Faith-Based',
            'ngo' => 'NGO',
            'military' => 'Military',
            'academic' => 'Academic',
            'public_private_partnership' => 'Public-Private Partnership',
            default => ucfirst(str_replace('_', ' ', $nature ?? 'unknown')),
        };
    }

    /**
     * Format operational status for display
     *
     * @param string|null $status
     * @return string
     */
    private function formatOperationalStatus(?string $status): string
    {
        return match($status) {
            'fully_operational' => 'Fully Operational',
            'limited_services' => 'Limited Services',
            'emergency_only' => 'Emergency Only',
            'temporarily_closed' => 'Temporarily Closed',
            'permanently_closed' => 'Permanently Closed',
            'under_construction' => 'Under Construction',
            default => ucfirst(str_replace('_', ' ', $status ?? 'unknown')),
        };
    }

    /**
     * Get color for capacity range
     *
     * @param string $range
     * @return string
     */
    private function getCapacityRangeColor(string $range): string
    {
        return match($range) {
            '0-50' => '#3B82F6',
            '51-100' => '#10B981',
            '101-200' => '#F59E0B',
            '201-500' => '#EF4444',
            '500+' => '#8B5CF6',
            default => '#6B7280',
        };
    }

    /**
     * Get country name from code
     *
     * @param string|null $code
     * @return string
     */
    private function getCountryName(?string $code): string
    {
        $countries = [
            'US' => 'United States',
            'UG' => 'Uganda',
            'KE' => 'Kenya',
            'TZ' => 'Tanzania',
            'RW' => 'Rwanda',
            'SS' => 'South Sudan',
            'CD' => 'DR Congo',
            'GB' => 'United Kingdom',
            'CA' => 'Canada',
            'AU' => 'Australia',
        ];
        
        return $countries[$code] ?? $code;
    }
}