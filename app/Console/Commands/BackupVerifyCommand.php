<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\BackupRecord;
use App\Services\Compliance\BackupReviewService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Daily backup verification (read-only): flags freshness gaps and missing
 * restore tests. Recording itself happens in the deploy flow right after
 * each mysqldump (see: backup:record).
 */
class BackupVerifyCommand extends Command
{
    protected $signature = 'backup:verify';

    protected $description = 'Verify backup freshness and restore-test currency. Read-only.';

    public function handle(BackupReviewService $review): int
    {
        $rows = BackupRecord::orderByDesc('taken_at')->limit(50)->get()
            ->map(fn ($r) => [
                'taken_at' => $r->taken_at,
                'bytes' => $r->bytes,
                'restore_tested_at' => $r->restore_tested_at,
                'restore_ok' => $r->restore_ok,
            ])->all();

        $gapHours = $review->hoursSinceLastUsable($rows);
        $restoreDays = $review->daysSinceRestoreTest($rows);

        $this->line('hours since last usable backup: ' . ($gapHours === null ? 'NONE' : round($gapHours, 1)));
        $this->line('days since restore test: ' . ($restoreDays === null ? 'NEVER' : round($restoreDays, 1)));

        Log::info('Backup verification completed', [
            'gap_hours' => $gapHours,
            'restore_days' => $restoreDays,
        ]);

        if ($review->isGap($gapHours) || $review->isRestoreTestOverdue($restoreDays)) {
            $this->warn('Backup posture needs attention - see rows above.');

            return self::FAILURE;
        }

        $this->info('Backup posture healthy.');

        return self::SUCCESS;
    }
}
