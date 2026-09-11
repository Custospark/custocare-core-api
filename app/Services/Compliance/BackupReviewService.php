<?php

declare(strict_types=1);

namespace App\Services\Compliance;

use Illuminate\Support\Carbon;

/**
 * Backup freshness rules (testable, no DB).
 *
 * Policy: a usable backup every 24h; restore test monthly. The review job
 * feeds real rows; these pure helpers decide what counts as a gap.
 */
class BackupReviewService
{
    public const MAX_GAP_HOURS = 26;

    public const RESTORE_TEST_DAYS = 30;

    /**
     * Hours since the last usable backup, null when none exists.
     *
     * @param list<array{taken_at: mixed, bytes: int}> $records newest-first
     */
    public function hoursSinceLastUsable(array $records): ?float
    {
        foreach ($records as $record) {
            if ((int) ($record['bytes'] ?? 0) <= 0) {
                continue;
            }
            $takenAt = $record['taken_at'] ?? null;
            if ($takenAt === null) {
                continue;
            }

            return Carbon::parse($takenAt)->diffInHours(now(), true);
        }

        return null;
    }

    public function isGap(?float $hoursSinceLastUsable): bool
    {
        return $hoursSinceLastUsable === null || $hoursSinceLastUsable > self::MAX_GAP_HOURS;
    }

    /**
     * Days since the last successful restore test, null when never tested.
     *
     * @param list<array{restore_tested_at: mixed, restore_ok: mixed}> $records newest-first
     */
    public function daysSinceRestoreTest(array $records): ?float
    {
        foreach ($records as $record) {
            if (empty($record['restore_ok']) || empty($record['restore_tested_at'])) {
                continue;
            }

            return Carbon::parse($record['restore_tested_at'])->diffInDays(now(), true);
        }

        return null;
    }

    public function isRestoreTestOverdue(?float $days): bool
    {
        return $days === null || $days > self::RESTORE_TEST_DAYS;
    }
}
