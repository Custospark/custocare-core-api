<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
    Schema::create('notifications', function (Blueprint $table) {
    $table->id();
    
    $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade'); // Null = system-wide
    $table->string('title');
    $table->text('message');
    $table->enum('target_type', ['system', 'user'])->default('system'); // System-wide or user-specific
    $table->enum('channel', ['in_app', 'email', 'both'])->default('in_app');
    
    $table->boolean('is_read')->default(false);
    
    $table->timestamp('read_at')->nullable(); // Optional: track read time
    $table->timestamp('sent_at')->nullable(); // Optional: track sent time (can be same as created_at)
    
    $table->timestamps();
});
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};