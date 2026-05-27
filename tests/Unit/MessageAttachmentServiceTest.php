<?php

namespace Tests\Unit;

use App\Models\MessageAttachment;
use App\Repositories\Contracts\MessageAttachmentRepositoryInterface;
use App\Services\MessageAttachment\MessageAttachmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Mockery;

class MessageAttachmentServiceTest extends TestCase
{
    use RefreshDatabase;

    private MessageAttachmentService $service;
    private $repositoryMock;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->repositoryMock = Mockery::mock(MessageAttachmentRepositoryInterface::class);
        $this->service = new MessageAttachmentService($this->repositoryMock);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_can_get_all_attachments(): void
    {
        $paginatedData = MessageAttachment::factory()->count(10)->make();
        
        $this->repositoryMock->shouldReceive('getAll')
            ->with(15)
            ->once()
            ->andReturn($paginatedData);
        
        $result = $this->service->getAllAttachments();
        
        $this->assertTrue($result['success']);
        $this->assertEquals('Attachments retrieved successfully', $result['message']);
        $this->assertSame($paginatedData, $result['data']);
    }

    /** @test */
    public function it_handles_error_when_getting_all_attachments(): void
    {
        $this->repositoryMock->shouldReceive('getAll')
            ->andThrow(new \Exception('Database error'));
        
        $result = $this->service->getAllAttachments();
        
        $this->assertFalse($result['success']);
        $this->assertEquals('Failed to retrieve attachments', $result['message']);
        $this->assertArrayHasKey('errors', $result);
    }

    /** @test */
    public function it_can_get_attachment_by_id(): void
    {
        $attachment = MessageAttachment::factory()->make(['id' => 1]);
        
        $this->repositoryMock->shouldReceive('findById')
            ->with(1)
            ->once()
            ->andReturn($attachment);
        
        $result = $this->service->getAttachmentById(1);
        
        $this->assertTrue($result['success']);
        $this->assertEquals('Attachment retrieved successfully', $result['message']);
        $this->assertSame($attachment, $result['data']);
    }

    /** @test */
    public function it_returns_error_when_attachment_not_found(): void
    {
        $this->repositoryMock->shouldReceive('findById')
            ->with(999)
            ->once()
            ->andReturn(null);
        
        $result = $this->service->getAttachmentById(999);
        
        $this->assertFalse($result['success']);
        $this->assertEquals('Attachment not found', $result['message']);
        $this->assertArrayHasKey('errors', $result);
    }

