<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('clinical_documents', function (Blueprint $table) {
            $table->id();
            $table->uuid('document_uuid')->unique()->index();
            $table->unsignedBigInteger('facility_id')->index();
            $table->unsignedBigInteger('patient_id')->index();
            $table->unsignedBigInteger('visit_id')->nullable()->index();
            
            // Document classification
            $table->enum('document_type', [
                'lab_report',
                'radiology_report',
                'pathology_report',
                'operative_note',
                'discharge_summary',
                'consultation_letter',
                'referral_letter',
                'consent_form',
                'advance_directive',
                'insurance_card',
                'identification',
                'medical_record_request',
                'other'
            ])->index();
            
            // File information
            $table->string('document_name', 300);
            $table->text('document_description')->nullable();
            $table->string('file_mime_type', 100);
            $table->string('file_extension', 10);
            $table->unsignedInteger('file_size_bytes');
            $table->string('file_storage_path', 512);
            $table->string('file_hash', 128)->comment('SHA-256 for integrity');
            
            // Metadata
            $table->date('document_date')->nullable()->index();
            $table->unsignedBigInteger('authored_by_staff_id')->nullable();
            $table->string('external_author', 200)->nullable();
            
            // Status
            $table->enum('status', ['active', 'superseded', 'entered_in_error'])->default('active')->index();
            
            // Audit
            $table->timestamps();
            $table->softDeletes();
            $table->unsignedBigInteger('uploaded_by_staff_id')->nullable();
            $table->json('metadata')->nullable();
            
            // Foreign keys - Note: Adjust based on your actual table names
            $table->foreign('patient_id')->references('id')->on('patients')->onDelete('restrict');
            $table->foreign('visit_id')->references('id')->on('visits')->onDelete('set null');
            // facility_id foreign key would be added if facilities table exists
            // $table->foreign('facility_id')->references('id')->on('facilities')->onDelete('cascade');
            
            // Performance indexes
            $table->index(['patient_id', 'document_type', 'document_date']);
            $table->index(['visit_id', 'document_type']);
            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clinical_documents');
    }
};