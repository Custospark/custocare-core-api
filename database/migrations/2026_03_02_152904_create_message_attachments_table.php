<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MESSAGE ATTACHMENTS
 *
 * File metadata and storage pointer for each attachment on a message.
 * Actual binary data lives on a Laravel filesystem disk (local/S3/etc.).
 * Multiple attachments per message are fully supported.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('message_attachments');
        Schema::create('message_attachments', function (Blueprint $table) {
            $table->id();

            // ── Relation to message ───────────────────────────────────────
            $table->unsignedBigInteger('message_id');
            $table->foreign('message_id')
                  ->references('id')->on('messages')
                  ->onDelete('cascade');

            // ── File identity ─────────────────────────────────────────────
            $table->string('original_name', 512)
                  ->comment('Filename as uploaded by the user');
            $table->string('stored_name', 512)
                  ->comment('UUID-based name used on disk to prevent collisions');

            // ── Storage pointer ───────────────────────────────────────────
            $table->string('disk', 50)->default('local')
                  ->comment('Laravel filesystem disk (local, s3, gcs …)');
            $table->string('path', 1024)
                  ->comment('Relative path on the selected disk');

            // ── File metadata ─────────────────────────────────────────────
            $table->string('mime_type', 255)->nullable()
                  ->comment('e.g. application/pdf, image/png');
            $table->unsignedBigInteger('size_bytes')
                  ->comment('File size in bytes');
            $table->string('size_formatted', 30)->nullable()
                  ->comment('Human-readable size, e.g. "2.4 MB"');

            // ── Uploader ──────────────────────────────────────────────────
            $table->unsignedBigInteger('uploaded_by')->nullable()
                  ->comment('FK → users.id; nullable for system-generated attachments');
            $table->foreign('uploaded_by')
                  ->references('id')->on('users')
                  ->onDelete('set null');

            // ── Upload status (for progressive uploads) ───────────────────
            $table->enum('upload_status', ['pending', 'uploading', 'complete', 'failed'])
                  ->default('pending')
                  ->comment('Tracks client-side or async upload state');
            $table->unsignedTinyInteger('upload_progress')->default(0)
                  ->comment('0-100 percentage indicator during upload');

            $table->timestamps();

            // ── Indexes ───────────────────────────────────────────────────
            $table->index('message_id');
            $table->index('mime_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_attachments');
    }
};
