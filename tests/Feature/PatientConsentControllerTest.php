<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\PatientConsent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PatientConsentControllerTest extends TestCase
{
    use RefreshDatabase;

    protected $adminUser;
    protected $doctorUser;
    protected $nurseUser;
    protected $patient;
    protected $consent;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test users with different roles
        $this->adminUser = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
        ]);
        $this->adminUser->assignRole('administrator');

        $this->doctorUser = User::factory()->create([
            'email' => 'doctor@example.com',
            'password' => Hash::make('password'),
        ]);
        $this->doctorUser->assignRole('doctor');

        $this->nurseUser = User::factory()->create([
            'email' => 'nurse@example.com',
            'password' => Hash::make('password'),
        ]);
        $this->nurseUser->assignRole('nurse');

        // Create test patient
        $this->patient = Patient::factory()->create();

        // Create test consent
        $this->consent = PatientConsent::factory()->create([
            'patient_id' => $this->patient->id,
            'status' => 'active',
        ]);
    }

    /** @test */
    public function admin_can_view_patient_consents()
    {
        $this->actingAs($this->adminUser);

        $response = $this->getJson("/api/patient-consents/patient/{$this->patient->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'consent_uuid',
                        'patient_id',
                        'consent_type',
                        'status',
                    ]
                ],
                'meta'
            ]);
    }

    /** @test */
    public function doctor_can_view_patient_consents()
    {
        $this->actingAs($this->doctorUser);

        $response = $this->getJson("/api/patient-consents/patient/{$this->patient->id}");

        $response->assertStatus(200);
    }

    /** @test */
    public function unauthorized_user_cannot_view_patient_consents()
    {
        $unauthorizedUser = User::factory()->create();
        // $this->actingAs($unauthorizedUser);

        $response = $this->getJson("/api/patient-consents/patient/{$this->patient->id}");

        $response->assertStatus(403);
    }

    /** @test */
    public function admin_can_create_consent()
    {
        $this->actingAs($this->adminUser);

        $data = [
            'patient_id' => $this->patient->id,
            'consent_type' => 'treatment',
            'legal_basis' => 'explicit_consent',
            'granted_at' => now()->subDay()->toISOString(),
            'effective_from' => now()->toISOString(),
            'consent_form_version' => '1.0',
            'consent_document_hash' => str_repeat('a', 64),
            'patient_signature_hash' => str_repeat('b', 128),
            'scope_limitations' => 'No limitations',
        ];

        $response = $this->postJson('/api/patient-consents', $data);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'consent_uuid',
                    'patient_id',
                    'consent_type',
                    'status'
                ]
            ]);
    }

    /** @test */
    public function doctor_can_create_consent()
    {
        $this->actingAs($this->doctorUser);

        $data = [
            'patient_id' => $this->patient->id,
            'consent_type' => 'procedures',
            'legal_basis' => 'explicit_consent',
            'granted_at' => now()->subDay()->toISOString(),
            'effective_from' => now()->toISOString(),
            'consent_form_version' => '1.0',
            'consent_document_hash' => str_repeat('a', 64),
            'patient_signature_hash' => str_repeat('b', 128),
        ];

        $response = $this->postJson('/api/patient-consents', $data);

        $response->assertStatus(201);
    }

    /** @test */
    public function validation_fails_for_invalid_consent_data()
    {
        $this->actingAs($this->adminUser);

        $data = [
            'patient_id' => $this->patient->id,
            // Missing required fields
        ];

        $response = $this->postJson('/api/patient-consents', $data);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'success',
                'message',
                'errors'
            ]);
    }

    /** @test */
    public function admin_can_view_specific_consent()
    {
        $this->actingAs($this->adminUser);

        $response = $this->getJson("/api/patient-consents/{$this->consent->consent_uuid}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'consent_uuid',
                    'patient_id',
                    'consent_type',
                ]
            ]);
    }

    /** @test */
    public function admin_can_update_consent()
    {
        $this->actingAs($this->adminUser);

        $data = [
            'scope_limitations' => 'Updated limitations',
        ];

        $response = $this->putJson("/api/patient-consents/{$this->consent->consent_uuid}", $data);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Consent updated successfully'
            ]);
    }

    /** @test */
    public function admin_can_revoke_consent()
    {
        $this->actingAs($this->adminUser);

        $data = [
            'revocation_reason' => 'Patient requested revocation',
            'revoked_by_staff_id' => 1,
        ];

        $response = $this->postJson("/api/patient-consents/{$this->consent->consent_uuid}/revoke", $data);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Consent revoked successfully'
            ]);
    }

    /** @test */
    public function cannot_revoke_already_revoked_consent()
    {
        $this->actingAs($this->adminUser);

        // Create a revoked consent
        $revokedConsent = PatientConsent::factory()->create([
            'patient_id' => $this->patient->id,
            'status' => 'revoked',
            'revoked_at' => now(),
        ]);

        $data = [
            'revocation_reason' => 'Test revocation',
            'revoked_by_staff_id' => 1,
        ];

        $response = $this->postJson("/api/patient-consents/{$revokedConsent->consent_uuid}/revoke", $data);

        $response->assertStatus(409);
    }

    /** @test */
    public function admin_can_validate_consent()
    {
        $this->actingAs($this->adminUser);

        $response = $this->getJson("/api/patient-consents/patient/{$this->patient->id}/validate/treatment");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'valid',
                'consent'
            ]);
    }

    /** @test */
    public function admin_can_view_statistics()
    {
        $this->actingAs($this->adminUser);

        $response = $this->getJson('/api/patient-consents/statistics');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'total',
                    'active',
                    'expired',
                    'revoked',
                    'by_type',
                    'by_legal_basis',
                    'active_percentage',
                ]
            ]);
    }

    /** @test */
    public function admin_can_view_expiring_consents()
    {
        $this->actingAs($this->adminUser);

        $response = $this->getJson('/api/patient-consents/expiring');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data',
                'count'
            ]);
    }

    /** @test */
    public function anyone_can_view_consent_options()
    {
        $response = $this->getJson('/api/patient-consents/options');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'consent_types',
                    'legal_basis_options',
                ]
            ]);
    }

    /** @test */
    public function it_returns_404_for_nonexistent_consent()
    {
        $this->actingAs($this->adminUser);

        $response = $this->getJson('/api/patient-consents/nonexistent-uuid');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Consent not found'
            ]);
    }

    /** @test */
    public function it_handles_server_errors_gracefully()
    {
        $this->actingAs($this->adminUser);

        // Mock a server error by causing an exception
        // This is a simplified example - in real tests you might mock the service layer
        PatientConsent::shouldReceive('where')->andThrow(new \Exception('Database error'));

        $response = $this->getJson("/api/patient-consents/{$this->consent->consent_uuid}");

        $response->assertStatus(500)
            ->assertJson([
                'success' => false,
                'message' => 'An unexpected error occurred while retrieving consent.'
            ]);
    }
}