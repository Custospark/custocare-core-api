<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AuditLog;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Monthly audit review rota (Guidelines: regularly reviewed audit trails,
 * mechanisms to track/address unauthorized or suspicious activity).
 *
 * Read-only. Reports operation mix, authorization failures, PHI exports/
 * prints/shares, after-hours access and legal-hold coverage for the window.
 */
class AuditReviewCommand extends Command
{
    protected $signature = 'audit:review {--days=30 : Lookback window in days}';

    protected $description = 'Review audit-trail activity for suspicious patterns. Read-only.';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $since = Carbon::now()->subDays($days);

        $base = AuditLog::where('created_at', '>=', $since);

        $byOperation = (clone $base)->selectRaw('operation, COUNT(*) c')
            ->groupBy('operation')->pluck('c', 'operation')->all();
        $authFailures = (clone $base)->where('operation', 'authorization_failure')->count();
        $exports = (clone $base)->whereIn('operation', ['export', 'print', 'share'])->count();
        $phiAccess = (clone $base)->where('phi_accessed', true)->count();
        $afterHours = null;
        if (\Illuminate\Support\Facades\DB::getDriverName() === 'mysql') {
            $afterHours = (clone $base)
                ->whereRaw('HOUR(created_at) < 6 OR HOUR(created_at) >= 22')
                ->count();
        }
        $legalHold = AuditLog::where('legal_hold_flag', true)->count();

        $this->info("audit review: last {$days} days");
        foreach ($byOperation as $operation => $count) {
            $this->line("  {$operation}: {$count}");
        }
        $this->line("  authorization_failures: {$authFailures}");
        $this->line("  exports_prints_shares: {$exports}");
        $this->line("  phi_access_events: {$phiAccess}");
        $this->line('  after_hours_access: ' . ($afterHours ?? 'n/a (non-mysql)'));
        $this->line("  legal_hold_rows: {$legalHold}");

        Log::info('Audit review completed', [
            'days' => $days,
            'by_operation' => $byOperation,
            'authorization_failures' => $authFailures,
            'exports' => $exports,
            'after_hours' => $afterHours,
        ]);

        return self::SUCCESS;
    }
}
