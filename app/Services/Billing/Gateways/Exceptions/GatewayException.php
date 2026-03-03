<?php

declare(strict_types=1);

namespace App\Services\Billing\Gateways\Exceptions;

use RuntimeException;

/**
 * Thrown when a payment gateway operation fails.
 * Carries the gateway name and original HTTP response for debugging.
 */
class GatewayException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly string $gatewayName = 'unknown',
        private readonly array  $rawResponse  = [],
        int $code = 422,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function getGatewayName(): string { return $this->gatewayName; }
    public function getRawResponse(): array  { return $this->rawResponse; }
}
