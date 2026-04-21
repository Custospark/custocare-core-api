<?php
// database/migrations/2026_04_21_000003_change_prescription_enums_to_varchar.php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Convert ENUM columns to VARCHAR to fix data truncation issues
 * MySQL ENUM has problems with strings containing spaces and special characters
 */
return new class extends Migration
{
    public function up(): void
    {
        // Disable foreign key checks temporarily
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        
        Schema::table('prescriptions', function (Blueprint $table) {
            // Convert status from ENUM to VARCHAR
            $table->string('status', 100)->default('Draft - Not Yet Finalized')->change();
            
            // Convert prescription_type from ENUM to VARCHAR
            $table->string('prescription_type', 100)->default('New Prescription')->change();
            
            // Convert priority from ENUM to VARCHAR
            $table->string('priority', 100)->default('Routine - Fill Within 24 Hours')->change();
            
            // Convert allergy_check from ENUM to VARCHAR
            $table->string('allergy_check', 100)->nullable()->change();
            
            // Convert prescriber_type from ENUM to VARCHAR
            $table->string('prescriber_type', 100)->default('Medical Doctor (MD)')->change();
            
            // Convert prescription_format from ENUM to VARCHAR
            $table->string('prescription_format', 100)->default('Electronic (e-Prescription)')->change();
            
            // Convert dispensing_location from ENUM to VARCHAR
            $table->string('dispensing_location', 100)->default('Not Dispensed Yet')->change();
            
            // Convert cancellation_reason from ENUM to VARCHAR
            $table->string('cancellation_reason', 100)->nullable()->change();
        });
        
        // Re-enable foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        // Disable foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        
        Schema::table('prescriptions', function (Blueprint $table) {
            // Revert status back to ENUM
            $table->enum('status', [
                'Draft - Not Yet Finalized',
                'Active - Ready for Dispensing',
                'Partially Dispensed',
                'Fully Dispensed',
                'Expired - Past Valid Date',
                'Cancelled - No Longer Valid',
                'On Hold - Pending Review'
            ])->default('Draft - Not Yet Finalized')->change();
            
            // Revert prescription_type back to ENUM
            $table->enum('prescription_type', [
                'New Prescription',
                'Refill Prescription',
                'Renewal (New Course)',
                'Emergency Prescription',
                'Standing Order',
                'Discharge Prescription',
                'Transfer Prescription'
            ])->default('New Prescription')->change();
            
            // Revert priority back to ENUM
            $table->enum('priority', [
                'Routine - Fill Within 24 Hours',
                'Urgent - Fill Within 4 Hours',
                'STAT - Fill Immediately',
                'Scheduled - Fill on Specific Date'
            ])->default('Routine - Fill Within 24 Hours')->change();
            
            // Revert allergy_check back to ENUM
            $table->enum('allergy_check', [
                'No Known Allergies',
                'Allergies Checked - No Conflicts',
                'Allergy Warning - Overridden',
                'Allergy Alert - Changed Medication'
            ])->nullable()->change();
            
            // Revert prescriber_type back to ENUM
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
            ])->default('Medical Doctor (MD)')->change();
            
            // Revert prescription_format back to ENUM
            $table->enum('prescription_format', [
                'Electronic (e-Prescription)',
                'Printed Paper Prescription',
                'Handwritten Prescription',
                'Faxed Prescription',
                'Verbal Order (Telephone)'
            ])->default('Electronic (e-Prescription)')->change();
            
            // Revert dispensing_location back to ENUM
            $table->enum('dispensing_location', [
                'Not Dispensed Yet',
                'Dispensed at Our Facility',
                'Dispensed at External Pharmacy',
                'Patient Took Elsewhere'
            ])->default('Not Dispensed Yet')->change();
            
            // Revert cancellation_reason back to ENUM
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
            ])->nullable()->change();
        });
        
        // Re-enable foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};