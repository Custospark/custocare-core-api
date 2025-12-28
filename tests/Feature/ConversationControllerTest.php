<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Facility;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ConversationControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected User $user;
    protected Facility $facility;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a test user and facility
        $this->facility = Facility::factory()->create();
        $this->user = User::factory()->create([
            'facility_id' => $this->facility->id
        ]);

        Sanctum::actingAs($this->user);
    }

    /** @test */
    public function it_can_list_conversations()
    {
        Conversation::factory()->count(5)->create([
            'facility_id' => $this->facility->id
        ]);

        $response = $this->getJson('/api/conversations');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'conversation_uuid',
                        'facility_id',
                        'conversation_type',
                        'status',
                        'created_at',
                        'updated_at'
                    ]
                ],
                'meta' => [
                    'total',
                    'per_page',
                    'current_page',
                    'last_page'
                ]
            ])
            ->assertJson(['success' => true]);
    }

    /** @test */
    public function it_can_create_a_conversation()
    {
        $data = [
            'facility_id' => $this->facility->id,
            'conversation_type' => 'direct',
            'title' => 'Test Conversation',
            'contains_phi' => true,
            'is_emergency' => false,
            'status' => 'active'
        ];

        $response = $this->postJson('/api/conversations', $data);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'conversation_uuid',
                    'facility_id',
                    'conversation_type',
                    'title',
                    'contains_phi',
                    'is_emergency',
                    'status',
                    'created_by_user_id',
                    'created_at',
                    'updated_at'
                ]
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Conversation created successfully',
                'data' => [
                    'facility_id' => $this->facility->id,
                    'conversation_type' => 'direct',
                    'title' => 'Test Conversation',
                    'contains_phi' => true,
                    'is_emergency' => false,
                    'status' => 'active'
                ]
            ]);

        $this->assertDatabaseHas('conversations', [
            'facility_id' => $this->facility->id,
            'conversation_type' => 'direct',
            'title' => 'Test Conversation'
        ]);
    }

    /** @test */
    public function it_validates_required_fields_when_creating_conversation()
    {
        $response = $this->postJson('/api/conversations', []);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'success',
                'message',
                'errors' => [
                    'facility_id',
                    'conversation_type'
                ]
            ])
            ->assertJson([
                'success' => false,
                'message' => 'Validation failed'
            ]);
    }

    /** @test */
    public function it_can_retrieve_a_specific_conversation()
    {
        $conversation = Conversation::factory()->create([
            'facility_id' => $this->facility->id,
            'created_by_user_id' => $this->user->id
        ]);

        // Add user as participant
        $conversation->participants()->attach($this->user->id, [
            'role' => 'admin',
            'is_admin' => true,
            'joined_at' => now()
        ]);

        $response = $this->getJson("/api/conversations/{$conversation->conversation_uuid}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'conversation_uuid',
                    'facility_id',
                    'conversation_type',
                    'status',
                    'created_at',
                    'updated_at'
                ]
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Conversation retrieved successfully',
                'data' => [
                    'id' => $conversation->id,
                    'conversation_uuid' => $conversation->conversation_uuid
                ]
            ]);
    }

    /** @test */
    public function it_returns_404_when_conversation_not_found()
    {
        $response = $this->getJson('/api/conversations/non-existent-uuid');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Conversation not found'
            ]);
    }

    /** @test */
    public function it_can_update_a_conversation()
    {
        $conversation = Conversation::factory()->create([
            'facility_id' => $this->facility->id,
            'created_by_user_id' => $this->user->id,
            'title' => 'Original Title'
        ]);

        // Add user as participant with admin role
        $conversation->participants()->attach($this->user->id, [
            'role' => 'admin',
            'is_admin' => true,
            'joined_at' => now()
        ]);

        $updateData = [
            'title' => 'Updated Title',
            'status' => 'archived'
        ];

        $response = $this->putJson("/api/conversations/{$conversation->conversation_uuid}", $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Conversation updated successfully',
                'data' => [
                    'title' => 'Updated Title',
                    'status' => 'archived'
                ]
            ]);

        $this->assertDatabaseHas('conversations', [
            'id' => $conversation->id,
            'title' => 'Updated Title',
            'status' => 'archived'
        ]);
    }

    /** @test */
    public function it_can_archive_a_conversation()
    {
        $conversation = Conversation::factory()->create([
            'facility_id' => $this->facility->id,
            'created_by_user_id' => $this->user->id,
            'status' => 'active'
        ]);

        // Add user as participant with admin role
        $conversation->participants()->attach($this->user->id, [
            'role' => 'admin',
            'is_admin' => true,
            'joined_at' => now()
        ]);

        $response = $this->postJson("/api/conversations/{$conversation->conversation_uuid}/archive");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Conversation archived successfully'
            ]);

        $this->assertDatabaseHas('conversations', [
            'id' => $conversation->id,
            'status' => 'archived'
        ]);
    }

    /** @test */
    public function it_can_delete_a_conversation()
    {
        $conversation = Conversation::factory()->create([
            'facility_id' => $this->facility->id,
            'created_by_user_id' => $this->user->id
        ]);

        // User needs delete permission
        $this->user->givePermissionTo('delete conversations');

        $response = $this->deleteJson("/api/conversations/{$conversation->conversation_uuid}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Conversation deleted successfully'
            ]);

        $this->assertSoftDeleted('conversations', ['id' => $conversation->id]);
    }

    /** @test */
    public function it_can_filter_conversations_by_type()
    {
        Conversation::factory()->create([
            'facility_id' => $this->facility->id,
            'conversation_type' => 'direct'
        ]);

        Conversation::factory()->create([
            'facility_id' => $this->facility->id,
            'conversation_type' => 'group'
        ]);

        $response = $this->getJson('/api/conversations?conversation_type=direct');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.conversation_type', 'direct');
    }

    /** @test */
    public function it_can_add_participant_to_conversation()
    {
        $conversation = Conversation::factory()->create([
            'facility_id' => $this->facility->id,
            'created_by_user_id' => $this->user->id,
            'conversation_type' => 'group'
        ]);

        // Add creator as admin participant
        $conversation->participants()->attach($this->user->id, [
            'role' => 'admin',
            'is_admin' => true,
            'joined_at' => now()
        ]);

        $otherUser = User::factory()->create(['facility_id' => $this->facility->id]);

        $response = $this->postJson("/api/conversations/{$conversation->conversation_uuid}/participants", [
            'user_id' => $otherUser->id,
            'role' => 'participant'
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Participant added successfully'
            ]);

        $this->assertDatabaseHas('conversation_participants', [
            'conversation_id' => $conversation->id,
            'user_id' => $otherUser->id,
            'role' => 'participant'
        ]);
    }

    /** @test */
    public function it_handles_internal_server_errors_gracefully()
    {
        // Mock a service failure
        $this->mock(\App\Services\Contracts\ConversationServiceInterface::class, function ($mock) {
            $mock->shouldReceive('getAllConversations')->andThrow(new \Exception('Service failure'));
        });

        $response = $this->getJson('/api/conversations');

        $response->assertStatus(500)
            ->assertJson([
                'success' => false,
                'message' => 'An unexpected error occurred. Please try again later.'
            ]);
    }
}