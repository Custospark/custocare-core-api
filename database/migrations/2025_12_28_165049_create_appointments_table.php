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
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->uuid('appointment_uuid')->unique()->index();
            $table->unsignedBigInteger('facility_id')->index();
            $table->unsignedBigInteger('patient_id')->index();
            $table->unsignedBigInteger('provider_staff_id')->nullable()->index();
            $table->unsignedBigInteger('department_id')->nullable()->index();
            $table->unsignedBigInteger('created_visit_id')->nullable();
            
            // Appointment details
            $table->enum('appointment_type', [
                'new_patient_consultation',
                'followup_visit',
                'annual_physical',
                'procedure',
                'diagnostic_test',
                'therapy_session',
                'telehealth',
                'vaccination',
                'consultation'
            ])->index();
            
            // Timing
            $table->timestamp('scheduled_start_time')->index();
            $table->timestamp('scheduled_end_time');
            $table->unsignedSmallInteger('duration_minutes');
            
            // Reason
            $table->text('reason_for_visit')->nullable();
            $table->json('requested_services')->nullable();
            
            // Status
            $table->enum('status', [
                'scheduled',
                'confirmed',
                'checked_in',
                'in_progress',
                'completed',
                'no_show',
                'cancelled',
                'rescheduled'
            ])->default('scheduled')->index();
            
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('checked_in_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            
            // Reminders
            $table->boolean('reminder_sent')->default(false);
            $table->timestamp('reminder_sent_at')->nullable();
            
            // Audit
            $table->timestamps();
            $table->softDeletes();
            $table->json('metadata')->nullable();
            
            // Foreign keys
            $table->foreign('facility_id')->references('id')->on('facilities')->onDelete('cascade');
            $table->foreign('patient_id')->references('id')->on('patients')->onDelete('cascade');
            $table->foreign('provider_staff_id')->references('id')->on('staff')->onDelete('set null');
            $table->foreign('created_visit_id')->references('id')->on('visits')->onDelete('set null');
            
            // Composite indexes for query performance
            $table->index(['facility_id', 'scheduled_start_time', 'status']);
            $table->index(['patient_id', 'scheduled_start_time']);
            $table->index(['provider_staff_id', 'scheduled_start_time']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};