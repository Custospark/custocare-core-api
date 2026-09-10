<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Billing\Gateways\Drivers\PesaPalDriver;
use App\Services\Billing\Gateways\GatewayManager;
use Illuminate\Console\Command;

class PesapalStatusCommand extends Command
{
    protected $signature = 'pesapal:status';

    protected $description = 'Check PesaPal wiring: config, API connectivity, registration in GatewayManager.';

    public function handle(GatewayManager $manager): int
    {
        $cfg = config('billing_gateways.pesapal');

        $this->info('environment: ' . ($cfg['environment'] ?? 'unset'));
        $this->info('enabled flag: ' . var_export((bool) ($cfg['enabled'] ?? false), true));
        $this->info('callback_url: ' . ($cfg['callback_url'] ?? 'unset'));
        $this->info('ipn_id: ' . ($cfg['ipn_id'] ? substr((string) $cfg['ipn_id'], 0, 4) . '****' : 'unset'));

        $driver = new PesaPalDriver();
        $check = $driver->checkConnection();
        $this->info('api connectivity: ' . ($check['ok'] ? 'OK' : 'FAILED - ' . $check['message']));
        $this->info('driver enabled: ' . var_export($driver->isEnabled(), true));
        $this->info('available via manager: ' . var_export(in_array('pesapal', $manager->available(), true), true));

        return $check['ok'] ? self::SUCCESS : self::FAILURE;
    }
}
