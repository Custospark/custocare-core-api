<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\ClinicalDocument;
use App\Repositories\Contracts\ClinicalDocumentRepositoryInterface;
use App\Services\ClinicalDocument\ClinicalDocumentService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mockery;

class ClinicalDocumentServiceTest extends TestCase
{
    private $repositoryMock;
    private $service;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->repositoryMock = Mockery::mock(ClinicalDocumentRepositoryInterface::class);
        $this->service = new ClinicalDocumentService($this->repositoryMock);
        
        Storage::fake('local');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_retrieves_all_documents_successfully()
    {
        $documents = ClinicalDocument::factory()->count(5)->make();
        
        $this->repositoryMock->shouldReceive('getAll')
            ->with([], 20)
            ->once()
            ->andReturn($documents);
        
        $result = $this->service->getAllDocuments();
        
        $this->assertTrue($result['success']);
        $this->assertEquals('Clinical documents retrieved successfully', $result['message']);
        $this->assertCount(5, $result['data']);
    }

    /** @test */
    public function it_handles_error_when_retrieving_documents_fails()
    {
        $this->repositoryMock->shouldReceive('getAll')
            ->andThrow(new \Exception('Database error'));
        
        $result = $this->service->getAllDocuments();
        
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Failed to retrieve', $result['message']);
        $this->assertEmpty($result['data']);
    }

    /** @test */
    public function it_retrieves_document_by_id_successfully()
    {
        $document = ClinicalDocument::factory()->make(['id' => 1]);
        
        $this->repositoryMock->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($document);
        
        $result = $this->service->getDocumentById(1);
        
        $this->assertTrue($result['success']);
        $this->assertEquals($document, $result['data']);
    }

    /** @test */
    public function it_returns_not_found_when_document_does_not_exist()
    {
        $this->repositoryMock->shouldReceive('find')
            ->with(999)
            ->once()
            ->andReturn(null);
        
        $result = $this->service->getDocumentById(999);
        
        $this->assertFalse($result['success']);
        $this->assertEquals('Clinical document not found', $result['message']);
    }

    /** @test */
    public function it_creates_document_successfully()
    {
        $file = UploadedFile::fake()->create('document.pdf', 1024);
        $data = [
            'patient_id' => 1,
            'facility_id' => 1,
            'document_type' => 'lab_report',
            'document_name' => 'Test Document',
        ];
        
        $this->repositoryMock->shouldReceive('fileHashExists')
            ->once()
            ->andReturn(false);
        
        $this->repositoryMock->shouldReceive('create')
            ->once()
            ->andReturn(ClinicalDocument::factory()->make($data));
        
        $result = $this->service->createDocument($data, $file);
        
        $this->assertTrue($result['success']);
        $this->assertEquals('Clinical document uploaded successfully', $result['message']);
    }

    /** @test */
    public function it_rejects_duplicate_file_uploads()
    {
        $file = UploadedFile::fake()->create('document.pdf', 1024);
        $data = ['patient_id' => 1];
        
        $this->repositoryMock->shouldReceive('fileHashExists')
            ->once()
            ->andReturn(true);
        
        $result = $this->service->createDocument($data, $file);
        
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('already been uploaded', $result['message']);
    }

    /** @test */
    public function it_updates_document_successfully()
    {
        $document = ClinicalDocument::factory()->make(['id' => 1]);
        $data = ['document_name' => 'Updated Name'];
        
        $this->repositoryMock->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($document);
        
        $this->repositoryMock->shouldReceive('update')
            ->with(1, $data)
            ->once()
            ->andReturn($document);
        
        $result = $this->service->updateDocument(1, $data);
        
        $this->assertTrue($result['success']);
        $this->assertEquals($document, $result['data']);
    }

    /** @test */
    public function it_validates_status_during_update()
    {
        $document = ClinicalDocument::factory()->make(['id' => 1]);
        $data = ['status' => 'invalid_status'];
        
        $this->repositoryMock->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($document);
        
        $result = $this->service->updateDocument(1, $data);
        
        $this->assertFalse($result['success']);
        $this->assertEquals('Invalid document status', $result['message']);
    }

    /** @test */
    public function it_deletes_document_by_updating_status()
    {
        $document = ClinicalDocument::factory()->make(['id' => 1]);
        
        $this->repositoryMock->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($document);
        
        $this->repositoryMock->shouldReceive('updateStatus')
            ->with(1, ClinicalDocument::STATUS_ENTERED_IN_ERROR)
            ->once()
            ->andReturn(true);
        
        $result = $this->service->deleteDocument(1);
        
        $this->assertTrue($result['success']);
        $this->assertStringContainsString('entered in error', $result['message']);
    }

    /** @test */
    public function it_verifies_document_integrity_successfully()
    {
        Storage::fake();
        Storage::put('test.pdf', 'content');
        
        $document = ClinicalDocument::factory()->make([
            'id' => 1,
            'file_storage_path' => 'test.pdf',
            'file_hash' => hash('sha256', 'content'),
        ]);
        
        $this->repositoryMock->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($document);
        
        $result = $this->service->verifyDocumentIntegrity(1);
        
        $this->assertTrue($result['success']);
        $this->assertTrue($result['data']['integrity_verified']);
    }
}