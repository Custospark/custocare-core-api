<?php

declare(strict_types=1);

namespace App\Services\Compliance;

use App\Services\Contracts\AuditLogServiceInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Retention schedule enforcement (Guidelines Sec 5.6, Act Sec 18).
 *
 * Nothing auto-deletes: review() lists what is DUE, a human authorizes
 * destruction per archives law, certifyProcessorDeletion() records vendor
 * delete-certifications in the audit log. Deletion itself stays a
 * deliberate, witnessed act - never a cron side effect.
 */
class RetentionService
{
    /**
     * Tables the review sweep understands: category => [table, anchor column].
     * Anchors mirror config/retention.php; last_encounter_at falls back to
     * updated_at where a dedicated column does not exist yet.
     */
    protected const SOURCES = [
        'diagnostics' => ['table' => 'lab_requests', 'anchor' => 'created_at'],
        'treatment_plans' => ['table' => 'treatment_plans', 'anchor' => 'created_at'],
        'billing' => ['table' => 'payments', 'anchor' => 'created_at'],
        'consents' => ['table' => 'patient_consents', 'anchor' => 'granted_at'],
        'audit_logs' => ['table' => 'audit_logs', 'anchor' => 'created_at'],
        'messages' => ['table' => 'messages', 'anchor' => 'created_at'],
    ];

    public function __construct(
        protected AuditLogServiceInterface $auditLog
    ) {}

    public function schedule(): array
    {
        return config('retention.categories', []);
    }

    public function dueDate(string $category, Carbon|string $anchorDate): ?Carbon
    {
        $rule = $this->schedule()[$category] ?? null;
        if (! $rule) {
            return null;
        }
        if (($rule['method'] ?? '') === 'rolling_30_day') {
            return Carbon::parse($anchorDate)->addDays((int) ($rule['retention_days'] ?? 30));
        }

        return Carbon::parse($anchorDate)->addYears((int) ($rule['years'] ?? 5));
    }

    public function isDue(string $category, Carbon|string $anchorDate): bool
    {
        $due = $this->dueDate($category, $anchorDate);

        return $due !== null && $due->isPast();
    }

    /**
     * Count rows past retention per category. Missing tables are skipped
     * (reported) rather than failing the whole review.
     *
     * @return array<string, array{count: int|null, note: string}>
     */
    public function review(): array
    {
        $report = [];

        foreach (self::SOURCES as $category => $source) {
            $rule = $this->schedule()[$category] ?? null;
            if (! $rule) {
                $report[$category] = ['count' => null, 'note' => 'no schedule rule'];
                continue;
            }

            try {
                $cutoff = $this->dueDate($category, now());
                $count = DB::table($source['table'])
                    ->where($source['anchor'], '<', $cutoff)
                    ->count();
                $report[$category] = ['count' => $count, 'note' => 'due for authorized disposal review'];
            } catch (\Throwable $e) {
                $report[$category] = ['count' => null, 'note' => 'table unavailable: ' . $e->getMessage()];
            }
        }

        return $report;
    }

    /**
     * Record a processor delete-certification (10 business days post-cessation).
     * Returns the audit entry reference for the defense pack.
     */
    public function certifyProcessorDeletion(string $vendor, string $scope, string $certifiedAt = ''): array
    {
        return $this->auditLog->createAuditLog([
            'entity_type' => 'processor_deletion_certificate',
            'entity_id' => null,
            'action' => 'processor_deletion_certified',
            'description' => "Vendor {$vendor} certified deletion of {$scope}" . ($certifiedAt ? " on {$certifiedAt}" : ''),
            'metadata' => ['vendor' => $vendor, 'scope' => $scope, 'certified_at' => $certifiedAt],
        ]);
    }
}
