<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\BackupRecord;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class BackupRecordCommand extends Command
{
    protected $signature = 'backup:record {--path= : Dump file path} {--bytes=0 : Dump size in bytes} {--kind=manual : manual|deploy|scheduled} {--env=production : staging|production}';

    protected $description = 'Record a database dump in the backup evidence log. Called by deploy flows right after mysqldump.';

    public function handle(): int
    {
        $path = (string) $this->option('path');
        if ($path === '') {
            $this->error('--path is required.');

            return self::FAILURE;
        }

        $record = BackupRecord::create([
            'backup_uuid' => (string) Str::uuid(),
            'environment' => (string) $this->option('env'),
            'path' => $path,
            'bytes' => (int) $this->option('bytes'),
            'kind' => (string) $this->option('kind'),
            'taken_at' => now(),
        ]);

        $this->info("Backup recorded #{$record->id} ({$record->bytes} bytes).");

        return self::SUCCESS;
    }
}
