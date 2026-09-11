<?php

declare(strict_types=1);

namespace Tests\Unit\Interop;

use App\Services\Interop\Dhis2Adapter;
use App\Services\Interop\IcdCode;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class Dhis2AdapterTest extends TestCase
{
    /** @test */
    public function period_rejects_non_yyyymm()
    {
        $this->assertSame('202601', Dhis2Adapter::period('202601'));

        foreach (['2026-01', '202613', '202600', 'Jan 2026', ''] as $bad) {
            try {
                Dhis2Adapter::period($bad);
                $this->fail("Should reject period '{$bad}'.");
            } catch (\InvalidArgumentException) {
                $this->assertTrue(true);
            }
        }
    }

    /** @test */
    public function payload_matches_dhis2_datavalueset_shape()
    {
        config()->set('dhis2.org_unit', 'OU-1');

        $payload = (new Dhis2Adapter())->buildPayload('DS-1', '202601', [
            ['dataElement' => 'DE-1', 'value' => 12],
            ['dataElement' => 'DE-2', 'categoryOptionCombo' => 'COC-1', 'value' => 3],
        ]);

        $this->assertSame('DS-1', $payload['dataSet']);
        $this->assertSame('202601', $payload['period']);
        $this->assertSame('OU-1', $payload['orgUnit']);
        $this->assertSame('default', $payload['dataValues'][0]['categoryOptionCombo']);
        $this->assertSame('COC-1', $payload['dataValues'][1]['categoryOptionCombo']);
    }

    /** @test */
    public function dry_run_builds_without_posting()
    {
        config()->set('dhis2.enabled', false);

        Http::fake();
        $result = (new Dhis2Adapter())->push('DS-1', '202601', [
            ['dataElement' => 'DE-1', 'value' => 1],
        ]);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['dry_run']);
        Http::assertNothingSent();
    }

    /** @test */
    public function live_push_posts_datavaluesets()
    {
        config()->set('dhis2.enabled', true);
        config()->set('dhis2.dry_run', false);
        config()->set('dhis2.base_url', 'https://dhis.example');
        config()->set('dhis2.username', 'u');
        config()->set('dhis2.password', 'p');
        config()->set('dhis2.org_unit', 'OU-1');

        Http::fake([
            'dhis.example/api/dataValueSets' => Http::response(['status' => 'SUCCESS'], 200),
        ]);

        $result = (new Dhis2Adapter())->push('DS-1', '202601', [
            ['dataElement' => 'DE-1', 'value' => 1],
        ]);

        $this->assertTrue($result['success']);
        $this->assertFalse($result['dry_run']);
    }

    /** @test */
    public function icd_shape_check_flags_uncoded_rows()
    {
        foreach (['B54', 'A00.1', 'J06.9', 'U07.1'] as $code) {
            $this->assertTrue(IcdCode::looksIcd10($code), "{$code} should read as ICD-10.");
        }
        foreach (['malaria', '', '123', 'B', null] as $code) {
            $this->assertFalse(IcdCode::looksIcd10($code), "'{$code}' should not read as ICD-10.");
        }
        $this->assertSame('http://hl7.org/fhir/sid/icd-10', IcdCode::system());
    }
}
