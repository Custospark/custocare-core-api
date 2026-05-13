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
        Schema::create('referrals', function (Blueprint $table) {
            $table->id();
            $table->string('referral_uuid')->unique()->index();
            
            // Relationships
            $table->foreignId('patient_id')->constrained()->onDelete('cascade');
            $table->foreignId('facility_id')->constrained()->onDelete('cascade');
            $table->foreignId('referring_staff_id')->constrained('staff')->onDelete('cascade');
            $table->foreignId('receiving_staff_id')->nullable()->constrained('staff')->onDelete('set null');
            
            // Referral details
            $table->enum('referral_type', ['internal', 'external'])->default('internal');
            $table->string('referral_reason')->nullable();
            $table->text('clinical_notes')->nullable();
            $table->string('external_referral_id')->nullable(); // For external referral tracking
            $table->enum('status', ['pending', 'accepted', 'rejected', 'completed', 'cancelled'])->default('pending');
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium');
            
            // Dates
            $table->timestamp('referral_date')->useCurrent();
            $table->timestamp('response_date')->nullable();
            $table->timestamp('completed_date')->nullable();
            $table->timestamp('expiry_date')->nullable();
            
            // Metadata
            $table->json('metadata')->nullable();
            
            // Audit trail
            $table->timestamps();
            $table->softDeletes();
            $table->foreignId('created_by_staff_id')->nullable()->constrained('staff')->onDelete('set null');
            $table->foreignId('updated_by_staff_id')->nullable()->constrained('staff')->onDelete('set null');
            
            // Indexes
            $table->index(['patient_id', 'status']);
            $table->index(['facility_id', 'status']);
            $table->index(['referring_staff_id', 'status']);
            $table->index(['referral_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('referrals');
    }
};