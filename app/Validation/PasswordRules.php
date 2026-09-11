<?php

declare(strict_types=1);

namespace App\Validation;

use Illuminate\Validation\Rules\Password;

/**
 * Single source for credential creation strength (Guidelines: complexity,
 * frequent change, immediate revocation on termination).
 *
 * 12+ chars with mixed case, letters, numbers and symbols. Deliberately
 * NOT using uncompromised(): it needs haveibeenpwned.org on every
 * validation, which couples registration to third-party availability.
 */
class PasswordRules
{
    /**
     * @return list<string|Password>
     */
    public static function create(): array
    {
        return [
            'required',
            'string',
            Password::min(12)->letters()->mixedCase()->numbers()->symbols(),
            'confirmed',
        ];
    }

    /**
     * Optional password change: same strength, without `required`.
     *
     * @return list<string|Password>
     */
    public static function update(): array
    {
        return [
            'sometimes',
            'nullable',
            'string',
            Password::min(12)->letters()->mixedCase()->numbers()->symbols(),
            'confirmed',
        ];
    }
}
