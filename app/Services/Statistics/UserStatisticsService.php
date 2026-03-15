<?php

declare(strict_types=1);

namespace App\Services\Statistics;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * UserStatisticsService
 * 
 * Provides comprehensive platform-wide user statistics and analytics for administrators.
 * All queries are optimized for performance with proper indexing.
 */
class UserStatisticsService
{
    /**
     * Get all dashboard statistics in a single call
     *
     * @param array $filters
     * @return array
     */
    public function getDashboardStatistics(array $filters = []): array
    {
        $dateRange = $filters['date_range'] ?? '30_days';

        return [
            'key_metrics' => $this->getKeyMetrics($dateRange),
            'verification_funnel' => $this->getVerificationFunnel(),
            'daily_activity' => $this->getDailyActivity($dateRange),
            'weekly_trends' => $this->getWeeklyTrends($dateRange),
            'monthly_trends' => $this->getMonthlyTrends(),
            'demographic_distribution' => $this->getDemographicDistribution(),
            'mfa_adoption' => $this->getMfaAdoptionStats(),
            'geographic_distribution' => $this->getGeographicDistribution(),
            'platform_breakdown' => $this->getPlatformBreakdown(),
            'user_retention' => $this->getUserRetentionCohorts(),
            'security_metrics' => $this->getSecurityMetrics(),
            'staff_performance' => $this->getStaffPerformanceMetrics(),
            'timestamp' => now()->toIso8601String(),
        ];
    }

    /**
     * Get key metrics for the dashboard
     *
     * @param string $dateRange
     * @return array
     */
    public function getKeyMetrics(string $dateRange = '30_days'): array
    {
        $today = now()->startOfDay();
        $yesterday = now()->subDay()->startOfDay();
        $lastWeek = now()->subWeek()->startOfDay();
        $lastMonth = now()->subMonth()->startOfDay();

        // Total users - cast to int
        $totalUsers = (int) User::count();
        
        // New today
        $newToday = (int) User::where('created_at', '>=', $today)->count();
        $newYesterday = (int) User::whereBetween('created_at', [$yesterday, $today])->count();
        
        // Verified today
        $verifiedToday = (int) User::whereNotNull('identity_verified_at')
            ->where('identity_verified_at', '>=', $today)
            ->count();
        
        $verifiedYesterday = (int) User::whereNotNull('identity_verified_at')
            ->whereBetween('identity_verified_at', [$yesterday, $today])
            ->count();

        // Pending review
        $pendingReview = (int) User::where('identity_state', 'pending')->count();
        $pendingLastWeek = (int) User::where('identity_state', 'pending')
            ->where('created_at', '<', $lastWeek)
            ->count();

        // Active users (last 30 days)
        $activeUsers = (int) User::where('last_login_at', '>=', now()->subDays(30))->count();
        $activeLastMonth = (int) User::whereBetween('last_login_at', [now()->subDays(60), now()->subDays(30)])->count();

        // MFA adoption
        $mfaEnabled = (int) User::where('mfa_enabled', true)->count();
        $mfaRate = $totalUsers > 0 ? round(($mfaEnabled / $totalUsers) * 100, 1) : 0;

        // Calculate changes - with safe type casting
        $newChange = $this->calculatePercentageChange($newToday, $newYesterday);
        $verifiedChange = $this->calculatePercentageChange($verifiedToday, $verifiedYesterday);
        $pendingChange = $this->calculatePercentageChange($pendingReview, $pendingLastWeek);
        $activeChange = $this->calculatePercentageChange($activeUsers, $activeLastMonth);

        return [
            [
                'label' => 'Total Users',
                'value' => number_format($totalUsers),
                'change' => null,
                'subtext' => 'All time',
                'icon' => 'Users',
                'color' => 'text-blue-500',
                'bgColor' => 'bg-blue-50',
            ],
            [
                'label' => 'New Users Today',
                'value' => $newToday,
                'change' => $newChange,
                'subtext' => 'vs yesterday',
                'icon' => 'UserPlus',
                'color' => 'text-indigo-500',
                'bgColor' => 'bg-indigo-50',
            ],
            [
                'label' => 'Verified Today',
                'value' => $verifiedToday,
                'change' => $verifiedChange,
                'subtext' => 'vs yesterday',
                'icon' => 'CheckCircle',
                'color' => 'text-green-500',
                'bgColor' => 'bg-green-50',
            ],
            [
                'label' => 'Pending Review',
                'value' => $pendingReview,
                'change' => -abs($pendingChange),
                'subtext' => 'vs last week',
                'icon' => 'Clock',
                'color' => 'text-yellow-500',
                'bgColor' => 'bg-yellow-50',
            ],
            [
                'label' => 'Active Users (30d)',
                'value' => number_format($activeUsers),
                'change' => $activeChange,
                'subtext' => 'vs previous 30d',
                'icon' => 'Activity',
                'color' => 'text-purple-500',
                'bgColor' => 'bg-purple-50',
            ],
            [
                'label' => 'MFA Adoption',
                'value' => $mfaRate . '%',
                'change' => null,
                'subtext' => "{$mfaEnabled} users",
                'icon' => 'Shield',
                'color' => 'text-emerald-500',
                'bgColor' => 'bg-emerald-50',
            ],
        ];
    }

