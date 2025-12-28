<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Appointment;
use App\Models\User;
use App\Models\Facility;
use App\Models\Patient;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Carbon\Carbon;

class AppointmentControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private User $adminUser;
    private User $providerUser;
    private User $patientUser;
    private Facility $facility;
    private Patient $patient;
    private Staff $provider;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test data
        $this->facility = Facility::factory()->create();
        $this->patient = Patient::factory()->create();
        $this->provider = Staff::factory()->create();

        // Create users with different roles
        $this->adminUser = User::factory()->create(['role' => 'admin']);
        $this->providerUser = User::factory()->create([
            'role' => 'healthcare_provider',
            'staff_id' => $this->provider->id,
        ]);
        $this->patientUser = User::factory()->create([
            'role' => 'patient',
            'patient_id' => $this->patient->id,
        ]);
    }

    /** @test */
    public function admin_can_view_all_appointments()
    {
        // Arrange
        Appointment::factory()->count(3)->create();
        
        // Act
        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/appointments');

        // Assert
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'data' => [
                        '*' => [
                            'id',
                            'appointment_uuid',
                            'appointment_type',
                            'status',
                        ]
                    ],
                    'pagination'
                ]
            ]);
    }

    /** @test */
    public function patient_can_only_view_their_own_appointments()
    {
        // Arrange
        $patientAppointment = Appointment::factory()->create([
            'patient_id' => $this->patient->id,
        ]);
        
        Appointment::factory()->create(); // Other appointment

        // Act
        $response = $this->actingAs($this->patientUser)
            ->getJson('/api/appointments');

        // Assert
        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data.data'));
        $this->assertEquals(
            $patientAppointment->id,
            $response->json('data.data.0.id')
        );
    }

    /** @test */
    public function admin_can_create_an_appointment()
    {
        // Arrange
        $appointmentData = [
            'facility_id' => $this->facility->id,
            'patient_id' => $this->patient->id,
            'provider_staff_id' => $this->provider->id,
            'appointment_type' => 'consultation',
            'scheduled_start_time' => now()->addDay()->format('Y-m-d H:i:s'),
            'duration_minutes' => 30,
            'reason_for_visit' => 'Annual checkup',
        ];

        // Act
        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/appointments', $appointmentData);

        // Assert
        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Appointment created successfully',
            ])
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'appointment_uuid',
                    'appointment_type',
                    'status',
                ]
            ]);

        $this->assertDatabaseHas('appointments', [
            'patient_id' => $this->patient->id,
            'provider_staff_id' => $this->provider->id,
        ]);
    }

    /** @test */
    public function validation_fails_for_invalid_appointment_data()
    {
        // Arrange
        $invalidData = [
            'facility_id' => 'invalid',
            'duration_minutes' => 500, // Too long
        ];

        // Act
        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/appointments', $invalidData);

        // Assert
        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Validation failed',
            ])
            ->assertJsonValidationErrors([
                'facility_id',
                'patient_id',
                'provider_staff_id',
                'appointment_type',
                'scheduled_start_time',
                'duration_minutes',
            ]);
    }

    /** @test */
    public function admin_can_update_an_appointment()
    {
        // Arrange
        $appointment = Appointment::factory()->create([
            'status' => Appointment::STATUS_SCHEDULED,
        ]);

        $updateData = [
            'reason_for_visit' => 'Updated reason',
        ];

        // Act
        $response = $this->actingAs($this->adminUser)
            ->putJson("/api/appointments/{$appointment->appointment_uuid}", $updateData);

        // Assert
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Appointment updated successfully',
            ]);

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'reason_for_visit' => 'Updated reason',
        ]);
    }

    /** @test */
    public function cannot_update_completed_appointment()
    {
        // Arrange
        $appointment = Appointment::factory()->create([
            'status' => Appointment::STATUS_COMPLETED,
        ]);

        $updateData = ['reason_for_visit' => 'Should not update'];

        // Act
        $response = $this->actingAs($this->adminUser)
            ->putJson("/api/appointments/{$appointment->appointment_uuid}", $updateData);

        // Assert
        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Cannot update a completed or cancelled appointment',
            ]);
    }

    /** @test */
    public function admin_can_delete_an_appointment()
    {
        // Arrange
        $appointment = Appointment::factory()->create([
            'status' => Appointment::STATUS_SCHEDULED,
        ]);

        // Act
        $response = $this->actingAs($this->adminUser)
            ->deleteJson("/api/appointments/{$appointment->appointment_uuid}");

        // Assert
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Appointment deleted successfully',
            ]);

        $this->assertSoftDeleted('appointments', ['id' => $appointment->id]);
    }

    /** @test */
    public function cannot_delete_in_progress_appointment()
    {
        // Arrange
        $appointment = Appointment::factory()->create([
            'status' => Appointment::STATUS_IN_PROGRESS,
        ]);

        // Act
        $response = $this->actingAs($this->adminUser)
            ->deleteJson("/api/appointments/{$appointment->appointment_uuid}");

        // Assert
        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Cannot delete an appointment that is in progress or completed',
            ]);
    }

    /** @test */
    public function admin_can_cancel_an_appointment()
    {
        // Arrange
        $appointment = Appointment::factory()->create([
            'status' => Appointment::STATUS_SCHEDULED,
            'scheduled_start_time' => now()->addDays(3),
        ]);

        $cancelData = ['cancellation_reason' => 'Patient request'];

        // Act
        $response = $this->actingAs($this->adminUser)
            ->postJson("/api/appointments/{$appointment->appointment_uuid}/cancel", $cancelData);

        // Assert
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Appointment cancelled successfully',
            ]);

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => Appointment::STATUS_CANCELLED,
            'cancellation_reason' => 'Patient request',
        ]);
    }

    /** @test */
    public function patient_can_check_in_for_their_appointment()
    {
        // Arrange
        $appointment = Appointment::factory()->create([
            'patient_id' => $this->patient->id,
            'status' => Appointment::STATUS_CONFIRMED,
            'scheduled_start_time' => now()->addMinutes(15),
        ]);

        // Act
        $response = $this->actingAs($this->patientUser)
            ->postJson("/api/appointments/{$appointment->appointment_uuid}/check-in");

        // Assert
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Checked in successfully',
            ]);

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => Appointment::STATUS_CHECKED_IN,
        ]);
    }

    /** @test */
    public function provider_can_complete_their_appointment()
    {
        // Arrange
        $appointment = Appointment::factory()->create([
            'provider_staff_id' => $this->provider->id,
            'status' => Appointment::STATUS_IN_PROGRESS,
        ]);

        // Act
        $response = $this->actingAs($this->providerUser)
            ->postJson("/api/appointments/{$appointment->appointment_uuid}/complete");

        // Assert
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Appointment completed successfully',
            ]);

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => Appointment::STATUS_COMPLETED,
        ]);
    }

    /** @test */
    public function can_check_appointment_availability()
    {
        // Arrange
        $availabilityData = [
            'facility_id' => $this->facility->id,
            'provider_staff_id' => $this->provider->id,
            'date' => now()->addDay()->format('Y-m-d'),
            'duration_minutes' => 30,
        ];

        // Act
        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/appointments/availability/check', $availabilityData);

        // Assert
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Availability retrieved successfully',
            ])
            ->assertJsonStructure([
                'data' => [
                    'date',
                    'available_slots',
                    'total_slots',
                ]
            ]);
    }

    /** @test */
    public function can_get_appointment_statistics()
    {
        // Arrange
        Appointment::factory()->count(5)->create(['status' => Appointment::STATUS_COMPLETED]);
        Appointment::factory()->count(3)->create(['status' => Appointment::STATUS_SCHEDULED]);

        // Act
        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/appointments/statistics');

        // Assert
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Statistics retrieved successfully',
            ])
            ->assertJsonStructure([
                'data' => [
                    'total',
                    'scheduled',
                    'confirmed',
                    'completed',
                    'cancelled',
                    'no_show',
                    'average_duration',
                ]
            ]);
    }

    /** @test */
    public function unauthorized_user_cannot_access_protected_routes()
    {
        // Arrange
        $appointment = Appointment::factory()->create();

        // Test various routes without authentication
        $routes = [
            ['method' => 'GET', 'url' => '/api/appointments'],
            ['method' => 'POST', 'url' => '/api/appointments'],
            ['method' => 'GET', 'url' => "/api/appointments/{$appointment->appointment_uuid}"],
            ['method' => 'PUT', 'url' => "/api/appointments/{$appointment->appointment_uuid}"],
        ];

        foreach ($routes as $route) {
            // Act
            $response = $this->json($route['method'], $route['url']);

            // Assert
            $response->assertStatus(401);
        }
    }

    /** @test */
    public function user_without_permission_cannot_access_restricted_actions()
    {
        // Arrange
        $appointment = Appointment::factory()->create([
            'provider_staff_id' => $this->provider->id + 1, // Different provider
        ]);

        // Act - Provider trying to complete someone else's appointment
        $response = $this->actingAs($this->providerUser)
            ->postJson("/api/appointments/{$appointment->appointment_uuid}/complete");

        // Assert
        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'You are not authorized to complete this appointment',
            ]);
    }
}