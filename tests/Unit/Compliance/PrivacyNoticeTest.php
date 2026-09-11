<?php

declare(strict_types=1);

namespace Tests\Unit\Compliance;

use App\Repositories\Contracts\PatientConsentRepositoryInterface;
use App\Services\Compliance\PrivacyNotice;
use App\Services\PatientConsent\PatientConsentService;
use Mockery;
use Tests\TestCase;

class PrivacyNoticeTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function document_contains_all_nine_mandatory_items()
    {
        $doc = PrivacyNotice::document();

        $this->assertMatchesRegularExpression('/^\d+\.\d+\.\d+$/', $doc['version']);
        $this->assertNotEmpty($doc['effective_at']);
        $this->assertNotEmpty($doc['controller']['name']);
        $this->assertNotEmpty($doc['dpo_contact']);

        $keys = array_column($doc['items'], 'key');
        foreach (['data_nature', 'controller', 'purpose', 'discretionary', 'consequences', 'legal_basis', 'recipients', 'rights', 'retention'] as $required) {
            $this->assertContains($required, $keys, "Missing mandatory notice item: {$required}");
        }

        foreach ($doc['items'] as $item) {
            $this->assertNotEmpty($item['title']);
            $this->assertGreaterThan(20, strlen($item['body']), "Item {$item['key']} body is too thin to inform.");
        }
    }

    /** @test */
    public function stale_detection_drives_fresh_consent()
    {
        $this->assertTrue(PrivacyNotice::isStale(null));
        $this->assertTrue(PrivacyNotice::isStale(''));
        $this->assertTrue(PrivacyNotice::isStale('0.0.0'));
        $this->assertFalse(PrivacyNotice::isStale(PrivacyNotice::version()));
    }

    /** @test */
    public function consent_service_flags_outdated_notice_versions()
    {
        $service = new PatientConsentService(Mockery::mock(PatientConsentRepositoryInterface::class));

        $this->assertTrue($service->consentNeedsRefresh(null));
        $this->assertTrue($service->consentNeedsRefresh('0.0.0'));
        $this->assertFalse($service->consentNeedsRefresh(PrivacyNotice::version()));
    }
}
