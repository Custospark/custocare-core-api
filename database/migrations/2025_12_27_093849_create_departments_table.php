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
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->uuid('department_uuid')->unique()->index();
            $table->unsignedBigInteger('facility_id')->index();
            
            // Department identification
            $table->string('department_code', 50)->index();
            $table->string('department_name', 200);
            $table->enum('department_type', [
                'emergency',
                'intensive_care',
                'surgery',
                'outpatient',
                'inpatient',
                'radiology',
                'laboratory',
                'pharmacy',
                'physical_therapy',
                'cardiology',
                'oncology',
                'pediatrics',
                'obstetrics',
                'psychiatry',
                'administration',
                'support_services'
            ])->index();
            
            // Hierarchy
            $table->unsignedBigInteger('parent_department_id')->nullable();
            $table->unsignedBigInteger('department_head_staff_id')->nullable();
            
            // Capacity & resources
            $table->unsignedSmallInteger('bed_count')->nullable();
            $table->unsignedSmallInteger('treatment_room_count')->nullable();
            $table->unsignedSmallInteger('max_concurrent_capacity')->default(10);
            
            // Location
            $table->string('building', 100)->nullable();
            $table->string('floor', 20)->nullable();
            $table->string('wing_section', 50)->nullable();
            
            // Operational
            $table->json('operating_hours')->nullable();
            $table->boolean('accepts_walk_ins')->default(false);
            $table->boolean('requires_appointment')->default(true);
            $table->unsignedSmallInteger('average_wait_time_minutes')->nullable();
            
            // Status
            $table->enum('status', ['active', 'inactive', 'temporarily_closed'])->default('active')->index();
            
            // Audit
            $table->timestamps();
            $table->softDeletes();
            $table->json('metadata')->nullable();
            
            // Foreign keys
            $table->foreign('facility_id')->references('id')->on('facilities')->onDelete('cascade');
            $table->foreign('parent_department_id')->references('id')->on('departments')->onDelete('set null');
            $table->foreign('department_head_staff_id')->references('id')->on('staff')->onDelete('set null');
            
            // Unique constraint
            $table->unique(['facility_id', 'department_code']);
            
            // Performance indexes
            $table->index(['facility_id', 'department_type', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('departments');
    }
};