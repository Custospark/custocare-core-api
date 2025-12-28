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
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->uuid('conversation_uuid')->unique()->index();

            $table->foreignId('facility_id')
                ->constrained('facilities')
                ->cascadeOnDelete();

            $table->enum('conversation_type', [
                'direct',
                'group',
                'broadcast',
                'system',
                'care_context'
            ])->index();

            // Optional clinical context
            $table->foreignId('visit_id')
                ->nullable()
                ->constrained('visits')
                ->nullOnDelete();

            $table->foreignId('appointment_id')
                ->nullable()
                ->constrained('appointments')
                ->nullOnDelete();

            $table->string('department_code', 50)->nullable()->index();
            $table->string('title', 255)->nullable();

            // Compliance & priority
            $table->boolean('contains_phi')->default(true)->index();
            $table->boolean('is_emergency')->default(false)->index();

            $table->enum('status', [
                'active',
                'archived',
                'locked'
            ])->default('active')->index();

            $table->foreignId('created_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['facility_id', 'conversation_type']);
            $table->index(['facility_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};