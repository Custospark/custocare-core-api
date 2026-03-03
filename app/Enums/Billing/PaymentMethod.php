<?php

declare(strict_types=1);

namespace App\Enums\Billing;

/**
 * Supported payment methods.
 * Gateway methods are stubbed for future integration (MTN MoMo, Airtel, Flutterwave, etc.)
 */
enum PaymentMethod: string
{
    // ── Manual methods (active) ──────────────────────────────────────────
    case MOBILE_MONEY   = 'mobile_money';    // MTN / Airtel UG manual reference
    case BANK_TRANSFER  = 'bank_transfer';
    case CASH           = 'cash';

    // ── Gateway methods (prepared, not yet active) ───────────────────────
    case GATEWAY        = 'gateway';         // Generic gateway placeholder

    public function label(): string
    {
        return match($this) {
            self::MOBILE_MONEY  => 'Mobile Money',
            self::BANK_TRANSFER => 'Bank Transfer',
            self::CASH          => 'Cash',
            self::GATEWAY       => 'Payment Gateway',
        };
    }

    /** Whether this method is currently enabled for use. */
    public function isEnabled(): bool
    {
        return match($this) {
            self::MOBILE_MONEY, self::BANK_TRANSFER, self::CASH => true,
            self::GATEWAY => false,   // flip to true when gateway is integrated
        };
    }
}