    /**
     * Calculate percentage change safely
     *
     * @param mixed $current
     * @param mixed $previous
     * @return float
     */
    private function calculatePercentageChange($current, $previous): float
    {
        $current = (float) $current;
        $previous = (float) $previous;
        
        if ($previous > 0) {
            return round((($current - $previous) / $previous) * 100, 1);
        }
        
        return $current > 0 ? 100.0 : 0.0;
    }

    /**
     * Get verification funnel statistics - FIXED LINE 219
     *
     * @return array
     */
    public function getVerificationFunnel(): array
    {
        $total = (int) User::count();
        
        $states = User::select('identity_state', DB::raw('COUNT(*) as count'))
            ->groupBy('identity_state')
            ->get()
            ->pluck('count', 'identity_state')
            ->toArray();

        $pending = (int) ($states['pending'] ?? 0);
        $verified = (int) ($states['verified'] ?? 0);
        $rejected = (int) ($states['rejected'] ?? 0);

        // Calculate conversion rates - with safe type casting (FIX FOR LINE 219)
        $verificationRate = $total > 0 ? round(((float) $verified / (float) $total) * 100, 1) : 0;
        $pendingRate = $total > 0 ? round(((float) $pending / (float) $total) * 100, 1) : 0;
        $rejectionRate = $total > 0 ? round(((float) $rejected / (float) $total) * 100, 1) : 0;

        // Get verification methods breakdown
        $methods = User::whereNotNull('identity_verified_at')
            ->whereNotNull('identity_verification_method')
            ->select('identity_verification_method', DB::raw('COUNT(*) as count'))
            ->groupBy('identity_verification_method')
            ->get()
            ->map(function ($item) {
                return [
                    'name' => $this->formatVerificationMethod($item->identity_verification_method),
                    'value' => (int) $item->count,
                    'color' => $this->getMethodColor($item->identity_verification_method),
                ];
            });

        // Average verification time
        $avgVerificationTime = User::whereNotNull('identity_verified_at')
            ->select(DB::raw('AVG(TIMESTAMPDIFF(HOUR, created_at, identity_verified_at)) as avg_hours'))
            ->first()
            ->avg_hours ?? 0;

        return [
            'funnel' => [
                ['stage' => 'Total Users', 'count' => $total, 'percentage' => 100],
                ['stage' => 'Pending', 'count' => $pending, 'percentage' => $pendingRate],
                ['stage' => 'Verified', 'count' => $verified, 'percentage' => $verificationRate],
                ['stage' => 'Rejected', 'count' => $rejected, 'percentage' => $rejectionRate],
            ],
            'verification_rate' => $verificationRate,
            'pending_rate' => $pendingRate,
            'rejection_rate' => $rejectionRate,
            'avg_verification_time_hours' => round((float) $avgVerificationTime, 1),
            'methods' => $methods,
        ];
    }

