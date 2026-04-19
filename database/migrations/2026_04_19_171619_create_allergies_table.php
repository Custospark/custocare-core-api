<?php
// database/migrations/2026_01_15_000001_create_allergies_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('allergies', function (Blueprint $table) {
            $table->id();
            
            // Foreign keys
            $table->foreignId('patient_id')
                  ->constrained('patients')
                  ->onDelete('cascade');
            
            $table->foreignId('recorded_by')
                  ->nullable()
                  ->constrained('users')
                  ->onDelete('set null');
            
            $table->foreignId('visit_id')
                  ->nullable()
                  ->constrained('visits')
                  ->onDelete('set null');
            
            // Core fields
            $table->string('allergen');
            $table->string('reaction')->nullable();
            $table->enum('severity', ['mild', 'moderate', 'severe'])->default('moderate');
            $table->text('clinical_notes')->nullable();
            
            // Status
            $table->boolean('is_active')->default(true);
            $table->timestamp('diagnosed_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            
            // Meta
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes for performance
            $table->index(['patient_id', 'is_active']);
            $table->index('allergen');
            $table->index('severity');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('allergies');
    }
};