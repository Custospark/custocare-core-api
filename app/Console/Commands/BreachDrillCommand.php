<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Compliance\BreachService;
use Illuminate\Console\Command;

class BreachDrillCommand extends Command
{
    protected $signature = 'breach:drill {--severity=low : low|high}';

    protected $description = 'Record a breach drill incident (marked drill, excluded from real counts).';

    public function handle(BreachService $breachService): int
    {
        $incident = $breachService->report([
            'description' => 'Scheduled breach response drill.',
            'severity' => $this->option('severity') === 'high' ? 'high' : 'low',
            'is_drill' => true,
        ]);

        $this->info("Drill incident #{$incident->id} recorded. Run the notify steps against it, then close it.");

        return self::SUCCESS;
    }
}