    /**
     * Get daily activity for charting
     *
     * @param string $dateRange
     * @return array
     */
    public function getDailyActivity(string $dateRange = '30_days'): array
    {
        $days = $this->getDaysFromRange($dateRange);
        $startDate = now()->subDays($days)->startOfDay();
        
        // Get daily new users
        $dailyNew = User::where('created_at', '>=', $startDate)
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        // Get daily verified users
        $dailyVerified = User::whereNotNull('identity_verified_at')
            ->where('identity_verified_at', '>=', $startDate)
            ->select(
                DB::raw('DATE(identity_verified_at) as date'),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy(DB::raw('DATE(identity_verified_at)'))
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        // Get daily active users (by login)
        $dailyActive = User::where('last_login_at', '>=', $startDate)
            ->select(
                DB::raw('DATE(last_login_at) as date'),
                DB::raw('COUNT(DISTINCT id) as count')
            )
            ->groupBy(DB::raw('DATE(last_login_at)'))
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        // Build complete dataset
        $result = [];
        $dayLabels = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        
        for ($i = 0; $i < $days; $i++) {
            $date = now()->subDays($days - 1 - $i)->startOfDay();
            $dateString = $date->toDateString();
            $dayName = $dayLabels[$date->dayOfWeek];
            
            $result[] = [
                'day' => $dayName,
                'date' => $dateString,
                'newUsers' => (int) ($dailyNew[$dateString]->count ?? 0),
                'verified' => (int) ($dailyVerified[$dateString]->count ?? 0),
                'active' => (int) ($dailyActive[$dateString]->count ?? 0),
            ];
        }

        return $result;
    }

    /**
     * Get weekly trends
     *
     * @param string $dateRange
     * @return array
     */
    public function getWeeklyTrends(string $dateRange = '12_weeks'): array
    {
        $weeks = $this->getWeeksFromRange($dateRange);
        $startDate = now()->subWeeks($weeks)->startOfWeek();
        
        $result = [];
        
        for ($i = 0; $i < $weeks; $i++) {
            $weekStart = now()->subWeeks($weeks - 1 - $i)->startOfWeek();
            $weekEnd = (clone $weekStart)->endOfWeek();
            $weekLabel = $weekStart->format('M d') . ' - ' . $weekEnd->format('M d');
            
            $newUsers = (int) User::whereBetween('created_at', [$weekStart, $weekEnd])->count();
            
            $verified = (int) User::whereNotNull('identity_verified_at')
                ->whereBetween('identity_verified_at', [$weekStart, $weekEnd])
                ->count();
            
            $withMfa = (int) User::where('mfa_enabled', true)
                ->whereBetween('created_at', [$weekStart, $weekEnd])
                ->count();

            $activeUsers = (int) User::whereBetween('last_login_at', [$weekStart, $weekEnd])
                ->count();

            $result[] = [
                'week' => $weekLabel,
                'week_number' => $weekStart->weekOfYear,
                'start_date' => $weekStart->toDateString(),
                'newUsers' => $newUsers,
                'verified' => $verified,
                'mfaEnabled' => $withMfa,
                'activeUsers' => $activeUsers,
            ];
        }

        return $result;
    }

    /**
     * Get monthly trends
     *
     * @return array
     */
    public function getMonthlyTrends(): array
    {
        $months = 12;
        $result = [];
        
        for ($i = 0; $i < $months; $i++) {
            $monthStart = now()->subMonths($months - 1 - $i)->startOfMonth();
            $monthEnd = (clone $monthStart)->endOfMonth();
            $monthLabel = $monthStart->format('M Y');
            
            $newUsers = (int) User::whereBetween('created_at', [$monthStart, $monthEnd])->count();
            
            $verified = (int) User::whereNotNull('identity_verified_at')
                ->whereBetween('identity_verified_at', [$monthStart, $monthEnd])
                ->count();
            
            $cumulativeTotal = (int) User::where('created_at', '<=', $monthEnd)->count();
            $cumulativeVerified = (int) User::whereNotNull('identity_verified_at')
                ->where('identity_verified_at', '<=', $monthEnd)
                ->count();

            $result[] = [
                'month' => $monthLabel,
                'month_key' => $monthStart->format('Y-m'),
                'newUsers' => $newUsers,
                'verified' => $verified,
                'cumulative_total' => $cumulativeTotal,
                'cumulative_verified' => $cumulativeVerified,
                'verification_rate' => $cumulativeTotal > 0 
                    ? round(((float) $cumulativeVerified / (float) $cumulativeTotal) * 100, 1)
                    : 0,
            ];
        }

        return $result;
    }

    /**
     * Get demographic distribution
     *
     * @return array
     */
    public function getDemographicDistribution(): array
    {
        // Age distribution
        $ageGroups = [
            '0-18' => [0, 18],
            '19-30' => [19, 30],
            '31-45' => [31, 45],
            '46-60' => [46, 60],
            '60+' => [61, 120],
        ];

        $ageDistribution = [];
        foreach ($ageGroups as $group => [$min, $max]) {
            $count = (int) User::whereNotNull('dob')
                ->whereRaw(
                    "TIMESTAMPDIFF(YEAR, dob, CURDATE()) BETWEEN ? AND ?",
                    [$min, $max]
                )
                ->count();
            
            $ageDistribution[] = [
                'group' => $group,
                'count' => $count,
                'color' => $this->getAgeGroupColor($group),
            ];
        }

        // Gender distribution
        $genderDistribution = User::whereNotNull('gender')
            ->select('gender', DB::raw('COUNT(*) as count'))
            ->groupBy('gender')
            ->get()
            ->map(function ($item) {
                return [
                    'name' => ucfirst($item->gender) ?? 'Unknown',
                    'value' => (int) $item->count,
                    'color' => $this->getGenderColor($item->gender),
                ];
            });

        // Title distribution (Mr, Mrs, Dr, etc.)
        $titleDistribution = User::whereNotNull('title')
            ->select('title', DB::raw('COUNT(*) as count'))
            ->groupBy('title')
            ->orderByDesc('count')
            ->limit(5)
            ->get()
            ->map(function ($item) {
                return [
                    'title' => $item->title,
                    'count' => (int) $item->count,
                ];
            });

        return [
            'age' => $ageDistribution,
            'gender' => $genderDistribution,
            'titles' => $titleDistribution,
        ];
    }

    /**
     * Get MFA adoption statistics
     *
     * @return array
     */
    public function getMfaAdoptionStats(): array
    {
        $total = (int) User::count();
        $mfaEnabled = (int) User::where('mfa_enabled', true)->count();
        $mfaDisabled = $total - $mfaEnabled;

        $adoptionRate = $total > 0 ? round(((float) $mfaEnabled / (float) $total) * 100, 1) : 0;

        // MFA by region
        $byRegion = User::whereNotNull('data_residency_region')
            ->select(
                'data_residency_region',
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN mfa_enabled = 1 THEN 1 ELSE 0 END) as mfa_count')
            )
            ->groupBy('data_residency_region')
            ->get()
            ->map(function ($item) {
                $total = (int) $item->total;
                $mfaCount = (int) $item->mfa_count;
                return [
                    'region' => $item->data_residency_region,
                    'total' => $total,
                    'mfa_count' => $mfaCount,
                    'adoption_rate' => $total > 0 
                        ? round(((float) $mfaCount / (float) $total) * 100, 1)
                        : 0,
                ];
            });

        // MFA by user age (cohort analysis)
        $byCohort = User::whereNotNull('created_at')
            ->select(
                DB::raw('YEAR(created_at) as cohort_year'),
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN mfa_enabled = 1 THEN 1 ELSE 0 END) as mfa_count')
            )
            ->groupBy(DB::raw('YEAR(created_at)'))
            ->orderBy('cohort_year')
            ->get()
            ->map(function ($item) {
                $total = (int) $item->total;
                $mfaCount = (int) $item->mfa_count;
                return [
                    'cohort' => $item->cohort_year,
                    'total' => $total,
                    'mfa_count' => $mfaCount,
                    'adoption_rate' => $total > 0 
                        ? round(((float) $mfaCount / (float) $total) * 100, 1)
                        : 0,
                ];
            });

