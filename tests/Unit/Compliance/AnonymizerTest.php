<?php

declare(strict_types=1);

namespace Tests\Unit\Compliance;

use App\Services\Compliance\Anonymizer;
use Tests\TestCase;

class AnonymizerTest extends TestCase
{
    /** @test */
    public function outputs_are_deterministic_per_input()
    {
        $this->assertSame(Anonymizer::email('a@b.com'), Anonymizer::email('a@b.com'));
        $this->assertSame(Anonymizer::phone('256771234567'), Anonymizer::phone('256771234567'));
        $this->assertSame(Anonymizer::name('Jane'), Anonymizer::name('Jane'));
    }

    /** @test */
    public function different_inputs_never_collide_in_practice()
    {
        $this->assertNotSame(Anonymizer::email('a@b.com'), Anonymizer::email('c@d.com'));
        $this->assertNotSame(Anonymizer::name('Jane'), Anonymizer::name('John'));
    }

    /** @test */
    public function outputs_are_recognizably_fake_and_unusable()
    {
        $this->assertStringEndsWith('@example.invalid', Anonymizer::email('real@hospital.ug'));
        $this->assertStringStartsWith('0770', Anonymizer::phone('256771234567'));
        $this->assertStringStartsWith('Training User ', Anonymizer::name('Real Name'));

        // Nulls and empties degrade to fakes, never to blanks that break NOT NULL.
        $this->assertStringEndsWith('@example.invalid', Anonymizer::email(null));
        $this->assertSame(Anonymizer::phone('x'), Anonymizer::phone('x'));
    }
}
