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
        Schema::create('message_receipts', function (Blueprint $table) {
            $table->id();
            
            // Foreign key to messages table
            $table->foreignId('message_id')
                ->constrained('messages')
                ->cascadeOnDelete()
                ->comment('Reference to the parent message');
            
            // Polymorphic recipient relationship
            $table->enum('recipient_type', ['staff', 'patient'])
                ->comment('Type of recipient (staff or patient)');
            $table->unsignedBigInteger('recipient_id')
                ->comment('ID of the recipient based on recipient_type');
            
            // Timestamps for tracking receipt status
            $table->timestamp('delivered_at')->nullable()
                ->comment('When the message was delivered to recipient');
            $table->timestamp('read_at')->nullable()
                ->comment('When the recipient read the message');
            $table->timestamp('acknowledged_at')->nullable()
                ->comment('When recipient acknowledged/responded to message');
            
            // Standard timestamps
            $table->timestamps();
            
            // Unique constraint to prevent duplicate receipts for same recipient
            $table->unique(
                ['message_id', 'recipient_type', 'recipient_id'],
                'message_recipient_unique'
            );
            
            // Index for polymorphic relationship queries
            $table->index(['recipient_type', 'recipient_id']);
            
            // Additional index for performance
            $table->index(['message_id', 'delivered_at']);
            $table->index(['message_id', 'read_at']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('message_receipts');
    }
};