<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('conversation_participants', function (Blueprint $table) {
            $table->id();
            
            // Foreign key to conversations table
            $table->foreignId('conversation_id')
                ->constrained('conversations')
                ->cascadeOnDelete();
            
            // Polymorphic participant relationship
            $table->enum('participant_type', ['staff', 'patient']);
            $table->unsignedBigInteger('participant_id');
            
            // Participant role in conversation
            $table->enum('role', [
                'owner',
                'moderator',
                'member',
                'read_only'
            ])->default('member');
            
            // Timestamps for participant lifecycle
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('left_at')->nullable();
            
            // Participant settings
            $table->boolean('is_muted')->default(false);
            
            $table->timestamps();
            
            // Unique constraint to prevent duplicate participants
            $table->unique(
                ['conversation_id', 'participant_type', 'participant_id'],
                'conversation_participant_unique'
            );
            
            // Index for polymorphic relationship queries
            $table->index(['participant_type', 'participant_id']);
            
            // Index for conversation-based queries
            $table->index(['conversation_id', 'left_at']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('conversation_participants');
    }
};