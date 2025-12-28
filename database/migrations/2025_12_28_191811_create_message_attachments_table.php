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
        Schema::create('message_attachments', function (Blueprint $table) {
            $table->id();
            $table->uuid('attachment_uuid')->unique()->index();
            
            // Foreign key to messages table with cascading delete
            $table->foreignId('message_id')
                ->constrained('messages')
                ->cascadeOnDelete();
            
            // Attachment type with predefined enum values
            $table->enum('attachment_type', [
                'image',
                'pdf',
                'lab_result',
                'radiology_image',
                'audio',
                'video',
                'other'
            ])->index();
            
            // File metadata
            $table->string('file_name', 255);
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('file_size_bytes');
            $table->string('storage_disk', 50);
            $table->string('storage_path', 512);
            
            // PHI (Protected Health Information) flag
            $table->boolean('contains_phi')->default(true);
            
            // File integrity check
            $table->string('checksum', 64)->index();
            
            // Timestamps
            $table->timestamps();
            
            // Additional indexes for performance
            $table->index(['message_id', 'attachment_type']);
            $table->index(['contains_phi', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('message_attachments');
    }
};