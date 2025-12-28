<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\PatientVisitSummaryView;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PatientVisitSummaryViewControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /**
     * @var User
     */
    protected User $adminUser;

    /**
     * @var User
     */
    protected User $providerUser;

    /**
     * @var Patient
     */
    protected Patient $patient;

    /**
     * @var PatientVisitSummaryView
     */
    protected PatientVisitSummaryView $summary;

    /**
     * Setup the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Create test users with different roles
        $this->adminUser = User::factory()->create([
            'role' => 'system_admin',
        ]);

        $this->providerUser = User::factory()->create([
            'role' => 'provider',
        ]);

        // Create a patient
        $this->patient = Patient::factory()->create([
            'primary_care_provider_id' => $this->providerUser->id,
        ]);

        // Create a summary view
        $this->summary = PatientVisitSummaryView::factory()->create([
            'patient_id' => $this->patient->id,
            'primary_care_provider_id' => $this->providerUser->id,
        ]);

        // Assign permissions (using spatie/laravel-permission or similar)
        $this->adminUser->givePermissionTo([
            'view_patient_summaries',
            'create_patient_summaries',
            'update_patient_summaries',
            'delete_patient_summaries',
            'refresh_patient_summaries',
            'batch_refresh_patient_summaries',
            'view_care_coordination_insights',
        ]);
    }

    /** @test */
    public function it_can_list_patient_visit_summary_views()
    {
        Sanctum::actingAs($this->adminUser);

        $response = $this->getJson('/api/patient-visit-summary-views');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'data' => [
                        '*' => [
                            'id',
                            'patient_id',
                            'active_visits_count',
                            'visits_last_30_days_count',
                            'last_visit_date',
                            'next_appointment_at',
                            'active_prescriptions_count',
                            'outstanding_bills_total',
                            'unpaid_invoices_count',
                            'unread_messages_count',
                            'last_updated_at',
                            'created_at',
                            'updated_at',
                        ],
                    ],
                    'meta',
                    'links',
                ],
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Patient visit summaries retrieved successfully',
            ]);
    }

    /** @test */
    public function it_can_show_a_patient_visit_summary_view()
    {
        Sanctum::actingAs($this->adminUser);

        $response = $this->getJson("/api/patient-visit-summary-views/{$this->summary->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'patient_id',
                    'active_visit_ids',
                    'active_visits_count',
                    'recent_visits_last_30_days',
                    'visits_last_30_days_count',
                    'last_visit_date',
                    'last_visit_facility_id',
                    'upcoming_appointments',
                    'next_appointment_at',
                    'active_prescriptions',
                    'pending_prescriptions',
                    'active_prescriptions_count',
                    'outstanding_bills_total',
                    'unpaid_invoices_count',
                    'payment_plans',
                    'health_metrics_trends',
                    'recent_lab_results',
                    'recent_imaging_results',
                    'care_team_members',
                    'primary_care_provider_id',
                    'preventive_care_due',
                    'immunizations_due',
                    'screenings_due',
                    'patient_alerts',
                    'unread_messages_count',
                    'last_updated_at',
                    'created_at',
                    'updated_at',
                    'has_upcoming_appointments',
                    'has_outstanding_bills',
                    'needs_attention',
                    'summary_status',
                ],
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Patient visit summary retrieved successfully',
                'data' => [
                    'id' => $this->summary->id,
                    'patient_id' => $this->patient->id,
                ],
            ]);
    }

    /** @test */
    public function it_can_show_a_patient_visit_summary_view_by_patient_id()
    {
        Sanctum::actingAs($this->adminUser);

        $response = $this->getJson("/api/patient-visit-summary-views/patient/{$this->patient->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Patient visit summary retrieved successfully',
                'data' => [
                    'patient_id' => $this->patient->id,
                ],
            ]);
    }

    /** @test */
    public function it_can_create_a_patient_visit_summary_view()
    {
        Sanctum::actingAs($this->adminUser);

        $newPatient = Patient::factory()->create();
        
        $data = [
            'patient_id' => $newPatient->id,
            'active_visits_count' => 0,
            'visits_last_30_days_count' => 0,
            'active_prescriptions_count' => 0,
            'outstanding_bills_total' => 0,
            'unpaid_invoices_count' => 0,
            'unread_messages_count' => 0,
        ];

        $response = $this->postJson('/api/patient-visit-summary-views', $data);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Patient visit summary created successfully',
            ])
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'patient_id',
                    'last_updated_at',
                ],
            ]);

        $this->assertDatabaseHas('patient_visit_summary_views', [
            'patient_id' => $newPatient->id,
            'active_visits_count' => 0,
        ]);
    }

    /** @test */
    public function it_validates_required_fields_when_creating_summary()
    {
        Sanctum::actingAs($this->adminUser);

        $response = $this->postJson('/api/patient-visit-summary-views', []);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'success',
                'message',
                'errors' => [
                    'patient_id',
                ],
            ])
            ->assertJson([
                'success' => false,
                'message' => 'Validation failed',
            ]);
    }

    /** @test */
    public function it_can_update_a_patient_visit_summary_view()
    {
        Sanctum::actingAs($this->adminUser);

        $data = [
            'active_visits_count' => 2,
            'visits_last_30_days_count' => 3,
            'unread_messages_count' => 5,
        ];

        $response = $this->putJson("/api/patient-visit-summary-views/{$this->summary->id}", $data);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Patient visit summary updated successfully',
                'data' => [
                    'id' => $this->summary->id,
                    'active_visits_count' => 2,
                    'visits_last_30_days_count' => 3,
                    'unread_messages_count' => 5,
                ],
            ]);

        $this->assertDatabaseHas('patient_visit_summary_views', [
            'id' => $this->summary->id,
            'active_visits_count' => 2,
            'visits_last_30_days_count' => 3,
            'unread_messages_count' => 5,
        ]);
    }

    /** @test */
    public function it_can_refresh_a_patient_visit_summary_view()
    {
        Sanctum::actingAs($this->adminUser);

        $response = $this->postJson("/api/patient-visit-summary-views/{$this->patient->id}/refresh");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Patient visit summary refreshed successfully',
            ]);
    }

    /** @test */
    public function it_can_delete_a_patient_visit_summary_view()
    {
        Sanctum::actingAs($this->adminUser);

        $response = $this->deleteJson("/api/patient-visit-summary-views/{$this->summary->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Patient visit summary deleted successfully',
            ]);

        $this->assertDatabaseMissing('patient_visit_summary_views', [
            'id' => $this->summary->id,
        ]);
    }

    /** @test */
    public function it_can_get_upcoming_appointments()
    {
        Sanctum::actingAs($this->adminUser);

        $startDate = now()->format('Y-m-d');
        $endDate = now()->addDays(30)->format('Y-m-d');

        $response = $this->getJson("/api/patient-visit-summary-views/upcoming-appointments?start_date={$startDate}&end_date={$endDate}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'start_date',
                    'end_date',
                    'appointments',
                ],
            ]);
    }

    /** @test */
    public function it_can_get_care_coordination_insights()
    {
        Sanctum::actingAs($this->adminUser);

        $response = $this->getJson('/api/patient-visit-summary-views/care-coordination-insights');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'insights' => [
                        'total_patients',
                        'patients_with_upcoming_appointments',
                        'patients_with_outstanding_bills',
                        'patients_with_preventive_care_due',
                        'average_visits_last_30_days',
                        'total_outstanding_bills',
                    ],
                    'filters_applied',
                ],
            ]);
    }

    /** @test */
    public function it_returns_403_when_unauthorized()
    {
        $unauthorizedUser = User::factory()->create(['role' => 'patient']);
        Sanctum::actingAs($unauthorizedUser);

        $response = $this->getJson('/api/patient-visit-summary-views');

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'You do not have permission to view patient visit summaries.',
            ]);
    }

    /** @test */
    public function it_returns_404_when_summary_not_found()
    {
        Sanctum::actingAs($this->adminUser);

        $response = $this->getJson('/api/patient-visit-summary-views/999999');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Patient visit summary not found',
            ]);
    }
}