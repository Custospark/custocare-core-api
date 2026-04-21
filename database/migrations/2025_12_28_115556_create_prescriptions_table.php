<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PRESCRIPTIONS
 * 
 * Clinical prescription record - no billing logic
 * Patient can take this prescription to ANY pharmacy
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('medication_dispenses')) {
                Schema::dropIfExists('medication_dispenses');
            }     
        Schema::dropIfExists('prescriptions');
        Schema::create('prescriptions', function (Blueprint $table) {
            $table->id();
            
            // ─── Core Relationships ────────────────────────────────────────
            $table->unsignedBigInteger('facility_id');
            $table->foreign('facility_id')->references('id')->on('facilities')->onDelete('cascade');
            
            $table->unsignedBigInteger('patient_id');
            $table->foreign('patient_id')->references('id')->on('patients')->onDelete('cascade');
            
            $table->unsignedBigInteger('visit_id')->nullable();
            $table->foreign('visit_id')->references('id')->on('visits')->onDelete('set null');
            
            $table->unsignedBigInteger('clinical_template_id')->nullable();
            $table->foreign('clinical_template_id')->references('id')->on('clinical_templates')->onDelete('set null');
            
            // ─── Prescription Identification ───────────────────────────────
            $table->string('prescription_number', 50)->unique();
            $table->date('prescription_date');
            $table->date('valid_until')->nullable();
            
            // ─── ENUMS: Prescription Status (UI-friendly) ──────────────────
            $table->enum('status', [
                'Draft - Not Yet Finalized',
                'Active - Ready for Dispensing',
                'Partially Dispensed',
                'Fully Dispensed',
                'Expired - Past Valid Date',
                'Cancelled - No Longer Valid',
                'On Hold - Pending Review'
            ])->default('Draft - Not Yet Finalized');
            
            // ─── ENUMS: Prescription Type ──────────────────────────────────
            $table->enum('prescription_type', [
                'New Prescription',
                'Refill Prescription',
                'Renewal (New Course)',
                'Emergency Prescription',
                'Standing Order',
                'Discharge Prescription',
                'Transfer Prescription'
            ])->default('New Prescription');
            
            // ─── ENUMS: Priority ───────────────────────────────────────────
            $table->enum('priority', [
                'Routine - Fill Within 24 Hours',
                'Urgent - Fill Within 4 Hours',
                'STAT - Fill Immediately',
                'Scheduled - Fill on Specific Date'
            ])->default('Routine - Fill Within 24 Hours');
            
            // ─── Clinical Content ──────────────────────────────────────────
            $table->text('diagnosis')->nullable();
            $table->text('clinical_notes')->nullable();
            $table->text('special_instructions')->nullable()->comment('For pharmacy: e.g., "Patient has swallowing difficulty"');
            
            // ─── ENUMS: Allergy Check Status ───────────────────────────────
            $table->enum('allergy_check', [
                'No Known Allergies',
                'Allergies Checked - No Conflicts',
                'Allergy Warning - Overridden',
                'Allergy Alert - Changed Medication'
            ])->nullable();
            
            $table->text('allergy_notes')->nullable();
            
            // ─── Prescriber Information ────────────────────────────────────
            $table->unsignedBigInteger('prescribed_by');
            $table->foreign('prescribed_by')->references('id')->on('users')->onDelete('restrict');
            
            // ─── ENUMS: Prescriber Type (UI-friendly) ──────────────────────
            $table->enum('prescriber_type', [
                'Medical Doctor (MD)',
                'Doctor of Osteopathy (DO)',
                'Nurse Practitioner (NP)',
                'Physician Assistant (PA)',
                'Clinical Officer',
                'Dentist (DDS/DMD)',
                'Podiatrist (DPM)',
                'Optometrist (OD)',
                'Pharmacist (PharmD)',
                'Midwife (CNM/CM)'
            ])->default('Medical Doctor (MD)');
            
            $table->string('prescriber_license', 100)->nullable();
            $table->string('prescriber_contact', 100)->nullable();
            
            // ─── ENUMS: Prescription Format ────────────────────────────────
            $table->enum('prescription_format', [
                'Electronic (e-Prescription)',
                'Printed Paper Prescription',
                'Handwritten Prescription',
                'Faxed Prescription',
                'Verbal Order (Telephone)'
            ])->default('Electronic (e-Prescription)');
            
            // ─── Dispensing Information (For tracking, not billing) ────────
            $table->timestamp('dispensed_at')->nullable();
            $table->string('dispensed_by_name', 255)->nullable()->comment('Name of pharmacist who dispensed');
            $table->string('dispensed_pharmacy', 255)->nullable()->comment('Pharmacy name if dispensed externally');
            
            // ─── ENUMS: Dispensing Location ─────────────────────────────────
            $table->enum('dispensing_location', [
                'Not Dispensed Yet',
                'Dispensed at Our Facility',
                'Dispensed at External Pharmacy',
                'Patient Took Elsewhere'
            ])->default('Not Dispensed Yet');
            
            // ─── Cancellation Information ──────────────────────────────────
            $table->timestamp('cancelled_at')->nullable();
            $table->unsignedBigInteger('cancelled_by')->nullable();
            $table->foreign('cancelled_by')->references('id')->on('users')->onDelete('set null');
            
            // ─── ENUMS: Cancellation Reasons (UI-friendly) ─────────────────
            $table->enum('cancellation_reason', [
                'Patient Requested Cancellation',
                'Medication Error - Wrong Drug',
                'Medication Error - Wrong Dose',
                'Allergy Discovered',
                'Adverse Reaction Reported',
                'Duplicate Prescription',
                'Prescription Expired',
                'Better Alternative Available',
                'Patient Deceased',
                'Insurance Denied (Patient Canceled)',
                'Out of Stock (Patient Canceled)'
            ])->nullable();
            
            $table->text('cancellation_notes')->nullable();
            
            // ─── Additional Clinical Notes ─────────────────────────────────
            $table->text('patient_education_notes')->nullable()->comment('What to tell the patient');
            $table->text('follow_up_instructions')->nullable();
            $table->date('follow_up_date')->nullable();
            
            // ─── Audit ─────────────────────────────────────────────────────
            $table->unsignedBigInteger('created_by')->nullable();
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
            
            $table->timestamps();
            $table->softDeletes();
            
            // ─── Indexes ───────────────────────────────────────────────────
            $table->index(['facility_id', 'patient_id']);
            $table->index('prescription_number');
            $table->index('visit_id');
            $table->index('status');
            $table->index('prescription_date');
            $table->index('prescribed_by');
            $table->index('valid_until');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prescriptions');
    }
};