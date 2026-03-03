<?php

declare(strict_types=1);

namespace App\Services\Billing\Gateways\Exceptions;

use RuntimeException;

/**
 * Thrown when a gateway webhook fails signature verification.
 * Always results in a 401 response to the gateway.
 */
class WebhookVerificationException extends RuntimeException
{
    public function __construct(string $message = 'Webhook signature verification failed.', int $code = 401)
    {
        parent::__construct($message, $code);
    }
}
