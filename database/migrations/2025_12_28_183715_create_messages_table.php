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
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->uuid('message_uuid')->unique()->index();

            $table->foreignId('conversation_id')
                ->constrained('conversations')
                ->cascadeOnDelete();

            // Sender
            $table->enum('sender_type', ['staff', 'patient', 'system']);
            $table->unsignedBigInteger('sender_id')->nullable();

            // Content
            $table->enum('message_type', [
                'text',
                'rich_text',
                'system_event',
                'clinical_note',
                'alert',
                'file',
                'image'
            ])->index();

            $table->longText('content_encrypted')->nullable();
            $table->string('content_hash', 64)->index();

            // Clinical flags
            $table->boolean('contains_phi')->default(true)->index();
            $table->boolean('is_clinical')->default(false)->index();
            $table->boolean('requires_acknowledgement')->default(false);

            // Threading
            $table->foreignId('parent_message_id')
                ->nullable()
                ->constrained('messages')
                ->nullOnDelete();

            // Delivery
            $table->enum('delivery_status', [
                'pending',
                'sent',
                'delivered',
                'failed'
            ])->default('pending')->index();

            $table->timestamps();
            $table->softDeletes();

            $table->timestamp('edited_at')->nullable();
            $table->foreignId('edited_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->index(['conversation_id', 'created_at']);
            
            // Compound index for sender polymorphic relation
            $table->index(['sender_type', 'sender_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};