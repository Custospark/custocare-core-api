<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * PATIENT_VISIT_SUMMARY_VIEWS - Patient portal & care coordination
     * Refresh Strategy: Nightly batch + real-time for active visits
     */
    public function up(): void
    {
        Schema::create('patient_visit_summary_views', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('patient_id')->unique()->index();
            
            // Active visits
            $table->json('active_visit_ids')->nullable();
            $table->unsignedTinyInteger('active_visits_count')->default(0);
            
            // Recent history
            $table->json('recent_visits_last_30_days')->nullable();
            $table->unsignedTinyInteger('visits_last_30_days_count')->default(0);
            $table->timestamp('last_visit_date')->nullable()->index();
            $table->unsignedBigInteger('last_visit_facility_id')->nullable();
            
            // Upcoming appointments
            $table->json('upcoming_appointments')->nullable();
            $table->timestamp('next_appointment_at')->nullable()->index();
            
            // Prescriptions
            $table->json('active_prescriptions')->nullable();
            $table->json('pending_prescriptions')->nullable();
            $table->unsignedTinyInteger('active_prescriptions_count')->default(0);
            
            // Financial
            $table->decimal('outstanding_bills_total', 12, 2)->default(0);
            $table->unsignedTinyInteger('unpaid_invoices_count')->default(0);
            $table->json('payment_plans')->nullable();
            
            // Health metrics trends
            $table->json('health_metrics_trends')->nullable()->comment('Weight, BP, glucose trends');
            $table->json('recent_lab_results')->nullable();
            $table->json('recent_imaging_results')->nullable();
            
            // Care team
            $table->json('care_team_members')->nullable();
            $table->unsignedBigInteger('primary_care_provider_id')->nullable();
            
            // Preventive care
            $table->json('preventive_care_due')->nullable();
            $table->json('immunizations_due')->nullable();
            $table->json('screenings_due')->nullable();
            
            // Alerts & notifications
            $table->json('patient_alerts')->nullable();
            $table->unsignedTinyInteger('unread_messages_count')->default(0);
            
            // Update tracking
            $table->timestamp('last_updated_at')->index();
            $table->timestamp('created_at');
            $table->timestamp('updated_at');
            
            // Performance indexes
            $table->index(['last_visit_date', 'active_visits_count'],'last_visit_date_active_visit_count_unique');
            $table->index(['next_appointment_at'],'next_appoitment_at_unque');
            
            // Foreign key constraints
            $table->foreign('patient_id')->references('id')->on('patients')->onDelete('cascade');
            $table->foreign('last_visit_facility_id')->references('id')->on('facilities')->onDelete('set null');
            $table->foreign('primary_care_provider_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('patient_visit_summary_views');
    }
};