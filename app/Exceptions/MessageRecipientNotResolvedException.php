<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a compose recipient email or phone does not match any User
 * ({@see User::email_hash} / {@see User::phone_hash}).
 */
final class MessageRecipientNotResolvedException extends RuntimeException
{
    public function __construct(
        public readonly string $channel,
        public readonly string $normalizedValue,
        ?string $message = null,
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message ?? self::defaultMessage($channel), $code, $previous);
    }

    private static function defaultMessage(string $channel): string
    {
        return match ($channel) {
            'email' => 'This email is not registered on Custocare.',
            'phone' => 'This phone number is not registered on Custocare.',
            default => 'This contact is not registered on Custocare.',
        };
    }
}
