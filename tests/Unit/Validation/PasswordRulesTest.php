<?php

declare(strict_types=1);

namespace Tests\Unit\Validation;

use App\Validation\PasswordRules;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * Backend half of the password contract. The frontend mirror is
 * shared/utils/passwordRules.test.ts with IDENTICAL vectors - if either
 * side changes, change both, or users submit passwords the other rejects.
 */
class PasswordRulesTest extends TestCase
{
    /**
     * @return list<string>
     */
    public static function compliantPasswords(): array
    {
        return ['Str0ng!Passw0rd', 'Abcdef123!@#', 'Xy9!Xy9!Xy9!', 'aB3$56789012'];
    }

    /**
     * @return list<string>
     */
    public static function weakPasswords(): array
    {
        return [
            '',
            'short1!A',
            'alllowercase123!',
            'ALLUPPERCASE123!',
            'NoDigitsHere!!',
            'NoSymbols123Aa',
            'Ab1!Ab1!Ab1',
        ];
    }

    private function passes(string $password): bool
    {
        return Validator::make(
            ['password' => $password, 'password_confirmation' => $password],
            ['password' => PasswordRules::create()]
        )->passes();
    }

    /** @test */
    public function it_accepts_compliant_passwords()
    {
        foreach (self::compliantPasswords() as $password) {
            $this->assertTrue($this->passes($password), "Should accept: {$password}");
        }
    }

    /** @test */
    public function it_rejects_weak_passwords()
    {
        foreach (self::weakPasswords() as $password) {
            $this->assertFalse($this->passes($password), "Should reject: '{$password}'");
        }
    }

    /** @test */
    public function update_variant_is_optional_but_equally_strong()
    {
        $rules = PasswordRules::update();

        $this->assertContains('sometimes', $rules);
        $this->assertNotContains('required', $rules);

        $accept = Validator::make(
            ['password' => 'Str0ng!Passw0rd', 'password_confirmation' => 'Str0ng!Passw0rd'],
            ['password' => $rules]
        )->passes();
        $this->assertTrue($accept);

        $emptyOk = Validator::make([], ['password' => $rules])->passes();
        $this->assertTrue($emptyOk);

        $weakFails = Validator::make(
            ['password' => 'weakpass1', 'password_confirmation' => 'weakpass1'],
            ['password' => $rules]
        )->passes();
        $this->assertFalse($weakFails);
    }
}
