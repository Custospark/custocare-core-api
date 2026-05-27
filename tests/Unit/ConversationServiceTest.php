<?php

namespace Tests\Unit;

use App\Models\Conversation;
use App\Models\Facility;
use App\Models\User;
use App\Repositories\Contracts\ConversationRepositoryInterface;
use App\Services\Conversation\ConversationService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Pagination\LengthAwarePaginator;
use Mockery;
use Tests\TestCase;

class ConversationServiceTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected ConversationService $service;
    protected $repositoryMock;

    protected function setUp(): void
    {
        parent::setUp();

        // Create mock repository
        $this->repositoryMock = Mockery::mock(ConversationRepositoryInterface::class);
        
        // Create service with mocked repository
        $this->service = new ConversationService($this->repositoryMock);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_can_get_all_conversations()
    {
        // Arrange
        $conversations = new Collection([
            Conversation::factory()->make(),
            Conversation::factory()->make()
        ]);

        $paginator = new LengthAwarePaginator($conversations, 2, 15, 1);

        $this->repositoryMock->shouldReceive('getAllPaginated')
            ->with([], 15)
            ->once()
            ->andReturn($paginator);

        // Act
        $result = $this->service->getAllConversations();

        // Assert
        $this->assertTrue($result['success']);
        $this->assertEquals('Conversations retrieved successfully', $result['message']);
        $this->assertInstanceOf(LengthAwarePaginator::class, $result['data']);
        $this->assertCount(2, $result['data']);
    }

    /** @test */
    public function it_handles_errors_when_getting_conversations()
    {
        // Arrange
        $this->repositoryMock->shouldReceive('getAllPaginated')
            ->andThrow(new \Exception('Database error'));

        // Act
        $result = $this->service->getAllConversations();

        // Assert
        $this->assertFalse($result['success']);
        $this->assertEquals('Failed to retrieve conversations. Please try again later.', $result['message']);
        $this->assertEmpty($result['data']);
    }

    /** @test */
    public function it_can_get_conversation_by_id()
    {
        // Arrange
        $conversation = Conversation::factory()->make(['id' => 1]);
        
        $this->repositoryMock->shouldReceive('findById')
            ->with(1)
            ->once()
            ->andReturn($conversation);

        // Act
        $result = $this->service->getConversationById(1);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertEquals('Conversation retrieved successfully', $result['message']);
        $this->assertEquals($conversation, $result['data']);
    }

    /** @test */
    public function it_returns_not_found_when_conversation_does_not_exist()
    {
        // Arrange
        $this->repositoryMock->shouldReceive('findById')
            ->with(999)
            ->once()
            ->andReturn(null);

        // Act
        $result = $this->service->getConversationById(999);

        // Assert
        $this->assertFalse($result['success']);
        $this->assertEquals('Conversation not found', $result['message']);
        $this->assertNull($result['data']);
    }

    /** @test */
    public function it_can_create_a_conversation()
    {
        // Arrange
        $facility = Facility::factory()->create();
        $user = User::factory()->create();
        
        $data = [
            'facility_id' => $facility->id,
            'conversation_type' => 'direct',
            'title' => 'Test Conversation',
            'contains_phi' => true,
            'is_emergency' => false,
        ];

        $conversation = Conversation::factory()->make(array_merge($data, [
            'id' => 1,
            'created_by_user_id' => $user->id,
            'conversation_uuid' => 'test-uuid-123'
        ]));

        $this->repositoryMock->shouldReceive('create')
            ->with(Mockery::on(function ($arg) use ($data) {
                return $arg['facility_id'] === $data['facility_id']
                    && $arg['conversation_type'] === $data['conversation_type']
                    && isset($arg['conversation_uuid']);
            }))
            ->once()
            ->andReturn($conversation);

        $this->repositoryMock->shouldReceive('addParticipant')
            ->with($conversation, $user->id, ['role' => 'admin', 'is_admin' => true])
            ->once()
            ->andReturn(true);

        // Act
        $result = $this->service->createConversation($data, $user->id);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertEquals('Conversation created successfully', $result['message']);
        $this->assertEquals($conversation, $result['data']);
    }

    /** @test */
    public function it_validates_data_when_creating_conversation()
    {
        // Arrange
        $invalidData = [
            'facility_id' => 'invalid', // Should be integer
            'conversation_type' => 'invalid_type' // Not in allowed values
        ];

        // Act
        $result = $this->service->createConversation($invalidData, 1);

        // Assert
        $this->assertFalse($result['success']);
        $this->assertEquals('Validation failed', $result['message']);
        $this->assertArrayHasKey('errors', $result);
    }

    /** @test */
    public function it_can_update_a_conversation()
    {
        // Arrange
        $conversation = Conversation::factory()->create([
            'conversation_type' => 'direct',
            'title' => 'Original Title'
        ]);

        $updateData = [
            'title' => 'Updated Title',
            'status' => 'archived'
        ];

        $this->repositoryMock->shouldReceive('findById')
            ->with($conversation->id)
            ->once()
            ->andReturn($conversation);

        $this->repositoryMock->shouldReceive('update')
            ->with($conversation, $updateData)
            ->once()
            ->andReturn(true);

        // Act
        $result = $this->service->updateConversation($conversation->id, $updateData);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertEquals('Conversation updated successfully', $result['message']);
    }

    /** @test */
    public function it_cannot_update_conversation_type_when_messages_exist()
    {
        // Arrange
        $conversation = Conversation::factory()->create([
            'conversation_type' => 'direct'
        ]);

        // Mock that conversation has messages
        $conversationMock = Mockery::mock($conversation);
        $conversationMock->shouldReceive('messages->count')->andReturn(1);
        $conversationMock->shouldReceive('getAttribute')->with('conversation_type')->andReturn('direct');

        $updateData = [
            'conversation_type' => 'group' // Trying to change type
        ];

        $this->repositoryMock->shouldReceive('findById')
            ->with($conversation->id)
            ->once()
            ->andReturn($conversationMock);

        // Act
        $result = $this->service->updateConversation($conversation->id, $updateData);

        // Assert
        $this->assertFalse($result['success']);
        $this->assertEquals('Cannot change conversation type after messages have been sent', $result['message']);
    }

    /** @test */
    public function it_can_archive_a_conversation()
    {
        // Arrange
        $conversation = Conversation::factory()->create([
            'status' => 'active'
        ]);

        $conversationMock = Mockery::mock($conversation);
        $conversationMock->shouldReceive('isArchived')->andReturn(false);
        $conversationMock->shouldReceive('refresh')->andReturnSelf();

        $this->repositoryMock->shouldReceive('findById')
            ->with($conversation->id)
            ->once()
            ->andReturn($conversationMock);

        $this->repositoryMock->shouldReceive('archive')
            ->with($conversationMock)
            ->once()
            ->andReturn(true);

        // Act
        $result = $this->service->archiveConversation($conversation->id);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertEquals('Conversation archived successfully', $result['message']);
    }

    /** @test */
    public function it_can_add_participant_to_conversation()
    {
        // Arrange
        $conversation = Conversation::factory()->create();
        $userId = 2;

        $conversationMock = Mockery::mock($conversation);
        $conversationMock->shouldReceive('isLocked')->andReturn(false);
        $conversationMock->shouldReceive('participants->where->first')->andReturn(null);

        $this->repositoryMock->shouldReceive('findById')
            ->with($conversation->id)
            ->once()
            ->andReturn($conversationMock);

        $this->repositoryMock->shouldReceive('addParticipant')
            ->with($conversationMock, $userId, [])
            ->once()
            ->andReturn(true);

        // Act
        $result = $this->service->addParticipant($conversation->id, $userId);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertEquals('Participant added successfully', $result['message']);
    }

    /** @test */
    public function it_cannot_add_participant_to_locked_conversation()
    {
        // Arrange
        $conversation = Conversation::factory()->create();
        $userId = 2;

        $conversationMock = Mockery::mock($conversation);
        $conversationMock->shouldReceive('isLocked')->andReturn(true);

        $this->repositoryMock->shouldReceive('findById')
            ->with($conversation->id)
            ->once()
            ->andReturn($conversationMock);

        // Act
        $result = $this->service->addParticipant($conversation->id, $userId);

        // Assert
        $this->assertFalse($result['success']);
        $this->assertEquals('Cannot add participants to a locked conversation', $result['message']);
    }

    /** @test */
    public function it_cannot_add_existing_participant()
    {
        // Arrange
        $conversation = Conversation::factory()->create();
        $userId = 2;

        $conversationMock = Mockery::mock($conversation);
        $conversationMock->shouldReceive('isLocked')->andReturn(false);
        $conversationMock->shouldReceive('participants->where->first')->andReturn((object) ['id' => $userId]);

        $this->repositoryMock->shouldReceive('findById')
            ->with($conversation->id)
            ->once()
            ->andReturn($conversationMock);

        // Act
        $result = $this->service->addParticipant($conversation->id, $userId);

        // Assert
        $this->assertFalse($result['success']);
        $this->assertEquals('User is already a participant in this conversation', $result['message']);
    }

    /** @test */
    public function it_validates_conversation_data()
    {
        // Arrange
        $validData = [
            'facility_id' => 1,
            'conversation_type' => 'direct',
            'title' => 'Valid Conversation',
            'contains_phi' => true,
            'is_emergency' => false,
            'status' => 'active'
        ];

        // Act
        $result = $this->service->validateConversationData($validData);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('validated_data', $result);
        $this->assertEquals($validData['facility_id'], $result['validated_data']['facility_id']);
    }

    /** @test */
    public function it_returns_validation_errors_for_invalid_data()
    {
        // Arrange
        $invalidData = [
            'facility_id' => 'not-an-integer',
            'conversation_type' => 'invalid_type',
            'title' => str_repeat('a', 256) // Too long
        ];

        // Act
        $result = $this->service->validateConversationData($invalidData);

        // Assert - In this case, the service throws ValidationException
        // which is caught and returns an error array
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        
        $this->service->validateConversationData($invalidData);
    }
}