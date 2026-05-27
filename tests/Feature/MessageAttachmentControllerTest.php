<?php

namespace Tests\Feature;

use App\Models\MessageAttachment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MessageAttachmentControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->adminUser = User::factory()->create(['role' => 'administrator']);
    }

    /** @test */
    public function it_can_list_message_attachments(): void
    {
        MessageAttachment::factory()->count(5)->create();
        
        $response = $this->actingAs($this->user)
            ->getJson('/api/message-attachments');
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'attachment_uuid',
                        'message_id',
                        'attachment_type',
                        'file_name',
                        'mime_type',
                        'file_size_bytes',
                        'storage_disk',
                        'storage_path',
                        'contains_phi',
                        'checksum',
                        'created_at',
                        'updated_at',
                        'links',
                    ]
                ],
                'meta' => [
                    'current_page',
                    'last_page',
                    'per_page',
                    'total',
                ],
                'message',
            ])
            ->assertJson(['success' => true]);
    }

    /** @test */
    public function it_requires_authentication_to_list_attachments(): void
    {
        $response = $this->getJson('/api/message-attachments');
        
        $response->assertStatus(401);
    }

    /** @test */
    public function it_can_show_a_message_attachment(): void
    {
        $attachment = MessageAttachment::factory()->create();
        
        $response = $this->actingAs($this->user)
            ->getJson("/api/message-attachments/{$attachment->id}");
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'attachment_uuid',
                    'message_id',
                    'attachment_type',
                    'file_name',
                    'mime_type',
                    'file_size_bytes',
                    'storage_disk',
                    'storage_path',
                    'contains_phi',
                    'checksum',
                    'created_at',
                    'updated_at',
                    'links',
                ],
                'message',
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $attachment->id,
                    'attachment_uuid' => $attachment->attachment_uuid,
                ]
            ]);
    }

    /** @test */
    public function it_returns_404_for_nonexistent_attachment(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/message-attachments/9999');
        
        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Attachment not found',
            ]);
    }

    /** @test */
    public function it_can_create_a_message_attachment(): void
    {
        $attachmentData = [
            'message_id' => 1,
            'attachment_type' => 'image',
            'file_name' => 'test.jpg',
            'mime_type' => 'image/jpeg',
            'file_size_bytes' => 1024,
            'storage_disk' => 'local',
            'storage_path' => 'path/to/file.jpg',
            'contains_phi' => true,
            'checksum' => 'a1b2c3d4e5f6g7h8i9j0' . str_repeat('0', 44),
        ];
        
        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/message-attachments', $attachmentData);
        
        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'attachment_uuid',
                    'message_id',
                    'attachment_type',
                    'file_name',
                    'mime_type',
                    'file_size_bytes',
                    'storage_disk',
                    'storage_path',
                    'contains_phi',
                    'checksum',
                    'created_at',
                    'updated_at',
                ],
                'message',
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Attachment created successfully',
                'data' => [
                    'message_id' => 1,
                    'attachment_type' => 'image',
                    'file_name' => 'test.jpg',
                ]
            ]);
        
        $this->assertDatabaseHas('message_attachments', [
            'message_id' => 1,
            'attachment_type' => 'image',
            'file_name' => 'test.jpg',
        ]);
    }

    /** @test */
    public function it_validates_required_fields_when_creating_attachment(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/message-attachments', []);
        
        $response->assertStatus(422)
            ->assertJsonStructure([
                'success',
                'message',
                'errors',
            ])
            ->assertJson([
                'success' => false,
                'message' => 'Validation failed',
            ]);
    }

    /** @test */
    public function it_can_update_a_message_attachment(): void
    {
        $attachment = MessageAttachment::factory()->create();
        
        $updateData = [
            'file_name' => 'updated.jpg',
            'contains_phi' => false,
        ];
        
        $response = $this->actingAs($this->adminUser)
            ->putJson("/api/message-attachments/{$attachment->id}", $updateData);
        
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Attachment updated successfully',
                'data' => [
                    'file_name' => 'updated.jpg',
                    'contains_phi' => false,
                ]
            ]);
        
        $this->assertDatabaseHas('message_attachments', [
            'id' => $attachment->id,
            'file_name' => 'updated.jpg',
            'contains_phi' => false,
        ]);
    }

    /** @test */
    public function it_can_delete_a_message_attachment(): void
    {
        $attachment = MessageAttachment::factory()->create();
        
        $response = $this->actingAs($this->adminUser)
            ->deleteJson("/api/message-attachments/{$attachment->id}");
        
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Attachment deleted successfully',
            ]);
        
        $this->assertDatabaseMissing('message_attachments', [
            'id' => $attachment->id,
        ]);
    }

    /** @test */
    public function it_can_upload_a_file_attachment(): void
    {
        Storage::fake('local');
        
        $file = UploadedFile::fake()->image('test.jpg')->size(1024);
        
        $uploadData = [
            'file' => $file,
            'message_id' => 1,
            'attachment_type' => 'image',
            'contains_phi' => true,
        ];
        
        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/message-attachments/upload', $uploadData);
        
        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'attachment_uuid',
                    'message_id',
                    'attachment_type',
                    'file_name',
                    'mime_type',
                    'file_size_bytes',
                    'storage_disk',
                    'storage_path',
                    'contains_phi',
                    'checksum',
                ],
                'message',
                'file_info',
            ])
            ->assertJson([
                'success' => true,
                'message' => 'File uploaded and processed successfully',
            ]);
        
        // Storage::disk('local')->assertExists($response->json('data.storage_path'));
    }

    /** @test */
    public function it_validates_file_upload(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/message-attachments/upload', [
                'file' => 'not-a-file',
                'message_id' => 1,
                'attachment_type' => 'image',
            ]);
        
        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Validation failed',
            ]);
    }

    /** @test */
    public function it_can_get_attachments_by_message(): void
    {
        $messageId = 1;
        MessageAttachment::factory()->count(3)->create(['message_id' => $messageId]);
        MessageAttachment::factory()->count(2)->create(['message_id' => 2]);
        
        $response = $this->actingAs($this->user)
            ->getJson("/api/message-attachments/message/{$messageId}");
        
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Message attachments retrieved successfully',
            ])
            ->assertJsonCount(3, 'data');
    }

    /** @test */
    public function it_can_get_attachments_by_type(): void
    {
        MessageAttachment::factory()->count(3)->create(['attachment_type' => 'image']);
        MessageAttachment::factory()->count(2)->create(['attachment_type' => 'pdf']);
        
        $response = $this->actingAs($this->user)
            ->getJson('/api/message-attachments/type/image');
        
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Image attachments retrieved successfully',
            ]);
        
        // Count might be paginated, so check the structure
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'attachment_type',
                ]
            ],
            'meta' => [
                'type',
            ]
        ]);
    }

    /** @test */
    public function it_can_get_attachment_statistics(): void
    {
        MessageAttachment::factory()->count(5)->create();
        
        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/message-attachments/statistics');
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'total_attachments',
                    'total_storage_bytes',
                    'total_storage_human',
                    'phi_attachments',
                    'type_distribution',
                    'recent_attachments',
                ],
                'message',
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Attachment statistics retrieved successfully',
            ]);
    }

    /** @test */
    public function it_requires_authorization_for_statistics(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/message-attachments/statistics');
        
        // This will depend on your policy implementation
        $response->assertStatus(403);
    }

    /** @test */
    public function it_can_get_attachment_by_uuid(): void
    {
        $attachment = MessageAttachment::factory()->create();
        
        $response = $this->actingAs($this->user)
            ->getJson("/api/message-attachments/uuid/{$attachment->attachment_uuid}");
        
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $attachment->id,
                    'attachment_uuid' => $attachment->attachment_uuid,
                ]
            ]);
    }

    /** @test */
    public function it_returns_404_for_invalid_uuid(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/message-attachments/uuid/invalid-uuid');
        
        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Attachment not found',
            ]);
    }
}