<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Facility;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DepartmentControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var User
     */
    protected $adminUser;

    /**
     * @var User
     */
    protected $regularUser;

    /**
     * @var Facility
     */
    protected $facility;

    /**
     * @var Staff
     */
    protected $departmentHead;

    /**
     * Set up the test environment.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Create test users
        $this->adminUser = User::factory()->create(['email' => 'admin@test.com']);
        $this->adminUser->assignRole('admin');

        $this->regularUser = User::factory()->create(['email' => 'user@test.com']);
        $this->regularUser->assignRole('user');

        // Create test facility
        $this->facility = Facility::factory()->create();

        // Create department head
        $this->departmentHead = Staff::factory()->create();
    }

    /**
     * Test getting all departments.
     *
     * @return void
     */
    public function test_get_all_departments(): void
    {
        // Create test departments
        Department::factory()->count(3)->create(['facility_id' => $this->facility->id]);

        // Make authenticated request
        Sanctum::actingAs($this->adminUser);

        $response = $this->getJson('/api/departments');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'department_uuid',
                        'department_code',
                        'department_name',
                        'department_type',
                        'status',
                    ]
                ],
                'meta' => [
                    'current_page',
                    'per_page',
                    'total',
                ]
            ]);
    }

    /**
     * Test creating a department successfully.
     *
     * @return void
     */
    public function test_create_department_successfully(): void
    {
        Sanctum::actingAs($this->adminUser);

        $departmentData = [
            'facility_id' => $this->facility->id,
            'department_code' => 'EMERG',
            'department_name' => 'Emergency Department',
            'department_type' => 'emergency',
            'max_concurrent_capacity' => 50,
            'accepts_walk_ins' => true,
            'requires_appointment' => false,
            'status' => 'active',
        ];

        $response = $this->postJson('/api/departments', $departmentData);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Department created successfully.',
            ])
            ->assertJsonStructure([
                'data' => [
                    'department_uuid',
                    'department_code',
                    'department_name',
                    'department_type',
                    'status',
                ]
            ]);

        // Verify database record
        $this->assertDatabaseHas('departments', [
            'department_code' => 'EMERG',
            'department_name' => 'Emergency Department',
            'facility_id' => $this->facility->id,
        ]);
    }

    /**
     * Test creating a department with validation errors.
     *
     * @return void
     */
    public function test_create_department_with_validation_errors(): void
    {
        Sanctum::actingAs($this->adminUser);

        $invalidData = [
            'department_code' => 'EMERG',
            // Missing required fields: facility_id, department_name, department_type
        ];

        $response = $this->postJson('/api/departments', $invalidData);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Validation failed.',
            ])
            ->assertJsonStructure([
                'errors' => [
                    'facility_id',
                    'department_name',
                    'department_type',
                ]
            ]);
    }

    /**
     * Test getting a specific department.
     *
     * @return void
     */
    public function test_get_department(): void
    {
        $department = Department::factory()->create([
            'facility_id' => $this->facility->id,
            'department_head_staff_id' => $this->departmentHead->id,
        ]);

        Sanctum::actingAs($this->adminUser);

        $response = $this->getJson("/api/departments/{$department->department_uuid}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Department retrieved successfully.',
            ])
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'department_uuid',
                    'department_code',
                    'department_name',
                    'department_type',
                    'status',
                    'facility',
                    'department_head',
                ]
            ]);
    }

    /**
     * Test updating a department.
     *
     * @return void
     */
    public function test_update_department(): void
    {
        $department = Department::factory()->create([
            'facility_id' => $this->facility->id,
            'status' => 'active',
        ]);

        Sanctum::actingAs($this->adminUser);

        $updateData = [
            'department_name' => 'Updated Department Name',
            'max_concurrent_capacity' => 75,
            'status' => 'inactive',
        ];

        $response = $this->putJson("/api/departments/{$department->department_uuid}", $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Department updated successfully.',
            ]);

        // Verify database update
        $this->assertDatabaseHas('departments', [
            'id' => $department->id,
            'department_name' => 'Updated Department Name',
            'max_concurrent_capacity' => 75,
            'status' => 'inactive',
        ]);
    }

    /**
     * Test deleting a department.
     *
     * @return void
     */
    public function test_delete_department(): void
    {
        $department = Department::factory()->create([
            'facility_id' => $this->facility->id,
        ]);

        Sanctum::actingAs($this->adminUser);

        $response = $this->deleteJson("/api/departments/{$department->department_uuid}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Department deleted successfully.',
            ]);

        // Verify soft delete
        $this->assertSoftDeleted('departments', ['id' => $department->id]);
    }

    /**
     * Test restoring a soft-deleted department.
     *
     * @return void
     */
    public function test_restore_department(): void
    {
        $department = Department::factory()->create([
            'facility_id' => $this->facility->id,
        ]);

        // Soft delete the department
        $department->delete();

        Sanctum::actingAs($this->adminUser);

        $response = $this->postJson("/api/departments/{$department->department_uuid}/restore");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Department restored successfully.',
            ]);

        // Verify restoration
        $this->assertDatabaseHas('departments', [
            'id' => $department->id,
            'deleted_at' => null,
        ]);
    }

    /**
     * Test getting departments by facility.
     *
     * @return void
     */
    public function test_get_departments_by_facility(): void
    {
        // Create departments for the facility
        Department::factory()->count(3)->create(['facility_id' => $this->facility->id]);
        
        // Create departments for another facility
        $otherFacility = Facility::factory()->create();
        Department::factory()->count(2)->create(['facility_id' => $otherFacility->id]);

        Sanctum::actingAs($this->adminUser);

        $response = $this->getJson("/api/departments/facility/{$this->facility->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Departments retrieved successfully.',
            ])
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure([
                'meta' => [
                    'facility_id',
                    'count',
                ]
            ]);
    }

    /**
     * Test getting departments by type.
     *
     * @return void
     */
    public function test_get_departments_by_type(): void
    {
        // Create emergency departments
        Department::factory()->count(2)->create([
            'facility_id' => $this->facility->id,
            'department_type' => 'emergency',
        ]);

        // Create other type departments
        Department::factory()->count(2)->create([
            'facility_id' => $this->facility->id,
            'department_type' => 'surgery',
        ]);

        Sanctum::actingAs($this->adminUser);

        $response = $this->getJson('/api/departments/type/emergency');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Departments retrieved successfully.',
            ])
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure([
                'meta' => [
                    'department_type',
                    'count',
                ]
            ]);
    }

    /**
     * Test authorization for creating department.
     *
     * @return void
     */
    public function test_unauthorized_create_department(): void
    {
        // Regular user without create permission
        Sanctum::actingAs($this->regularUser);

        $departmentData = [
            'facility_id' => $this->facility->id,
            'department_code' => 'EMERG',
            'department_name' => 'Emergency Department',
            'department_type' => 'emergency',
        ];

        $response = $this->postJson('/api/departments', $departmentData);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'You are not authorized to create departments.',
            ]);
    }

    /**
     * Test authorization for updating department.
     *
     * @return void
     */
    public function test_unauthorized_update_department(): void
    {
        $department = Department::factory()->create([
            'facility_id' => $this->facility->id,
        ]);

        // Regular user without update permission
        Sanctum::actingAs($this->regularUser);

        $response = $this->putJson("/api/departments/{$department->department_uuid}", [
            'department_name' => 'Updated Name',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'You are not authorized to update this department.',
            ]);
    }

    /**
     * Test validation for duplicate department code.
     *
     * @return void
     */
    public function test_duplicate_department_code_validation(): void
    {
        // Create a department with code 'EMERG'
        Department::factory()->create([
            'facility_id' => $this->facility->id,
            'department_code' => 'EMERG',
        ]);

        Sanctum::actingAs($this->adminUser);

        // Try to create another department with same code in same facility
        $departmentData = [
            'facility_id' => $this->facility->id,
            'department_code' => 'EMERG',
            'department_name' => 'Another Emergency Department',
            'department_type' => 'emergency',
        ];

        $response = $this->postJson('/api/departments', $departmentData);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Department code already exists in this facility.',
            ])
            ->assertJsonStructure([
                'errors' => [
                    'department_code',
                ]
            ]);
    }
}