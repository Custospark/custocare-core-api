<?php

declare(strict_types=1);

namespace App\Constants\Billing;

final class PlanFeatures
{
    /**
     * Canonical feature keys used by plan creation/editing.
     * These keys intentionally mirror current frontend capabilities/modules.
     */
    public const ALL = [
        'patient_dashboard',
        'medical_records',
        'nursing',
        'clinical',
        'laboratory',
        'pharmacy',
        'billing',
        'administration',
        'platform_administration',
        'account',
        'messaging_center',
        'api_access',
        'analytics_dashboards',
        'audit_logs',
    ];
}

