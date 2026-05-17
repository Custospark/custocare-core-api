<?php

namespace Tests\Feature;

use App\Models\Facility;
use App\Models\FacilityStaffRole;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class EnsureStaffFacilityAccessMiddlewareTest extends TestCase
{
    use WithFaker;

    private User $user;
    private Staff $staff;
    private Facility $facility;
    private FacilityStaffRole $assignment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createRequiredTables();

        $this->user = User::factory()->create();
        $this->staff = Staff::factory()->active()->create(['user_id' => $this->user->id]);
        $this->facility = Facility::create([
            'facility_uuid' => $this->faker->uuid(),
            'facility_code' => 'TEST-' . $this->faker->unique()->bothify('####'),
            'facility_name' => 'Test Facility',
            'legal_entity_name' => 'Test Facility LLC',
            'facility_type' => 'clinic',
            'facility_tier' => 'primary',
            'address_line1' => '123 Test St',
            'city' => 'Test City',
            'state_province' => 'TS',
            'postal_code' => '12345',
            'country_code' => 'USA',
            'timezone' => 'America/New_York',
            'operating_hours' => ['mon' => '9-5'],
            'available_services' => ['general'],
            'data_residency_region' => 'US',
            'primary_database_shard' => 'shard_01',
            'operational_status' => 'fully_operational',
            'currency' => 'USD',
        ]);
        $this->assignment = FacilityStaffRole::create([
            'assignment_uuid' => Str::uuid()->toString(),
            'staff_id' => $this->staff->id,
            'facility_id' => $this->facility->id,
            'role_code' => 'attending_physician',
            'assignment_status' => 'active',
            'effective_from' => now()->subMonth()->format('Y-m-d'),
            'effective_to' => now()->addYear()->format('Y-m-d'),
            'shift_schedule' => ['monday' => ['start' => '08:00', 'end' => '17:00']],
            'shift_type' => 'day',
        ]);

        $this->app['router']->get('/api/test/staff-facility-access')
            ->middleware('auth')
            ->uses(fn () => response()->json([
                'success' => true,
                'message' => 'Middleware passed',
                'data' => null,
            ]));
    }

    protected function tearDown(): void
    {
        $this->dropRequiredTables();
        parent::tearDown();
    }

    public function test_passes_with_valid_headers(): void
    {
        $this->actingAs($this->user)
            ->withHeaders([
                'X-Staff-Id' => $this->staff->id,
                'X-Active-Facility-Id' => $this->facility->id,
            ])
            ->getJson('/api/test/staff-facility-access')
            ->assertStatus(200)
            ->assertJson(['message' => 'Middleware passed']);
    }

    public function test_passes_with_both_headers_missing(): void
    {
        $this->actingAs($this->user)
            ->getJson('/api/test/staff-facility-access')
            ->assertStatus(200)
            ->assertJson(['message' => 'Middleware passed']);
    }

    public function test_passes_with_only_staff_header(): void
    {
        $this->actingAs($this->user)
            ->withHeaders([
                'X-Staff-Id' => $this->staff->id,
            ])
            ->getJson('/api/test/staff-facility-access')
            ->assertStatus(200)
            ->assertJson(['message' => 'Middleware passed']);
    }

    public function test_passes_with_only_facility_header(): void
    {
        $this->actingAs($this->user)
            ->withHeaders([
                'X-Active-Facility-Id' => $this->facility->id,
            ])
            ->getJson('/api/test/staff-facility-access')
            ->assertStatus(200)
            ->assertJson(['message' => 'Middleware passed']);
    }

    public function test_rejects_invalid_staff_id(): void
    {
        $this->actingAs($this->user)
            ->withHeaders([
                'X-Staff-Id' => 99999,
                'X-Active-Facility-Id' => $this->facility->id,
            ])
            ->getJson('/api/test/staff-facility-access')
            ->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Staff record not found.',
            ]);
    }

    public function test_rejects_invalid_facility_id(): void
    {
        $this->actingAs($this->user)
            ->withHeaders([
                'X-Staff-Id' => $this->staff->id,
                'X-Active-Facility-Id' => 99999,
            ])
            ->getJson('/api/test/staff-facility-access')
            ->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Facility record not found.',
            ]);
    }

    public function test_rejects_unassigned_staff(): void
    {
        $otherFacility = Facility::create([
            'facility_uuid' => $this->faker->uuid(),
            'facility_code' => 'TEST-' . $this->faker->unique()->bothify('####'),
            'facility_name' => 'Other Facility',
            'legal_entity_name' => 'Other Facility LLC',
            'facility_type' => 'clinic',
            'facility_tier' => 'primary',
            'address_line1' => '456 Other St',
            'city' => 'Other City',
            'state_province' => 'OT',
            'postal_code' => '67890',
            'country_code' => 'USA',
            'timezone' => 'America/New_York',
            'operating_hours' => ['mon' => '9-5'],
            'available_services' => ['general'],
            'data_residency_region' => 'US',
            'primary_database_shard' => 'shard_02',
            'operational_status' => 'fully_operational',
            'currency' => 'USD',
        ]);

        $this->actingAs($this->user)
            ->withHeaders([
                'X-Staff-Id' => $this->staff->id,
                'X-Active-Facility-Id' => $otherFacility->id,
            ])
            ->getJson('/api/test/staff-facility-access')
            ->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Staff is not assigned to this facility.',
            ]);
    }

    public function test_passes_with_on_leave_assignment(): void
    {
        $onLeaveAssignment = FacilityStaffRole::create([
            'assignment_uuid' => Str::uuid()->toString(),
            'staff_id' => $this->staff->id,
            'facility_id' => $this->facility->id,
            'role_code' => 'attending_physician',
            'assignment_status' => 'on_leave',
            'effective_from' => now()->subMonth()->format('Y-m-d'),
            'effective_to' => now()->addYear()->format('Y-m-d'),
            'shift_schedule' => ['monday' => ['start' => '08:00', 'end' => '17:00']],
            'shift_type' => 'day',
        ]);

        FacilityStaffRole::where('id', $this->assignment->id)->delete();

        $this->actingAs($this->user)
            ->withHeaders([
                'X-Staff-Id' => $this->staff->id,
                'X-Active-Facility-Id' => $this->facility->id,
            ])
            ->getJson('/api/test/staff-facility-access')
            ->assertStatus(200)
            ->assertJson(['message' => 'Middleware passed']);
    }

    public function test_uses_x_facility_id_fallback(): void
    {
        $this->actingAs($this->user)
            ->withHeaders([
                'X-Staff-Id' => $this->staff->id,
                'X-Facility-Id' => $this->facility->id,
            ])
            ->getJson('/api/test/staff-facility-access')
            ->assertStatus(200)
            ->assertJson(['message' => 'Middleware passed']);
    }

    public function test_rejects_terminated_assignment(): void
    {
        FacilityStaffRole::where('id', $this->assignment->id)
            ->update(['assignment_status' => 'terminated']);

        $this->actingAs($this->user)
            ->withHeaders([
                'X-Staff-Id' => $this->staff->id,
                'X-Active-Facility-Id' => $this->facility->id,
            ])
            ->getJson('/api/test/staff-facility-access')
            ->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Staff is not assigned to this facility.',
            ]);
    }

    // --- Attacker simulation tests ---

    public function test_rejects_non_numeric_staff_id(): void
    {
        $this->actingAs($this->user)
            ->withHeaders([
                'X-Staff-Id' => 'some-random-uuid-string',
                'X-Active-Facility-Id' => $this->facility->id,
            ])
            ->getJson('/api/test/staff-facility-access')
            ->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Staff record not found.',
            ]);
    }

    public function test_rejects_non_numeric_facility_id(): void
    {
        $this->actingAs($this->user)
            ->withHeaders([
                'X-Staff-Id' => $this->staff->id,
                'X-Active-Facility-Id' => 'not-a-valid-id',
            ])
            ->getJson('/api/test/staff-facility-access')
            ->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Facility record not found.',
            ]);
    }

    public function test_sql_injection_in_staff_id_safely_returns_404(): void
    {
        $this->actingAs($this->user)
            ->withHeaders([
                'X-Staff-Id' => '999 UNION SELECT * FROM staff',
                'X-Active-Facility-Id' => $this->facility->id,
            ])
            ->getJson('/api/test/staff-facility-access')
            ->assertStatus(404);
    }

    public function test_sql_injection_in_facility_id_safely_returns_404(): void
    {
        $this->actingAs($this->user)
            ->withHeaders([
                'X-Staff-Id' => $this->staff->id,
                'X-Active-Facility-Id' => '999; DROP TABLE facilities; --',
            ])
            ->getJson('/api/test/staff-facility-access')
            ->assertStatus(404);
    }

    public function test_rejects_expired_assignment_date(): void
    {
        $assignment = FacilityStaffRole::create([
            'assignment_uuid' => Str::uuid()->toString(),
            'staff_id' => $this->staff->id,
            'facility_id' => $this->facility->id,
            'role_code' => 'attending_physician',
            'assignment_status' => 'active',
            'effective_from' => now()->subMonths(6)->format('Y-m-d'),
            'effective_to' => now()->subMonth()->format('Y-m-d'),
            'shift_schedule' => ['monday' => ['start' => '08:00', 'end' => '17:00']],
            'shift_type' => 'day',
        ]);

        FacilityStaffRole::where('id', $this->assignment->id)->delete();

        $this->actingAs($this->user)
            ->withHeaders([
                'X-Staff-Id' => $this->staff->id,
                'X-Active-Facility-Id' => $this->facility->id,
            ])
            ->getJson('/api/test/staff-facility-access')
            ->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Staff is not assigned to this facility.',
            ]);
    }

    public function test_rejects_future_effective_from(): void
    {
        $assignment = FacilityStaffRole::create([
            'assignment_uuid' => Str::uuid()->toString(),
            'staff_id' => $this->staff->id,
            'facility_id' => $this->facility->id,
            'role_code' => 'attending_physician',
            'assignment_status' => 'active',
            'effective_from' => now()->addMonth()->format('Y-m-d'),
            'effective_to' => now()->addYear()->format('Y-m-d'),
            'shift_schedule' => ['monday' => ['start' => '08:00', 'end' => '17:00']],
            'shift_type' => 'day',
        ]);

        FacilityStaffRole::where('id', $this->assignment->id)->delete();

        $this->actingAs($this->user)
            ->withHeaders([
                'X-Staff-Id' => $this->staff->id,
                'X-Active-Facility-Id' => $this->facility->id,
            ])
            ->getJson('/api/test/staff-facility-access')
            ->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Staff is not assigned to this facility.',
            ]);
    }

    public function test_passes_through_empty_header_values(): void
    {
        $this->actingAs($this->user)
            ->withHeaders([
                'X-Staff-Id' => '',
                'X-Active-Facility-Id' => '',
            ])
            ->getJson('/api/test/staff-facility-access')
            ->assertStatus(200)
            ->assertJson(['message' => 'Middleware passed']);
    }

    public function test_rejects_negative_staff_id(): void
    {
        $this->actingAs($this->user)
            ->withHeaders([
                'X-Staff-Id' => '-1',
                'X-Active-Facility-Id' => $this->facility->id,
            ])
            ->getJson('/api/test/staff-facility-access')
            ->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Staff record not found.',
            ]);
    }

    public function test_rejects_huge_integer_staff_id(): void
    {
        $this->actingAs($this->user)
            ->withHeaders([
                'X-Staff-Id' => '9999999999999999999',
                'X-Active-Facility-Id' => $this->facility->id,
            ])
            ->getJson('/api/test/staff-facility-access')
            ->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Staff record not found.',
            ]);
    }

    public function test_rejects_known_staff_at_wrong_facility_using_facility_uuid(): void
    {
        $this->actingAs($this->user)
            ->withHeaders([
                'X-Staff-Id' => $this->staff->id,
                'X-Active-Facility-Id' => $this->facility->facility_uuid,
            ])
            ->getJson('/api/test/staff-facility-access')
            ->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Facility record not found.',
            ]);
    }

    private function createRequiredTables(): void
    {
        if (!Schema::hasTable('users')) {
            Schema::create('users', function ($table) {
                $table->id();
                $table->uuid('global_user_uuid')->nullable()->unique();
                $table->string('national_id_hash', 128)->nullable();
                $table->string('national_id_encrypted', 512)->nullable();
                $table->string('national_id_country_code', 3)->nullable();
                $table->string('identity_state', 50)->default('pending');
                $table->timestamp('identity_verified_at')->nullable();
                $table->string('data_residency_region', 10)->nullable();
                $table->string('email')->nullable();
                $table->string('email_encrypted', 512)->nullable();
                $table->string('email_hash', 128)->nullable();
                $table->timestamp('email_verified_at')->nullable();
                $table->string('phone_encrypted', 512)->nullable();
                $table->string('phone_hash', 128)->nullable();
                $table->string('first_name')->nullable();
                $table->string('last_name')->nullable();
                $table->string('title')->nullable();
                $table->string('display_name')->nullable();
                $table->date('dob')->nullable();
                $table->string('gender', 10)->nullable();
                $table->string('address_line1')->nullable();
                $table->string('address_line2')->nullable();
                $table->string('city')->nullable();
                $table->string('state')->nullable();
                $table->string('country')->nullable();
                $table->string('name')->nullable();
                $table->string('postal_code')->nullable();
                $table->string('password_hash', 255)->nullable();
                $table->string('password', 255)->nullable();
                $table->timestamp('password_changed_at')->nullable();
                $table->boolean('requires_password_change')->default(false);
                $table->boolean('mfa_enabled')->default(true);
                $table->rememberToken();
                $table->string('mfa_secret_encrypted', 512)->nullable();
                $table->timestamp('last_login_at')->nullable();
                $table->string('last_login_ip', 45)->nullable();
                $table->string('last_login_user_agent', 512)->nullable();
                $table->unsignedInteger('failed_login_attempts')->default(0);
                $table->timestamp('account_locked_until')->nullable();
                $table->timestamps();
                $table->softDeletes();
                $table->unsignedBigInteger('created_by_staff_id')->nullable();
                $table->unsignedBigInteger('updated_by_staff_id')->nullable();
                $table->string('created_ip', 45)->nullable();
                $table->json('metadata')->nullable();
                $table->string('theme_mode')->nullable();
                $table->string('ui_density')->nullable();
            });

            Schema::create('password_reset_tokens', function ($table) {
                $table->string('email')->primary();
                $table->string('token');
                $table->timestamp('created_at')->nullable();
            });

            Schema::create('sessions', function ($table) {
                $table->string('id')->primary();
                $table->foreignId('user_id')->nullable()->index();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->longText('payload');
                $table->integer('last_activity')->index();
            });
        }

        if (!Schema::hasTable('personal_access_tokens')) {
            Schema::create('personal_access_tokens', function ($table) {
                $table->id();
                $table->morphs('tokenable');
                $table->string('name');
                $table->string('token', 64)->unique();
                $table->text('abilities')->nullable();
                $table->timestamp('last_used_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('facilities')) {
            Schema::create('facilities', function ($table) {
                $table->id();
                $table->uuid('facility_uuid')->unique();
                $table->string('facility_code', 50)->unique();
                $table->string('facility_name', 200);
                $table->string('legal_entity_name', 200);
                $table->string('facility_type', 50);
                $table->string('facility_tier', 50);
                $table->string('address_line1', 200);
                $table->string('city', 100);
                $table->string('state_province', 100);
                $table->string('postal_code', 20);
                $table->string('country_code', 3);
                $table->string('timezone', 50);
                $table->json('operating_hours');
                $table->json('available_services');
                $table->string('data_residency_region', 10);
                $table->string('primary_database_shard', 50);
                $table->string('operational_status', 50);
                $table->string('currency', 3);
                $table->string('tax_id_encrypted', 512)->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (!Schema::hasTable('staff')) {
            Schema::create('staff', function ($table) {
                $table->id();
                $table->uuid('staff_uuid')->unique();
                $table->foreignId('user_id')->constrained();
                $table->string('employee_id', 50)->nullable();
                $table->string('professional_title', 100)->nullable();
                $table->string('professional_license_number_encrypted', 512)->nullable();
                $table->string('professional_license_number_hash', 128)->nullable();
                $table->string('license_issuing_state', 100)->nullable();
                $table->string('license_issuing_country', 100)->nullable();
                $table->date('license_expiry_date')->nullable();
                $table->json('specialization_codes')->nullable();
                $table->json('board_certifications')->nullable();
                $table->json('additional_certifications')->nullable();
                $table->string('npi_number', 20)->nullable();
                $table->string('dea_number_encrypted', 512)->nullable();
                $table->date('dea_expiry_date')->nullable();
                $table->string('employment_status', 50)->default('active');
                $table->string('employment_type', 50)->nullable();
                $table->date('hire_date')->nullable();
                $table->date('termination_date')->nullable();
                $table->string('termination_reason')->nullable();
                $table->json('clinical_privileges')->nullable();
                $table->json('prescribing_authority')->nullable();
                $table->boolean('can_supervise_trainees')->default(false);
                $table->boolean('can_order_controlled_substances')->default(false);
                $table->boolean('can_sign_death_certificates')->default(false);
                $table->string('global_role_level', 50)->nullable();
                $table->unsignedBigInteger('reports_to_staff_id')->nullable();
                $table->json('default_schedule')->nullable();
                $table->unsignedInteger('max_concurrent_patients')->nullable();
                $table->unsignedInteger('average_appointment_duration_minutes')->nullable();
                $table->boolean('accepts_new_patients')->default(true);
                $table->decimal('patient_satisfaction_score', 5, 2)->nullable();
                $table->unsignedInteger('total_patients_treated')->default(0);
                $table->json('quality_metrics')->nullable();
                $table->timestamp('last_peer_review_date')->nullable();
                $table->timestamp('last_competency_assessment_date')->nullable();
                $table->boolean('background_check_completed')->default(false);
                $table->date('background_check_date')->nullable();
                $table->boolean('drug_screening_completed')->default(false);
                $table->date('drug_screening_date')->nullable();
                $table->json('immunization_records')->nullable();
                $table->json('tb_test_records')->nullable();
                $table->boolean('hipaa_training_completed')->default(false);
                $table->date('hipaa_training_date')->nullable();
                $table->date('hipaa_training_expiry')->nullable();
                $table->string('work_phone_encrypted', 512)->nullable();
                $table->string('work_email_encrypted', 512)->nullable();
                $table->string('emergency_contact_encrypted', 512)->nullable();
                $table->json('system_permissions')->nullable();
                $table->json('accessible_facility_ids')->nullable();
                $table->json('accessible_department_ids')->nullable();
                $table->unsignedBigInteger('created_by_staff_id')->nullable();
                $table->unsignedBigInteger('updated_by_staff_id')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (!Schema::hasTable('facility_staff_roles')) {
            Schema::create('facility_staff_roles', function ($table) {
                $table->id();
                $table->uuid('assignment_uuid')->unique();
                $table->foreignId('facility_id')->constrained();
                $table->foreignId('staff_id')->constrained();
                $table->string('role_code', 100);
                $table->string('assignment_status', 50)->default('active');
                $table->date('effective_from');
                $table->date('effective_to')->nullable();
                $table->boolean('is_primary_facility')->default(false);
                $table->json('department_ids')->nullable();
                $table->json('module_code')->nullable();
                $table->json('privileges_bitmask')->nullable();
                $table->json('accessible_patient_populations')->nullable();
                $table->json('prescribing_authority_at_facility')->nullable();
                $table->json('shift_schedule')->nullable();
                $table->string('shift_type', 50)->nullable();
                $table->unsignedSmallInteger('hours_per_week')->nullable();
                $table->string('employment_status', 50)->default('employed');
                $table->string('employment_type', 50)->default('full_time');
                $table->date('hire_date')->nullable();
                $table->date('termination_date')->nullable();
                $table->text('termination_reason')->nullable();
                $table->unsignedBigInteger('staff_invitation_id')->nullable();
                $table->unsignedInteger('patients_treated_at_facility')->default(0);
                $table->decimal('facility_satisfaction_score', 3, 2)->nullable();
                $table->timestamps();
                $table->softDeletes();
                $table->unsignedBigInteger('created_by_staff_id')->nullable();
                $table->json('metadata')->nullable();
            });
        }
    }

    private function dropRequiredTables(): void
    {
        Schema::dropIfExists('facility_staff_roles');
        Schema::dropIfExists('staff');
        Schema::dropIfExists('facilities');
        Schema::dropIfExists('users');
    }
}
