<?php

declare(strict_types=1);

namespace App\Services\Billing\Gateways;

use App\Services\Billing\Gateways\Contracts\GatewayDriverInterface;
use App\Services\Billing\Gateways\Drivers\AirtelMoneyDriver;
use App\Services\Billing\Gateways\Drivers\FlutterwaveDriver;
use App\Services\Billing\Gateways\Drivers\MtnMomoDriver;
use App\Services\Billing\Gateways\Drivers\PesaPalDriver;
use App\Services\Billing\Gateways\Exceptions\GatewayException;

/**
 * GatewayManager
 *
 * Singleton that registers, resolves, and caches gateway driver instances.
 * New gateways can be plugged in via extend() without touching existing code.
 *
 * Usage:
 *   app(GatewayManager::class)->driver('flutterwave')->initiate([...]);
 *   app(GatewayManager::class)->available(); // ['flutterwave', 'mtn_momo']
 */
class GatewayManager
{
    /** @var array<string, class-string<GatewayDriverInterface>> */
    private array $registry = [
        'mtn_momo'     => MtnMomoDriver::class,
        'airtel_money' => AirtelMoneyDriver::class,
        'flutterwave'  => FlutterwaveDriver::class,
        'pesapal'      => PesaPalDriver::class,
    ];

    /** @var array<string, GatewayDriverInterface> Resolved / cached instances */
    private array $resolved = [];

    /**
     * Resolve a gateway driver by name.
     *
     * @throws GatewayException  When the gateway is not registered.
     */
    public function driver(string $name): GatewayDriverInterface
    {
        $name = strtolower($name);

        if (! isset($this->registry[$name])) {
            throw new GatewayException(
                "Payment gateway '{$name}' is not registered. Available: " .
                implode(', ', array_keys($this->registry)),
                $name
            );
        }

        // Lazy-resolve and cache
        if (! isset($this->resolved[$name])) {
            $this->resolved[$name] = app($this->registry[$name]);
        }

        return $this->resolved[$name];
    }

    /**
     * List of gateway names that are currently enabled and fully configured.
     *
     * @return string[]
     */
    public function available(): array
    {
        return collect(array_keys($this->registry))
            ->filter(fn(string $name) => $this->driver($name)->isEnabled())
            ->values()
            ->toArray();
    }

    /**
     * Register a new driver or override an existing one.
     * Use this in your AppServiceProvider to add custom gateways.
     *
     * @param  class-string<GatewayDriverInterface> $driverClass
     */
    public function extend(string $name, string $driverClass): void
    {
        $this->registry[strtolower($name)] = $driverClass;
        unset($this->resolved[strtolower($name)]); // clear cached instance
    }

    /** All registered gateway names (enabled and disabled). */
    public function registered(): array
    {
        return array_keys($this->registry);
    }
}
