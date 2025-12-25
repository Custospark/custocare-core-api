<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class BumpVersion extends Command
{
    protected $signature = 'app:version {type : major|minor|patch}';
    protected $description = 'Bump application version';

    public function handle(): int
    {
        $envPath = base_path('.env');

        if (!File::exists($envPath)) {
            $this->error('.env file not found');
            return Command::FAILURE;
        }

        $env = File::get($envPath);

        preg_match('/APP_VERSION=(.*)/', $env, $matches);

        $current = $matches[1] ?? '0.0.0';
        [$major, $minor, $patch] = array_map('intval', explode('.', $current));

        match ($this->argument('type')) {
            'major' => [$major++, $minor = 0, $patch = 0],
            'minor' => [$minor++, $patch = 0],
            'patch' => $patch++,
            default => $this->error('Invalid version type'),
        };

        $newVersion = "$major.$minor.$patch";

        $env = preg_replace(
            '/APP_VERSION=.*/',
            "APP_VERSION=$newVersion",
            $env
        );

        File::put($envPath, $env);

        $this->info("Version bumped: $current → $newVersion");

        return Command::SUCCESS;
    }
}
