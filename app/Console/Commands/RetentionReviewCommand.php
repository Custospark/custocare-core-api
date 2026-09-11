<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Compliance\RetentionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RetentionReviewCommand extends Command
{
    protected $signature = 'retention:review';

    protected $description = 'Weekly retention sweep: list what is due for authorized disposal review. Never deletes.';

    public function handle(RetentionService $retention): int
    {
        $report = $retention->review();
        $dueTotal = 0;

        foreach ($report as $category => $row) {
            $count = $row['count'];
            $line = $count === null
                ? "{$category}: unavailable ({$row['note']})"
                : "{$category}: {$count} due ({$row['note']})";
            $this->line($line);
            if (is_int($count)) {
                $dueTotal += $count;
            }
        }

        Log::info('Retention review completed', ['due_total' => $dueTotal, 'report' => $report]);

        if ($dueTotal > 0) {
            $this->warn("{$dueTotal} record(s) past retention - authorize disposal per archives law before any deletion.");
        } else {
            $this->info('Nothing past retention.');
        }

        return self::SUCCESS;
    }
}