        return [
            'overall' => [
                'enabled' => $mfaEnabled,
                'disabled' => $mfaDisabled,
                'adoption_rate' => $adoptionRate,
            ],
            'by_region' => $byRegion,
            'by_cohort' => $byCohort,
            'trend' => $this->getMfaAdoptionTrend(),
        ];
    }

    /**
     * Get geographic distribution
     *
     * @return array
     */
    public function getGeographicDistribution(): array
    {
        // Top 10 countries
        $byCountry = User::whereNotNull('country')
            ->select('country', DB::raw('COUNT(*) as count'))
            ->groupBy('country')
            ->orderByDesc('count')
            ->limit(10)
            ->get()
            ->map(function ($item) {
                return [
                    'country' => $item->country,
                    'count' => (int) $item->count,
                    'country_name' => $this->getCountryName($item->country),
                ];
            });

        // Top 10 US states
        $byState = User::where('country', 'US')
            ->whereNotNull('state')
            ->select('state', DB::raw('COUNT(*) as count'))
            ->groupBy('state')
            ->orderByDesc('count')
            ->limit(10)
            ->get()
            ->map(function ($item) {
                return [
                    'state' => $item->state,
                    'count' => (int) $item->count,
                    'state_name' => $this->getStateName($item->state),
                ];
            });

        // Top 10 cities
        $byCity = User::whereNotNull('city')
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

        // Data residency distribution
        $byResidency = User::whereNotNull('data_residency_region')
            ->select('data_residency_region', DB::raw('COUNT(*) as count'))
            ->groupBy('data_residency_region')
            ->get()
            ->map(function ($item) {
                return [
                    'region' => $item->data_residency_region,
                    'count' => (int) $item->count,
                ];
            });

        return [
            'by_country' => $byCountry,
            'by_state' => $byState,
            'by_city' => $byCity,
            'by_residency' => $byResidency,
        ];
    }

    /**
     * Get platform breakdown from user agents
     *
     * @return array
     */
    public function getPlatformBreakdown(): array
    {
        $userAgents = User::whereNotNull('last_login_user_agent')
            ->select('last_login_user_agent')
            ->get()
            ->pluck('last_login_user_agent');

        $platforms = [
            'Windows' => 0,
            'macOS' => 0,
            'Linux' => 0,
            'iOS' => 0,
            'Android' => 0,
            'Other' => 0,
        ];

        $browsers = [
            'Chrome' => 0,
            'Firefox' => 0,
            'Safari' => 0,
            'Edge' => 0,
            'Opera' => 0,
            'Other' => 0,
        ];

        $deviceTypes = [
            'desktop' => 0,
            'mobile' => 0,
            'tablet' => 0,
        ];

        foreach ($userAgents as $ua) {
            $ua = strtolower($ua);
            
            // Device type
            if (preg_match('/(mobile|android|iphone|ipod)/', $ua)) {
                $deviceTypes['mobile']++;
            } elseif (preg_match('/(tablet|ipad)/', $ua)) {
                $deviceTypes['tablet']++;
            } else {
                $deviceTypes['desktop']++;
            }
            
            // Platform
            if (strpos($ua, 'windows') !== false) {
                $platforms['Windows']++;
            } elseif (strpos($ua, 'mac os') !== false || strpos($ua, 'macos') !== false) {
                $platforms['macOS']++;
            } elseif (strpos($ua, 'linux') !== false) {
                $platforms['Linux']++;
            } elseif (strpos($ua, 'iphone') !== false || strpos($ua, 'ipad') !== false) {
                $platforms['iOS']++;
            } elseif (strpos($ua, 'android') !== false) {
                $platforms['Android']++;
            } else {
                $platforms['Other']++;
            }
            
            // Browser
            if (strpos($ua, 'chrome') !== false && strpos($ua, 'edg') === false) {
                $browsers['Chrome']++;
            } elseif (strpos($ua, 'firefox') !== false) {
                $browsers['Firefox']++;
            } elseif (strpos($ua, 'safari') !== false && strpos($ua, 'chrome') === false) {
                $browsers['Safari']++;
            } elseif (strpos($ua, 'edg') !== false) {
                $browsers['Edge']++;
            } elseif (strpos($ua, 'opera') !== false || strpos($ua, 'opr') !== false) {
                $browsers['Opera']++;
            } else {
                $browsers['Other']++;
            }
        }

        // Theme preference
        $themePreference = User::whereNotNull('theme_mode')
            ->select('theme_mode', DB::raw('COUNT(*) as count'))
            ->groupBy('theme_mode')
            ->get()
            ->map(function ($item) {
                $count = (int) $item->count;
                return [
                    'theme' => $item->theme_mode,
                    'count' => $count,
                    'percentage' => User::count() > 0 
                        ? round(((float) $count / (float) User::count()) * 100, 1)
                        : 0,
                ];
            });

        return [
            'device_types' => $deviceTypes,
            'platforms' => $platforms,
            'browsers' => $browsers,
            'theme_preference' => $themePreference,
        ];
    }

    /**
     * Get user retention cohorts
     *
     * @return array
     */
    public function getUserRetentionCohorts(): array
    {
        $cohorts = [];
        $months = 6;
        
        for ($i = 0; $i < $months; $i++) {
            $cohortMonth = now()->subMonths($i)->startOfMonth();
            $cohortKey = $cohortMonth->format('Y-m');
            
            // Users who joined in this cohort month
            $cohortUsers = User::whereBetween('created_at', [
                $cohortMonth->copy()->startOfMonth(),
                $cohortMonth->copy()->endOfMonth()
            ])->pluck('id');
            
            $cohortSize = (int) $cohortUsers->count();
            
            if ($cohortSize === 0) {
                continue;
            }
            
            $retention = [];
            
            // Calculate retention for each subsequent month
            for ($j = 0; $j <= $i; $j++) {
                $periodStart = $cohortMonth->copy()->addMonths($j)->startOfMonth();
                $periodEnd = $cohortMonth->copy()->addMonths($j)->endOfMonth();
                
                if ($periodStart->gt(now())) {
                    break;
                }
                
                $activeInPeriod = (int) User::whereIn('id', $cohortUsers)
                    ->whereBetween('last_login_at', [$periodStart, $periodEnd])
                    ->count();
                
                $retention[] = [
                    'month' => $j,
                    'period' => $periodStart->format('M Y'),
                    'active_users' => $activeInPeriod,
                    'retention_rate' => round(((float) $activeInPeriod / (float) $cohortSize) * 100, 1),
                ];
            }
            
            $cohorts[] = [
                'cohort' => $cohortKey,
                'cohort_label' => $cohortMonth->format('M Y'),
                'size' => $cohortSize,
                'retention' => $retention,
            ];
        }
        
        return $cohorts;
    }

    /**
     * Get security metrics - FIXED LINE 838
     *
     * @return array
     */
    public function getSecurityMetrics(): array
    {
        // Failed login attempts distribution
        $failedAttempts = User::select(
            DB::raw('CASE 
                WHEN failed_login_attempts = 0 THEN "0"
                WHEN failed_login_attempts BETWEEN 1 AND 3 THEN "1-3"
                WHEN failed_login_attempts BETWEEN 4 AND 10 THEN "4-10"
                ELSE "10+"
            END as attempt_range'),
            DB::raw('COUNT(*) as count')
        )
        ->groupBy('attempt_range')
        ->get();

        // Locked accounts
        $lockedAccounts = (int) User::whereNotNull('account_locked_until')
            ->where('account_locked_until', '>', now())
            ->count();

        // Password changes (last 30 days)
        $passwordChanges = (int) User::where('password_changed_at', '>=', now()->subDays(30))
            ->count();

        // Users requiring password change
        $requirePasswordChange = (int) User::where('requires_password_change', true)
            ->count();

        // FIX FOR LINE 838 - Cast to float before rounding
        $avgFailedAttempts = User::avg('failed_login_attempts');
        $avgFailedAttempts = is_numeric($avgFailedAttempts) ? (float) $avgFailedAttempts : 0.0;

        return [
            'failed_attempts_distribution' => $failedAttempts,
            'locked_accounts' => $lockedAccounts,
            'password_changes_30d' => $passwordChanges,
            'require_password_change' => $requirePasswordChange,
            'avg_failed_attempts' => round($avgFailedAttempts, 1),
        ];
    }

    /**
     * Get staff performance metrics - FIXED FOR LINE 838 ISSUES
     *
     * @return array
     */
    public function getStaffPerformanceMetrics(): array
    {
        // Verifications by staff
        $verificationsByStaff = User::whereNotNull('identity_verified_by_staff_id')
            ->whereNotNull('identity_verified_at')
            ->select(
                'identity_verified_by_staff_id',
                DB::raw('COUNT(*) as verification_count'),
                DB::raw('MIN(identity_verified_at) as first_verification'),
                DB::raw('MAX(identity_verified_at) as last_verification')
            )
            ->groupBy('identity_verified_by_staff_id')
            ->orderByDesc('verification_count')
            ->limit(10)
            ->get()
            ->map(function ($item) {
                // Get staff name from users table (assuming staff are users)
                $staff = User::find($item->identity_verified_by_staff_id);
                
                return [
                    'staff_id' => $item->identity_verified_by_staff_id,
                    'staff_name' => $staff ? "{$staff->first_name} {$staff->last_name}" : 'Unknown',
                    'verification_count' => (int) $item->verification_count,
                    'first_verification' => $item->first_verification,
                    'last_verification' => $item->last_verification,
                ];
            });

        // Average verification time by staff - FIX: Cast to float before rounding
        $avgTimeByStaff = User::whereNotNull('identity_verified_by_staff_id')
            ->whereNotNull('identity_verified_at')
            ->select(
                'identity_verified_by_staff_id',
                DB::raw('AVG(TIMESTAMPDIFF(HOUR, created_at, identity_verified_at)) as avg_hours')
            )
            ->groupBy('identity_verified_by_staff_id')
            ->get()
            ->mapWithKeys(function ($item) {
                $avgHours = $item->avg_hours;
                $avgHours = is_numeric($avgHours) ? (float) $avgHours : 0.0;
                return [$item->identity_verified_by_staff_id => round($avgHours, 1)];
            });

        // Overall average verification time
        $overallAvg = User::whereNotNull('identity_verified_at')
            ->select(DB::raw('AVG(TIMESTAMPDIFF(HOUR, created_at, identity_verified_at)) as avg_hours'))
            ->first()
            ->avg_hours ?? 0;
        $overallAvg = is_numeric($overallAvg) ? (float) $overallAvg : 0.0;

        return [
            'top_performers' => $verificationsByStaff,
            'avg_time_by_staff' => $avgTimeByStaff,
            'total_verifications' => (int) User::whereNotNull('identity_verified_at')->count(),
            'avg_verification_time_overall' => round($overallAvg, 1),
        ];
    }

    /**
     * Get MFA adoption trend
     *
     * @return array
     */
    private function getMfaAdoptionTrend(): array
    {
        $months = 6;
        $trend = [];
        
        for ($i = $months - 1; $i >= 0; $i--) {
            $month = now()->subMonths($i);
            $monthStart = $month->copy()->startOfMonth();
            $monthEnd = $month->copy()->endOfMonth();
            
            $usersByEndOfMonth = (int) User::where('created_at', '<=', $monthEnd)->count();
            $mfaByEndOfMonth = (int) User::where('mfa_enabled', true)
                ->where('created_at', '<=', $monthEnd)
                ->count();
            
            $trend[] = [
                'month' => $month->format('M Y'),
                'adoption_rate' => $usersByEndOfMonth > 0 
                    ? round(((float) $mfaByEndOfMonth / (float) $usersByEndOfMonth) * 100, 1)
                    : 0,
                'mfa_count' => $mfaByEndOfMonth,
                'total_users' => $usersByEndOfMonth,
            ];
        }
        
        return $trend;
    }

    /**
     * Get days from range string
     *
     * @param string $range
     * @return int
     */
    private function getDaysFromRange(string $range): int
    {
        return match($range) {
            '7_days' => 7,
            '14_days' => 14,
            '30_days' => 30,
            '90_days' => 90,
            default => 30,
        };
    }

    /**
     * Get weeks from range string
     *
     * @param string $range
     * @return int
     */
    private function getWeeksFromRange(string $range): int
    {
        return match($range) {
            '4_weeks' => 4,
            '8_weeks' => 8,
            '12_weeks' => 12,
            '24_weeks' => 24,
            default => 12,
        };
    }

    /**
     * Get date condition for range
     *
     * @param string $range
     * @return array
     */
    private function getDateCondition(string $range): array
    {
        $days = $this->getDaysFromRange($range);
        return ['>=', now()->subDays($days)];
    }

    /**
     * Format verification method
     *
     * @param string|null $method
     * @return string
     */
    private function formatVerificationMethod(?string $method): string
    {
        return match($method) {
            'document_upload' => 'Document Upload',
            'video_call' => 'Video Call',
            'in_person' => 'In Person',
            'api_verification' => 'API Verification',
            default => ucfirst(str_replace('_', ' ', $method ?? 'unknown')),
        };
    }

    /**
     * Get color for verification method
     *
     * @param string|null $method
     * @return string
     */
    private function getMethodColor(?string $method): string
    {
        return match($method) {
            'document_upload' => '#3B82F6', // blue
            'video_call' => '#10B981', // green
            'in_person' => '#F59E0B', // yellow
            'api_verification' => '#8B5CF6', // purple
            default => '#6B7280', // gray
        };
    }

    /**
     * Get color for age group
     *
     * @param string $group
     * @return string
     */
    private function getAgeGroupColor(string $group): string
    {
        return match($group) {
            '0-18' => '#3B82F6',
            '19-30' => '#10B981',
            '31-45' => '#F59E0B',
            '46-60' => '#8B5CF6',
            '60+' => '#EC4899',
            default => '#6B7280',
        };
    }

    /**
     * Get color for gender
     *
     * @param string|null $gender
     * @return string
     */
    private function getGenderColor(?string $gender): string
    {
        return match($gender) {
            'male' => '#3B82F6',
            'female' => '#EC4899',
            'other' => '#8B5CF6',
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
        ];
        
        return $countries[$code] ?? $code;
    }

    /**
     * Get state name from code
     *
     * @param string|null $code
     * @return string
     */
    private function getStateName(?string $code): string
    {
        $states = [
            'CA' => 'California',
            'TX' => 'Texas',
            'FL' => 'Florida',
            'NY' => 'New York',
            'IL' => 'Illinois',
            'PA' => 'Pennsylvania',
            'OH' => 'Ohio',
            'GA' => 'Georgia',
            'NC' => 'North Carolina',
            'MI' => 'Michigan',
        ];
        
        return $states[$code] ?? $code;
    }
}