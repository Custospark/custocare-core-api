<?php

declare(strict_types=1);

namespace Tests\Unit\Clinical;

use App\Services\Clinical\ClinicalRangeCheck;
use Tests\TestCase;

class ClinicalRangeCheckTest extends TestCase
{
    private ClinicalRangeCheck $check;

    protected function setUp(): void
    {
        parent::setUp();
        $this->check = new ClinicalRangeCheck();
    }

    /** @test */
    public function sane_vitals_pass_clean()
    {
        $result = $this->check->validate([
            'temperature' => 37.0, 'temperature_unit' => 'celsius',
            'systolic_bp' => 120, 'diastolic_bp' => 80,
            'oxygen_saturation' => 98, 'heart_rate' => 72,
            'respiratory_rate' => 16, 'height' => 170, 'weight' => 65,
        ]);

        $this->assertTrue($result['valid']);
        $this->assertSame([], $result['errors']);
        $this->assertSame([], $result['warnings']);
    }

    /** @test */
    public function inverted_blood_pressure_is_rejected()
    {
        $result = $this->check->validate(['systolic_bp' => 80, 'diastolic_bp' => 90]);

        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);
    }

    /** @test */
    public function unrecordable_spo2_is_rejected_while_low_spo2_warns()
    {
        $dead = $this->check->validate(['oxygen_saturation' => 30]);
        $this->assertFalse($dead['valid']);

        $low = $this->check->validate(['oxygen_saturation' => 85]);
        $this->assertTrue($low['valid']);
        $this->assertNotEmpty($low['warnings']);
    }

    /** @test */
    public function fahrenheit_values_are_judged_in_celsius()
    {
        $ok = $this->check->validate(['temperature' => 98.6, 'temperature_unit' => 'fahrenheit']);
        $this->assertTrue($ok['valid']);

        // 98.6 entered as celsius means certain death, not a mild fever.
        $wrongUnit = $this->check->validate(['temperature' => 98.6, 'temperature_unit' => 'celsius']);
        $this->assertFalse($wrongUnit['valid']);
    }

    /** @test */
    public function impossible_bmi_is_rejected()
    {
        $result = $this->check->validate(['height' => 170, 'weight' => 400]);

        $this->assertFalse($result['valid']);
    }

    /** @test */
    public function absurd_heart_and_resp_rates_are_rejected()
    {
        $this->assertFalse($this->check->validate(['heart_rate' => 400])['valid']);
        $this->assertFalse($this->check->validate(['respiratory_rate' => 120])['valid']);
    }

    /** @test */
    public function blanks_and_garbage_never_crash()
    {
        $result = $this->check->validate([
            'temperature' => '', 'systolic_bp' => null,
            'heart_rate' => 'not-a-number', 'weight' => 'abc',
        ]);

        $this->assertTrue($result['valid']);
        $this->assertSame([], $result['errors']);
    }
}
