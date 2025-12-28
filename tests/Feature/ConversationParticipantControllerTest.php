<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ConversationParticipantControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Conversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->conversation = Conversation::factory()->create();
        
        Sanctum::actingAs($this->user);
    }

    /** @test */
    public function it_can_list_conversation_participants()
    {
        ConversationParticipant::factory()->count(3)->create([
            'conversation_id' => $this->conversation->id
        ]);

        $response = $this->getJson('/api/conversation-participants');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'success',
                     'data' => [
                         '*' => [
                             'id',
                             'conversation_id',
                             'participant_type',
                             'participant_id',
                             'role',
                             'is_active'
                         ]
                     ],
                     'meta'
                 ]);
    }

    /** @test */
    public function it_can_filter_participants_by_conversation()
    {
        ConversationParticipant::factory()->count(2)->create([
            'conversation_id' => $this->conversation->id
        ]);

        ConversationParticipant::factory()->create([
            'conversation_id' => Conversation::factory()->create()->id
        ]);

        $response = $this->getJson("/api/conversation-participants?conversation_id={$this->conversation->id}");

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
    }

    /** @test */
    public function it_can_create_a_conversation_participant()
    {
        $data = [
            'conversation_id' => $this->conversation->id,
            'participant_type' => 'staff',
            'participant_id' => $this->user->id,
            'role' => 'member'
        ];

        $response = $this->postJson('/api/conversation-participants', $data);

        $response->assertStatus(201)
                 ->assertJson([
                     'success' => true,
                     'message' => 'Conversation participant added successfully.'
                 ])
                 ->assertJsonStructure([
                     'data' => [
                         'id',
                         'conversation_id',
                         'participant_type',
                         'participant_id',
                         'role'
                     ]
                 ]);

        $this->assertDatabaseHas('conversation_participants', [
            'conversation_id' => $this->conversation->id,
            'participant_id' => $this->user->id,
            'participant_type' => 'staff'
        ]);
    }

    /** @test */
    public function it_validates_required_fields_when_creating_participant()
    {
        $response = $this->postJson('/api/conversation-participants', []);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors([
                     'conversation_id',
                     'participant_type',
                     'participant_id'
                 ]);
    }

    /** @test */
    public function it_cannot_create_duplicate_participant()
    {
        ConversationParticipant::factory()->create([
            'conversation_id' => $this->conversation->id,
            'participant_type' => 'staff',
            'participant_id' => $this->user->id
        ]);

        $data = [
            'conversation_id' => $this->conversation->id,
            'participant_type' => 'staff',
            'participant_id' => $this->user->id,
            'role' => 'member'
        ];

        $response = $this->postJson('/api/conversation-participants', $data);

        $response->assertStatus(409)
                 ->assertJson([
                     'success' => false
                 ]);
    }

    /** @test */
    public function it_can_show_a_conversation_participant()
    {
        $participant = ConversationParticipant::factory()->create([
            'conversation_id' => $this->conversation->id
        ]);

        $response = $this->getJson("/api/conversation-participants/{$participant->id}");

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'data' => [
                         'id' => $participant->id,
                         'conversation_id' => $participant->conversation_id
                     ]
                 ]);
    }

    /** @test */
    public function it_returns_404_when_participant_not_found()
    {
        $response = $this->getJson('/api/conversation-participants/999');

        $response->assertStatus(404)
                 ->assertJson([
                     'success' => false,
                     'message' => 'Conversation participant not found.'
                 ]);
    }

    /** @test */
    public function it_can_update_a_conversation_participant()
    {
        $participant = ConversationParticipant::factory()->create([
            'conversation_id' => $this->conversation->id,
            'is_muted' => false
        ]);

        $data = [
            'is_muted' => true,
            'role' => 'moderator'
        ];

        $response = $this->putJson("/api/conversation-participants/{$participant->id}", $data);

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'message' => 'Conversation participant updated successfully.'
                 ]);

        $this->assertDatabaseHas('conversation_participants', [
            'id' => $participant->id,
            'is_muted' => true,
            'role' => 'moderator'
        ]);
    }

    /** @test */
    public function it_can_delete_a_conversation_participant()
    {
        $participant = ConversationParticipant::factory()->create([
            'conversation_id' => $this->conversation->id,
            'role' => 'member'
        ]);

        $response = $this->deleteJson("/api/conversation-participants/{$participant->id}");

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'message' => 'Conversation participant removed successfully.'
                 ]);

        $this->assertDatabaseMissing('conversation_participants', [
            'id' => $participant->id
        ]);
    }

    /** @test */
    public function it_can_mute_a_participant()
    {
        $participant = ConversationParticipant::factory()->create([
            'conversation_id' => $this->conversation->id,
            'is_muted' => false
        ]);

        $response = $this->postJson("/api/conversation-participants/{$participant->id}/mute");

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'message' => 'Participant muted successfully.'
                 ]);

        $this->assertDatabaseHas('conversation_participants', [
            'id' => $participant->id,
            'is_muted' => true
        ]);
    }

    /** @test */
    public function it_can_unmute_a_participant()
    {
        $participant = ConversationParticipant::factory()->create([
            'conversation_id' => $this->conversation->id,
            'is_muted' => true
        ]);

        $response = $this->postJson("/api/conversation-participants/{$participant->id}/unmute");

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'message' => 'Participant unmuted successfully.'
                 ]);

        $this->assertDatabaseHas('conversation_participants', [
            'id' => $participant->id,
            'is_muted' => false
        ]);
    }

    /** @test */
    public function it_can_update_participant_role()
    {
        $participant = ConversationParticipant::factory()->create([
            'conversation_id' => $this->conversation->id,
            'role' => 'member'
        ]);

        $response = $this->putJson("/api/conversation-participants/{$participant->id}/role", [
            'role' => 'moderator'
        ]);

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'message' => 'Participant role updated successfully.'
                 ]);

        $this->assertDatabaseHas('conversation_participants', [
            'id' => $participant->id,
            'role' => 'moderator'
        ]);
    }
}