<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Message\MessageBodyCipher;
use Tests\TestCase;

class MessageBodyCipherTest extends TestCase
{
    public function test_encrypt_decrypt_round_trip(): void
    {
        $plain = 'Hello Custocare — message body with emoji 🔒';

        $encrypted = MessageBodyCipher::encrypt($plain);

        $this->assertNotNull($encrypted);
        $this->assertNotSame($plain, $encrypted);

        $this->assertSame($plain, MessageBodyCipher::decrypt($encrypted));
    }

    public function test_decrypt_falls_back_to_legacy_plaintext(): void
    {
        $legacy = 'Legacy row before migration';

        $this->assertSame($legacy, MessageBodyCipher::decrypt(null, $legacy));
    }

    public function test_empty_body_encrypts_to_null(): void
    {
        $this->assertNull(MessageBodyCipher::encrypt(''));
        $this->assertNull(MessageBodyCipher::encrypt('   '));
        $this->assertNull(MessageBodyCipher::encrypt(null));
    }
}
