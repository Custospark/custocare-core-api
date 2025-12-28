<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MessageControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Conversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a test user
        $this->user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
        ]);

        // Create a conversation
        $this->conversation = Conversation::factory()->create();
        
        // Add user to conversation participants
        $this->conversation->participants()->attach($this->user->id);
    }

    public function test_index_returns_unauthorized_for_guest(): void
    {
        $response = $this->getJson('/api/messages');

        $response->assertStatus(401);
    }

    public function test_index_returns_paginated_messages_for_authenticated_user(): void
    {
        Message::factory()->count(5)->create([
            'conversation_id' => $this->conversation->id,
            'sender_id' => $this->user->id,
            'sender_type' => 'staff',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/messages');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
                'meta' => [
                    'current_page',
                    'last_page',
                    'per_page',
                    'total',
                ],
            ])
            ->assertJson(['success' => true]);
    }

    public function test_store_creates_new_message(): void
    {
        $data = [
            'conversation_id' => $this->conversation->id,
            'sender_type' => 'staff',
            'sender_id' => $this->user->id,
            'message_type' => 'text',
            'content' => 'Test message content',
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/messages', $data);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'message_uuid',
                    'conversation_id',
                    'sender_type',
                    'message_type',
                    'content_hash',
                ],
            ])
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $this->conversation->id,
            'sender_id' => $this->user->id,
            'sender_type' => 'staff',
            'message_type' => 'text',
        ]);
    }

    public function test_store_returns_validation_error_for_invalid_data(): void
    {
        $data = [
            'conversation_id' => 999, // Non-existent conversation
            'sender_type' => 'invalid_type', // Invalid sender type
            'message_type' => 'text',
            'content' => 'Test',
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/messages', $data);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'success',
                'message',
                'errors',
            ])
            ->assertJson(['success' => false]);
    }

    public function test_show_returns_message(): void
    {
        $message = Message::factory()->create([
            'conversation_id' => $this->conversation->id,
            'sender_id' => $this->user->id,
            'sender_type' => 'staff',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/messages/{$message->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'message_uuid',
                    'conversation_id',
                ],
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $message->id,
                    'conversation_id' => $message->conversation_id,
                ],
            ]);
    }

    public function test_show_returns_not_found_for_invalid_id(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/messages/9999');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Message not found.',
            ]);
    }

    public function test_update_modifies_existing_message(): void
    {
        $message = Message::factory()->create([
            'conversation_id' => $this->conversation->id,
            'sender_id' => $this->user->id,
            'sender_type' => 'staff',
            'content_hash' => 'old_hash',
        ]);

        $data = [
            'content' => 'Updated content',
            'edited_by_user_id' => $this->user->id,
        ];

        $response = $this->actingAs($this->user)
            ->putJson("/api/messages/{$message->id}", $data);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data',
            ])
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('messages', [
            'id' => $message->id,
            'edited_by_user_id' => $this->user->id,
        ]);
    }

    public function test_destroy_deletes_message(): void
    {
        $message = Message::factory()->create([
            'conversation_id' => $this->conversation->id,
            'sender_id' => $this->user->id,
            'sender_type' => 'staff',
        ]);

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/messages/{$message->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Message deleted successfully.',
            ]);

        $this->assertSoftDeleted('messages', ['id' => $message->id]);
    }

    public function test_conversation_messages_returns_messages_for_conversation(): void
    {
        Message::factory()->count(3)->create([
            'conversation_id' => $this->conversation->id,
            'sender_id' => $this->user->id,
            'sender_type' => 'staff',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/conversations/{$this->conversation->id}/messages");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
                'meta' => [
                    'current_page',
                    'last_page',
                    'per_page',
                    'total',
                    'conversation_id',
                ],
            ])
            ->assertJson([
                'success' => true,
                'meta' => [
                    'conversation_id' => $this->conversation->id,
                ],
            ]);
    }

    public function test_mark_as_delivered_updates_status(): void
    {
        $message = Message::factory()->create([
            'conversation_id' => $this->conversation->id,
            'sender_id' => $this->user->id,
            'sender_type' => 'staff',
            'delivery_status' => 'sent',
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/messages/{$message->id}/mark-delivered");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Message marked as delivered.',
            ]);

        $this->assertDatabaseHas('messages', [
            'id' => $message->id,
            'delivery_status' => 'delivered',
        ]);
    }

    public function test_show_by_uuid_returns_message(): void
    {
        $message = Message::factory()->create([
            'conversation_id' => $this->conversation->id,
            'sender_id' => $this->user->id,
            'sender_type' => 'staff',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/messages/uuid/{$message->message_uuid}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'message_uuid' => $message->message_uuid,
                ],
            ]);
    }

    public function test_clinical_messages_returns_only_clinical_messages(): void
    {
        Message::factory()->create([
            'conversation_id' => $this->conversation->id,
            'sender_id' => $this->user->id,
            'sender_type' => 'staff',
            'is_clinical' => true,
        ]);

        Message::factory()->create([
            'conversation_id' => $this->conversation->id,
            'sender_id' => $this->user->id,
            'sender_type' => 'staff',
            'is_clinical' => false,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/messages/clinical');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
                'meta',
            ])
            ->assertJson(['success' => true]);
    }
}