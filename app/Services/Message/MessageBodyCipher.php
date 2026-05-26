<?php

declare(strict_types=1);

namespace App\Services\Message;

/**
 * Encrypts message body at rest using Laravel's APP_KEY (AES-256-CBC).
 * Subject remains plaintext for inbox search and alphabetical sort.
 */
final class MessageBodyCipher
{
    public static function encrypt(?string $plain): ?string
    {
        if ($plain === null) {
            return null;
        }

        $trimmed = trim($plain);

        return $trimmed === '' ? null : encrypt($plain);
    }

    /**
     * @param  string|null  $encrypted  Ciphertext from body_encrypted
     * @param  string|null  $legacyPlain  Legacy plaintext body column (pre-migration rows)
     */
    public static function decrypt(?string $encrypted, ?string $legacyPlain = null): ?string
    {
        if ($encrypted !== null && $encrypted !== '') {
            try {
                return decrypt($encrypted);
            } catch (\Throwable) {
                // Fall through to legacy plaintext when ciphertext is corrupt or key rotated without re-encrypt.
            }
        }

        if ($legacyPlain === null || $legacyPlain === '') {
            return null;
        }

        return $legacyPlain;
    }
}
