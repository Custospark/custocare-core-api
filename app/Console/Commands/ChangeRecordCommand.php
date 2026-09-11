<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ChangeRequest;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ChangeRecordCommand extends Command
{
    protected $signature = 'change:record {--title= : Change title} {--env= : staging|production} {--commits= : Commit range} {--requested-by= : Requester} {--approved-by= : Approver} {--rollback= : Rollback reference} {--tests= : Test evidence summary}';

    protected $description = 'Record a signed change request (plan approval gate evidence).';

    public function handle(): int
    {
        foreach (['title', 'env', 'commits', 'requested-by'] as $required) {
            if (empty($this->option($required))) {
                $this->error("--{$required} is required.");

                return self::FAILURE;
            }
        }

        if (! in_array($this->option('env'), ['staging', 'production'], true)) {
            $this->error('--env must be staging or production.');

            return self::FAILURE;
        }

        $record = ChangeRequest::create([
            'request_uuid' => (string) Str::uuid(),
            'title' => (string) $this->option('title'),
            'environment' => (string) $this->option('env'),
            'commit_range' => (string) $this->option('commits'),
            'requested_by' => (string) $this->option('requested-by'),
            'approved_by' => $this->option('approved-by') ?: null,
            'approved_at' => $this->option('approved-by') ? now() : null,
            'rollback_ref' => $this->option('rollback') ?: null,
            'test_evidence' => $this->option('tests') ?: null,
        ]);

        $this->info("Change request #{$record->id} recorded.");

        return self::SUCCESS;
    }
}
