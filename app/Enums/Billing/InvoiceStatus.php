<?php

declare(strict_types=1);

namespace App\Enums\Billing;

enum InvoiceStatus: string
{
    case PAID = 'paid';
    case UNPAID = 'unpaid';
    case OVERDUE = 'overdue';
    case PARTIALLY_PAID = 'partially_paid';
    case CANCELLED = 'cancelled';
    case REFUNDED = 'refunded';

    public function label(): string
    {
        return match($this) {
            self::PAID           => 'Paid',
            self::UNPAID         => 'Unpaid',
            self::OVERDUE        => 'Overdue',
            self::PARTIALLY_PAID => 'Partially Paid',
            self::CANCELLED      => 'Cancelled',
            self::REFUNDED       => 'Refunded',
        };
    }
}
