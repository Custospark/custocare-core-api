<?php

declare(strict_types=1);

namespace Tests\Unit\Compliance;

use App\Models\Patient;
use App\Repositories\Contracts\PatientConsentRepositoryInterface;
use App\Services\PatientConsent\PatientConsentService;
use Illuminate\Support\Carbon;
use Mockery;
use Tests\TestCase;

class GuardianConsentTest extends TestCase
{
    private function service(): PatientConsentService
    {
        return new PatientConsentService(Mockery::mock(PatientConsentRepositoryInterface::class));
    }

    private function patient(string $dob): Patient
    {
        $patient = new Patient();
        $patient->forceFill(['date_of_birth' => $dob]);

        return $patient;
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function adults_need_no_guardian()
    {
        $adult = $this->patient(Carbon::now()->subYears(30)->toDateString());

        $this->assertNull($this->service()->guardianRequirements($adult, []));
    }

    /** @test */
    public function minors_require_a_guardian()
    {
        $minor = $this->patient(Carbon::now()->subYears(10)->toDateString());

        $error = $this->service()->guardianRequirements($minor, []);

        $this->assertNotNull($error);
        $this->assertStringContainsString('guardian', strtolower($error));
    }

    /** @test */
    public function minor_consent_requires_a_staff_witness()
    {
        $minor = $this->patient(Carbon::now()->subYears(10)->toDateString());

        $unwitnessed = $this->service()->guardianRequirements($minor, ['legal_guardian_id' => 9]);
        $this->assertNotNull($unwitnessed);
        $this->assertStringContainsString('witness', strtolower($unwitnessed));

        $witnessed = $this->service()->guardianRequirements(
            $minor,
            ['legal_guardian_id' => 9, 'witnessed_by_staff_id' => 3]
        );
        $this->assertNull($witnessed);
    }

    /** @test */
    public function unknown_patients_fail_open_to_other_validations()
    {
        // No DOB on file: guardianship cannot be assessed here, so this
        // check stays silent and remaining validations still run.
        $this->assertNull($this->service()->guardianRequirements(null, []));
    }
}
