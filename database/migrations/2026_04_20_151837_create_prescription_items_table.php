<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PRESCRIPTION ITEMS
 * 
 * Individual medications prescribed with clear, UI-friendly enums
 * Total quantity auto-calculated for patient to purchase
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('prescription_items');
        Schema::create('prescription_items', function (Blueprint $table) {
            $table->id();
            
            // ─── Parent Prescription ───────────────────────────────────────
            $table->unsignedBigInteger('prescription_id');
            $table->foreign('prescription_id')->references('id')->on('prescriptions')->onDelete('cascade');
            
            // ─── Medication Information (Human-readable for any pharmacy) ───
            $table->string('medication_name', 255)->comment('e.g., Paracetamol, Amoxicillin, Lisinopril');
            $table->string('brand_name', 255)->nullable()->comment('e.g., Panadol, Augmentin, Prinivil');
            $table->string('strength', 100)->nullable()->comment('e.g., 500mg, 250mg/5ml, 100mcg');
            
            // ─── ENUMS: Dosage Form (UI-friendly, exhaustive) ──────────────
            $table->enum('dosage_form', [
                'Tablet',
                'Capsule',
                'Injection (IV/IM/SC)',
                'Syrup / Liquid',
                'Suspension',
                'Cream',
                'Ointment',
                'Gel',
                'Lotion',
                'Eye Drops',
                'Ear Drops',
                'Nasal Spray',
                'Inhaler',
                'Nebulizer Solution',
                'Patch (Transdermal)',
                'Suppository (Rectal)',
                'Suppository (Vaginal)',
                'Powder',
                'Foam',
                'Shampoo',
                'Mouthwash / Gargle',
                'Lozenge / Troche',
                'Chewing Gum',
                'Implant',
                'Insert (Vaginal Ring)',
                'Wafer (Oral Dissolving)',
                'Film (Oral Dissolving)'
            ])->default('Tablet');
            
            // ─── Dosage Quantity ───────────────────────────────────────────
            $table->decimal('dosage_quantity', 8, 2)->comment('e.g., 1, 0.5, 2');
            
            // ─── ENUMS: Dosage Unit (UI-friendly) ──────────────────────────
            $table->enum('dosage_unit', [
                'tablet(s)',
                'capsule(s)',
                'milligram (mg)',
                'microgram (mcg)',
                'gram (g)',
                'milliliter (ml)',
                'liter (L)',
                'international unit (IU)',
                'drop(s)',
                'puff(s)',
                'spray(s)',
                'inhalation(s)',
                'application(s)',
                'patch(es)',
                'suppository(ies)',
                'pump(s)',
                'actuation(s)',
                'vial(s)',
                'ampule(s)'
            ])->default('tablet(s)');
            
            // ─── ENUMS: Frequency (UI-friendly, clear text) ────────────────
            $table->enum('frequency', [
                'Once daily (OD) - Take 1 time per day',
                'Twice daily (BD) - Take 2 times per day',
                'Three times daily (TDS) - Take 3 times per day',
                'Four times daily (QID) - Take 4 times per day',
                'Every 2 hours - Take every 2 hours',
                'Every 3 hours - Take every 3 hours',
                'Every 4 hours - Take every 4 hours',
                'Every 6 hours - Take every 6 hours',
                'Every 8 hours - Take every 8 hours',
                'Every 12 hours - Take every 12 hours',
                'Every 24 hours - Take every 24 hours',
                'At bedtime (HS) - Take before sleeping',
                'Before meals (AC) - Take 30 minutes before food',
                'After meals (PC) - Take immediately after food',
                'As needed (PRN) - Take only when symptoms occur',
                'Immediately (STAT) - Take right now',
                'Once weekly - Take 1 time per week',
                'Twice weekly - Take 2 times per week',
                'Once monthly - Take 1 time per month',
                'Every other day - Take once every 2 days',
                'With specific meals - Breakfast/lunch/dinner only'
            ])->default('Once daily (OD) - Take 1 time per day');
            
            // ─── Duration ──────────────────────────────────────────────────
            $table->integer('duration_value')->comment('e.g., 5, 7, 10, 14');
            
            // ─── ENUMS: Duration Unit (UI-friendly) ────────────────────────
            $table->enum('duration_unit', [
                'Day(s)',
                'Week(s)',
                'Month(s)',
                'Year(s)'
            ])->default('Day(s)');
            
            // ─── CALCULATED TOTAL QUANTITY (What patient needs to buy) ─────
            // Auto-calculated based on frequency × duration
            $table->decimal('total_quantity', 10, 2)
                  ->storedAs('CASE 
                      WHEN frequency LIKE "Once daily%" THEN 1 * duration_value * dosage_quantity
                      WHEN frequency LIKE "Twice daily%" THEN 2 * duration_value * dosage_quantity
                      WHEN frequency LIKE "Three times daily%" THEN 3 * duration_value * dosage_quantity
                      WHEN frequency LIKE "Four times daily%" THEN 4 * duration_value * dosage_quantity
                      WHEN frequency LIKE "Every 2 hours%" THEN 12 * duration_value * dosage_quantity
                      WHEN frequency LIKE "Every 3 hours%" THEN 8 * duration_value * dosage_quantity
                      WHEN frequency LIKE "Every 4 hours%" THEN 6 * duration_value * dosage_quantity
                      WHEN frequency LIKE "Every 6 hours%" THEN 4 * duration_value * dosage_quantity
                      WHEN frequency LIKE "Every 8 hours%" THEN 3 * duration_value * dosage_quantity
                      WHEN frequency LIKE "Every 12 hours%" THEN 2 * duration_value * dosage_quantity
                      WHEN frequency LIKE "Every 24 hours%" THEN 1 * duration_value * dosage_quantity
                      WHEN frequency LIKE "At bedtime%" THEN 1 * duration_value * dosage_quantity
                      WHEN frequency LIKE "Before meals%" THEN 3 * duration_value * dosage_quantity
                      WHEN frequency LIKE "After meals%" THEN 3 * duration_value * dosage_quantity
                      WHEN frequency LIKE "Once weekly%" THEN 1 * (duration_value / 7) * dosage_quantity
                      WHEN frequency LIKE "Twice weekly%" THEN 2 * (duration_value / 7) * dosage_quantity
                      WHEN frequency LIKE "Once monthly%" THEN 1 * (duration_value / 30) * dosage_quantity
                      WHEN frequency LIKE "Every other day%" THEN 0.5 * duration_value * dosage_quantity
                      ELSE duration_value * dosage_quantity
                  END')
                  ->comment('Total quantity patient needs to purchase (auto-calculated)');
            
            // ─── ENUMS: Route of Administration (UI-friendly, exhaustive) ──
            $table->enum('route', [
                'By mouth (Oral)',
                'Under the tongue (Sublingual)',
                'Between gum and cheek (Buccal)',
                'Into the vein (Intravenous/IV)',
                'Into the muscle (Intramuscular/IM)',
                'Under the skin (Subcutaneous/SC)',
                'Into the skin (Intradermal)',
                'On the skin (Topical)',
                'Through the skin (Transdermal patch)',
                'Into the eye (Ophthalmic)',
                'Into the ear (Otic)',
                'Into the nose (Nasal)',
                'Inhaled into lungs (Inhalation)',
                'Into the rectum (Rectal)',
                'Into the vagina (Vaginal)',
                'Into the bladder (Intravesical)',
                'Into the joint (Intra-articular)',
                'Into the spine (Intrathecal)'
            ])->default('By mouth (Oral)');
            
            // ─── Instructions ──────────────────────────────────────────────
            $table->text('instructions')->nullable()->comment('Custom instructions for patient');
            $table->boolean('as_needed')->default(false);
            $table->text('as_needed_reason')->nullable()->comment('e.g., For fever >38.5°C, For breakthrough pain');
            
            // ─── ENUMS: Administration Instructions (UI-friendly) ──────────
            $table->enum('administration_instructions', [
                'No special instructions',
                'Take with food',
                'Take before meals (30 minutes before)',
                'Take after meals (immediately after)',
                'Take on empty stomach (1 hour before or 2 hours after meals)',
                'Take with plenty of water',
                'Take with milk',
                'Avoid grapefruit juice',
                'Avoid alcohol',
                'Avoid dairy products',
                'Shake well before use',
                'Refrigerate - do not freeze',
                'Do not refrigerate - store at room temperature',
                'Protect from light',
                'Chew tablet completely before swallowing',
                'Dissolve under tongue - do not swallow',
                'Swallow whole - do not crush or chew',
                'Crush tablet and mix with soft food',
                'Open capsule and mix with applesauce',
                'Apply to clean, dry skin',
                'Wash hands before and after application',
                'Do not use more than directed'
            ])->default('No special instructions');
            
            // ─── ENUMS: Refill Authorization ────────────────────────────────
            $table->enum('refills', [
                '0 refills - One time only',
                '1 refill',
                '2 refills',
                '3 refills',
                '4 refills',
                '5 refills',
                '6 refills',
                '12 refills - One year supply',
                'Unlimited refills as needed'
            ])->default('0 refills - One time only');
            
            $table->text('refill_instructions')->nullable();
            
            // ─── ENUMS: Medication Type (Clinical classification) ───────────
            $table->enum('medication_type', [
                'Prescription only (Rx required)',
                'Over-the-counter (OTC)',
                'Controlled substance (Special prescription required)',
                'Antibiotic (Complete full course)',
                'Antibiotic (Complete full course) - High priority',
                'Steroid (Tapering required)',
                'Opioid (High risk - monitor)',
                'Insulin (Refrigeration required)',
                'Biologic (Special handling)',
                'Chemotherapy (Special handling)',
                'Vaccine (Cold chain required)'
            ])->nullable();
            
            // ─── ENUMS: Monitoring Required (UI-friendly) ──────────────────
            $table->enum('monitoring_required', [
                'No specific monitoring needed',
                'Monitor blood pressure regularly',
                'Monitor blood glucose levels',
                'Monitor kidney function (Creatinine)',
                'Monitor liver function (LFTs)',
                'Monitor blood counts (CBC)',
                'Monitor INR (Blood thinning test)',
                'Monitor potassium levels',
                'Monitor drug levels (Therapeutic drug monitoring)',
                'Monitor for side effects'
            ])->nullable();
            
            // ─── ENUMS: Common Side Effects Warning ────────────────────────
            $table->enum('common_side_effects', [
                'No common side effects',
                'May cause drowsiness - Avoid driving',
                'May cause dizziness - Rise slowly',
                'May cause nausea - Take with food',
                'May cause dry mouth',
                'May cause headache',
                'May cause stomach upset',
                'May cause diarrhea',
                'May cause constipation',
                'May cause skin rash - Report immediately',
                'May cause swelling - Report immediately'
            ])->nullable();
            
            // ─── Clinical Notes ────────────────────────────────────────────
            $table->text('clinical_reasoning')->nullable()->comment('Why this medication was chosen');
            $table->text('substitution_instructions')->nullable()->comment('e.g., "May substitute with generic"');
            
            // ─── ENUMS: Substitution Allowed ───────────────────────────────
            $table->enum('substitution', [
                'Generic substitution allowed',
                'Brand name only - No substitution',
                'Therapeutic substitution allowed (same class)',
                'Dispense as written (DAW)'
            ])->default('Generic substitution allowed');
            
            // ─── Audit ─────────────────────────────────────────────────────
            $table->unsignedBigInteger('created_by')->nullable();
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
            
            $table->timestamps();
            $table->softDeletes();
            
            // ─── Indexes ───────────────────────────────────────────────────
            $table->index('prescription_id');
            $table->index('medication_name');
            $table->index('frequency');
            $table->index('total_quantity');
            $table->index('dosage_form');
            $table->index('route');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prescription_items');
    }
};