<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Billing\Gateways\Drivers\PesaPalDriver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class PesapalRegisterIpnCommand extends Command
{
    protected $signature = 'pesapal:register-ipn';

    protected $description = 'Register the PesaPal IPN URL and persist the ipn_id into .env (with backup).';

    public function handle(): int
    {
        $driver = new PesaPalDriver();

        try {
            $ipnId = $driver->registerIpnId();
        } catch (\Throwable $e) {
            $this->error('Registration failed: ' . $e->getMessage());

            return self::FAILURE;
        }

        $envPath = base_path('.env');
        File::copy($envPath, $envPath . '.bak-' . date('Ymd-His'));

        $lines = file($envPath, FILE_IGNORE_NEW_LINES) ?? [];
        $found = false;
        foreach ($lines as $i => $line) {
            if (str_starts_with(trim($line), 'PESAPAL_IPN_ID=')) {
                $lines[$i] = 'PESAPAL_IPN_ID=' . $ipnId;
                $found = true;
                break;
            }
        }
        if (! $found) {
            $lines[] = 'PESAPAL_IPN_ID=' . $ipnId;
        }
        file_put_contents($envPath, implode("\n", $lines) . "\n");

        $this->info('IPN registered and persisted (.env backed up first).');

        return self::SUCCESS;
    }
}
