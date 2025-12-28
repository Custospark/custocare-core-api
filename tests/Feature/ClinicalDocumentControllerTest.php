<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\ClinicalDocument;
use App\Models\Patient;
use App\Models\Facility;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ClinicalDocumentControllerTest extends TestCase
{
    private $user;
    private $patient;
    private $facility;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->facility = Facility::factory()->create();
        $this->patient = Patient::factory()->create(['facility_id' => $this->facility->id]);
        
        // Create a clinician user
        $this->user = User::factory()->create([
            'facility_id' => $this->facility->id,
        ]);
        $this->user->assignRole('clinician');
        
        Storage::fake('local');
    }

    /** @test */
    public function it_can_list_clinical_documents()
    {
        ClinicalDocument::factory()->count(3)->create([
            'patient_id' => $this->patient->id,
            'facility_id' => $this->facility->id,
        ]);
        
        $response = $this->actingAs($this->user)
            ->getJson('/api/clinical-documents');
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'document_uuid',
                        'patient_id',
                        'document_type',
                        'document_name',
                    ]
                ],
                'meta'
            ]);
    }

    /** @test */
    public function it_can_store_a_new_clinical_document()
    {
        $file = UploadedFile::fake()->create('document.pdf', 1024, 'application/pdf');
        
        $data = [
            'patient_id' => $this->patient->id,
            'facility_id' => $this->facility->id,
            'document_type' => 'lab_report',
            'document_name' => 'Test Lab Report',
            'document_description' => 'This is a test lab report',
            'document_file' => $file,
        ];
        
        $response = $this->actingAs($this->user)
            ->postJson('/api/clinical-documents', $data);
        
        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Clinical document uploaded successfully'
            ])
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'document_uuid',
                    'document_name',
                    'file_mime_type',
                    'file_size_bytes',
                ]
            ]);
        
        $this->assertDatabaseHas('clinical_documents', [
            'patient_id' => $this->patient->id,
            'document_name' => 'Test Lab Report',
        ]);
    }

    /** @test */
    public function it_validates_required_fields_when_storing()
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/clinical-documents', []);
        
        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'patient_id',
                'facility_id',
                'document_type',
                'document_name',
                'document_file'
            ]);
    }

    /** @test */
    public function it_can_show_a_clinical_document()
    {
        $document = ClinicalDocument::factory()->create([
            'patient_id' => $this->patient->id,
            'facility_id' => $this->facility->id,
        ]);
        
        $response = $this->actingAs($this->user)
            ->getJson("/api/clinical-documents/{$document->id}");
        
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $document->id,
                    'document_uuid' => $document->document_uuid,
                ]
            ]);
    }

    /** @test */
    public function it_returns_404_when_document_not_found()
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/clinical-documents/9999');
        
        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Clinical document not found'
            ]);
    }

    /** @test */
    public function it_can_update_a_clinical_document()
    {
        $document = ClinicalDocument::factory()->create([
            'patient_id' => $this->patient->id,
            'facility_id' => $this->facility->id,
            'uploaded_by_staff_id' => $this->user->id,
        ]);
        
        $data = [
            'document_name' => 'Updated Document Name',
            'document_description' => 'Updated description',
            'status' => 'superseded',
        ];
        
        $response = $this->actingAs($this->user)
            ->putJson("/api/clinical-documents/{$document->id}", $data);
        
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Clinical document updated successfully'
            ]);
        
        $this->assertDatabaseHas('clinical_documents', [
            'id' => $document->id,
            'document_name' => 'Updated Document Name',
            'status' => 'superseded',
        ]);
    }

    /** @test */
    public function it_can_delete_a_clinical_document()
    {
        $document = ClinicalDocument::factory()->create([
            'patient_id' => $this->patient->id,
            'facility_id' => $this->facility->id,
            'uploaded_by_staff_id' => $this->user->id,
        ]);
        
        $response = $this->actingAs($this->user)
            ->deleteJson("/api/clinical-documents/{$document->id}");
        
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Clinical document marked as entered in error successfully'
            ]);
        
        $this->assertDatabaseHas('clinical_documents', [
            'id' => $document->id,
            'status' => 'entered_in_error',
        ]);
    }

    /** @test */
    public function it_can_get_documents_by_patient()
    {
        ClinicalDocument::factory()->count(5)->create([
            'patient_id' => $this->patient->id,
            'facility_id' => $this->facility->id,
        ]);
        
        $response = $this->actingAs($this->user)
            ->getJson("/api/clinical-documents/patient/{$this->patient->id}");
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
                'meta'
            ]);
    }

    /** @test */
    public function it_can_download_a_document()
    {
        $fileContent = 'Test file content';
        $fileHash = hash('sha256', $fileContent);
        
        Storage::put('clinical_documents/test.pdf', $fileContent);
        
        $document = ClinicalDocument::factory()->create([
            'patient_id' => $this->patient->id,
            'facility_id' => $this->facility->id,
            'file_storage_path' => 'clinical_documents/test.pdf',
            'file_hash' => $fileHash,
            'file_mime_type' => 'application/pdf',
            'file_extension' => 'pdf',
            'document_name' => 'Test Document.pdf',
        ]);
        
        $response = $this->actingAs($this->user)
            ->getJson("/api/clinical-documents/{$document->id}/download");
        
        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'application/pdf');
    }

    /** @test */
    public function it_can_verify_document_integrity()
    {
        $fileContent = 'Test file content';
        $fileHash = hash('sha256', $fileContent);
        
        Storage::put('clinical_documents/test.pdf', $fileContent);
        
        $document = ClinicalDocument::factory()->create([
            'patient_id' => $this->patient->id,
            'facility_id' => $this->facility->id,
            'file_storage_path' => 'clinical_documents/test.pdf',
            'file_hash' => $fileHash,
        ]);
        
        $response = $this->actingAs($this->user)
            ->getJson("/api/clinical-documents/{$document->id}/verify");
        
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'integrity_verified' => true,
                ]
            ]);
    }

    /** @test */
    public function it_can_update_document_status()
    {
        $document = ClinicalDocument::factory()->create([
            'patient_id' => $this->patient->id,
            'facility_id' => $this->facility->id,
        ]);
        
        $response = $this->actingAs($this->user)
            ->patchJson("/api/clinical-documents/{$document->id}/status", [
                'status' => 'superseded',
            ]);
        
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Document status updated successfully'
            ]);
        
        $this->assertDatabaseHas('clinical_documents', [
            'id' => $document->id,
            'status' => 'superseded',
        ]);
    }

    /** @test */
    public function it_can_get_statistics()
    {
        ClinicalDocument::factory()->count(10)->create([
            'facility_id' => $this->facility->id,
        ]);
        
        $response = $this->actingAs($this->user)
            ->getJson('/api/clinical-documents/statistics');
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'total_documents',
                    'active_documents',
                    'documents_by_type',
                    'total_storage_bytes',
                ]
            ]);
    }
}