    /** @test */
    public function it_can_create_attachment(): void
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
            'checksum' => 'a1b2c3d4e5f6g7h8i9j0' . str_repeat('0', 44), // SHA256 length
        ];
        
        $attachment = MessageAttachment::factory()->make($attachmentData);
        
        $this->repositoryMock->shouldReceive('checksumExists')
            ->with($attachmentData['checksum'], null)
            ->once()
            ->andReturn(false);
        
        $this->repositoryMock->shouldReceive('create')
            ->with($attachmentData)
            ->once()
            ->andReturn($attachment);
        
        $result = $this->service->createAttachment($attachmentData);
        
        $this->assertTrue($result['success']);
        $this->assertEquals('Attachment created successfully', $result['message']);
        $this->assertSame($attachment, $result['data']);
    }

    /** @test */
    public function it_validates_attachment_type_during_creation(): void
    {
        $invalidData = [
            'message_id' => 1,
            'attachment_type' => 'invalid_type',
            'file_name' => 'test.jpg',
            'mime_type' => 'image/jpeg',
            'file_size_bytes' => 1024,
            'storage_disk' => 'local',
            'storage_path' => 'path/to/file.jpg',
            'contains_phi' => true,
            'checksum' => 'a1b2c3d4e5f6g7h8i9j0' . str_repeat('0', 44),
        ];
        
        $result = $this->service->createAttachment($invalidData);
        
        $this->assertFalse($result['success']);
        $this->assertEquals('Invalid attachment type', $result['message']);
        $this->assertArrayHasKey('errors', $result);
    }

    /** @test */
    public function it_prevents_duplicate_files(): void
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
        
        $existingAttachment = MessageAttachment::factory()->make();
        
        $this->repositoryMock->shouldReceive('checksumExists')
            ->with($attachmentData['checksum'], null)
            ->once()
            ->andReturn(true);
        
        $result = $this->service->createAttachment($attachmentData);
        
        $this->assertFalse($result['success']);
        $this->assertEquals('Duplicate file detected', $result['message']);
        $this->assertArrayHasKey('duplicate', $result);
    }

    /** @test */
    public function it_can_process_file_upload(): void
    {
        Storage::fake('local');
        
        $file = UploadedFile::fake()->image('test.jpg')->size(1024);
        
        $this->repositoryMock->shouldReceive('checksumExists')
            ->once()
            ->andReturn(false);
        
        $this->repositoryMock->shouldReceive('create')
            ->once()
            ->andReturn(MessageAttachment::factory()->make());
        
        $result = $this->service->processFileUpload(
            $file,
            1,
            'image',
            true
        );
        
        $this->assertTrue($result['success']);
        $this->assertEquals('File uploaded and processed successfully', $result['message']);
        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('file_info', $result);
    }

    /** @test */
    public function it_validates_file_size_during_upload(): void
    {
        Storage::fake('local');
        
        $file = UploadedFile::fake()->image('test.jpg')->size(10485761); // 10MB + 1 byte
        
        $result = $this->service->processFileUpload(
            $file,
            1,
            'image',
            true
        );
        
        $this->assertFalse($result['success']);
        $this->assertEquals('File size exceeds maximum limit', $result['message']);
        $this->assertArrayHasKey('errors', $result);
    }

    /** @test */
    public function it_validates_attachment_type_during_upload(): void
    {
        Storage::fake('local');
        
        $file = UploadedFile::fake()->image('test.jpg')->size(1024);
        
        $result = $this->service->processFileUpload(
            $file,
            1,
            'invalid_type',
            true
        );
        
        $this->assertFalse($result['success']);
        $this->assertEquals('Invalid attachment type', $result['message']);
        $this->assertArrayHasKey('errors', $result);
    }

    /** @test */
    public function it_can_delete_attachment(): void
    {
        $attachment = MessageAttachment::factory()->make(['id' => 1]);
        
        $this->repositoryMock->shouldReceive('findById')
            ->with(1)
            ->once()
            ->andReturn($attachment);
        
        $this->repositoryMock->shouldReceive('delete')
            ->with(1)
            ->once()
            ->andReturn(true);
        
        $result = $this->service->deleteAttachment(1);
        
        $this->assertTrue($result['success']);
        $this->assertEquals('Attachment deleted successfully', $result['message']);
    }

    /** @test */
    public function it_returns_error_when_deleting_nonexistent_attachment(): void
    {
        $this->repositoryMock->shouldReceive('findById')
            ->with(999)
            ->once()
            ->andReturn(null);
        
        $result = $this->service->deleteAttachment(999);
        
        $this->assertFalse($result['success']);
        $this->assertEquals('Attachment not found', $result['message']);
        $this->assertArrayHasKey('errors', $result);
    }

    /** @test */
    public function it_can_validate_attachment_type(): void
    {
        $validTypes = ['image', 'pdf', 'lab_result', 'radiology_image', 'audio', 'video', 'other'];
        
        foreach ($validTypes as $type) {
            $this->assertTrue($this->service->validateAttachmentType($type));
        }
        
        $this->assertFalse($this->service->validateAttachmentType('invalid_type'));
    }

    /** @test */
    public function it_can_check_file_duplicate(): void
    {
        $checksum = 'a1b2c3d4e5f6g7h8i9j0' . str_repeat('0', 44);
        
        $this->repositoryMock->shouldReceive('checksumExists')
            ->with($checksum, null)
            ->once()
            ->andReturn(true);
        
        $result = $this->service->checkFileDuplicate($checksum);
        
        $this->assertTrue($result['exists']);
    }
}