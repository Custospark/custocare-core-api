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
        Schema::create('staff_invitations', function (Blueprint $table) {
            $table->id();
            $table->uuid('invitation_uuid')->unique()->index();
            
            // Staff and assignment references
            $table->unsignedBigInteger('staff_id')->index();
            $table->unsignedBigInteger('facility_id')->index();
            $table->unsignedBigInteger('department_id')->nullable()->index();
            $table->unsignedBigInteger('role_id')->nullable()->index();
            
            // Invitation status
            $table->enum('status', ['pending', 'accepted', 'declined', 'expired'])->default('pending')->index();
            
            // Timing
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            
            // Audit / metadata
            $table->unsignedBigInteger('invited_by_staff_id')->nullable()->index();
            $table->json('metadata')->nullable();
            
            // Timestamps
            $table->timestamps();
            $table->softDeletes();
            
            // Foreign keys
            $table->foreign('staff_id')->references('id')->on('staff')->onDelete('cascade');
            $table->foreign('facility_id')->references('id')->on('facilities')->onDelete('cascade');
            $table->foreign('department_id')->references('id')->on('departments')->onDelete('set null');
            $table->foreign('role_id')->references('id')->on('roles')->onDelete('set null');
            $table->foreign('invited_by_staff_id')->references('id')->on('staff')->onDelete('set null');
            
            // Performance indexes
            $table->index(['facility_id', 'department_id', 'status']);
            $table->index(['staff_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('staff_invitations');
    }
};