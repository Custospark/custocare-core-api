<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * VITALS FORM
 * 
 * Standard clinical vital signs measurements
 * Supports both numeric and qualitative measurements
 * Tracks measurement units and normal ranges automatically
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('vitals');
        Schema::create('vitals', function (Blueprint $table) {
            $table->id();

            // ── Core Relations ──────────────────────────────────────────────
            $table->unsignedBigInteger('facility_id')
                  ->comment('FK → facilities.id');
            $table->foreign('facility_id')
                  ->references('id')->on('facilities')
                  ->onDelete('restrict');

            $table->unsignedBigInteger('visit_id')
                  ->comment('FK → visits.id');
            $table->foreign('visit_id')
                  ->references('id')->on('visits')
                  ->onDelete('cascade');

            $table->unsignedBigInteger('patient_id')
                  ->comment('FK → patients.id');
            $table->foreign('patient_id')
                  ->references('id')->on('patients')
                  ->onDelete('restrict');

            $table->unsignedBigInteger('staff_id')
                  ->comment('FK → staff.id (clinician measuring vitals)');
            $table->foreign('staff_id')
                  ->references('id')->on('staff')
                  ->onDelete('restrict');

            // ── Core Vital Signs ────────────────────────────────────────────
            $table->decimal('temperature', 5, 2)
                  ->nullable()
                  ->comment('Body temperature in configured unit');
            
            $table->string('temperature_unit', 10)
                  ->default('celsius')
                  ->comment('celsius, fahrenheit');
            
            $table->decimal('heart_rate', 5, 2)
                  ->nullable()
                  ->comment('Heart rate in beats per minute');
            
            $table->decimal('respiratory_rate', 5, 2)
                  ->nullable()
                  ->comment('Respiratory rate in breaths per minute');
            
            $table->decimal('systolic_bp', 5, 2)
                  ->nullable()
                  ->comment('Systolic blood pressure in mmHg');
            
            $table->decimal('diastolic_bp', 5, 2)
                  ->nullable()
                  ->comment('Diastolic blood pressure in mmHg');
            
            $table->enum('bp_position', ['sitting', 'standing', 'supine', 'lying'])
                  ->nullable()
                  ->comment('Position when BP was measured');
            
            $table->string('bp_location', 50)
                  ->nullable()
                  ->comment('left_arm, right_arm, left_leg, right_leg');

            // ── Advanced Vitals ─────────────────────────────────────────────
            $table->decimal('oxygen_saturation', 5, 2)
                  ->nullable()
                  ->comment('SpO2 percentage');
            
            $table->integer('oxygen_flow_rate')
                  ->nullable()
                  ->comment('Oxygen flow rate in L/min');
            
            $table->string('oxygen_delivery_device', 100)
                  ->nullable()
                  ->comment('nasal_cannula, mask, non-rebreather, venturi, etc.');
            
            $table->decimal('height', 6, 2)
                  ->nullable()
                  ->comment('Height in cm or inches');
            
            $table->string('height_unit', 10)
                  ->default('cm')
                  ->comment('cm, inches');
            
            $table->decimal('weight', 6, 2)
                  ->nullable()
                  ->comment('Weight in kg or lbs');
            
            $table->string('weight_unit', 10)
                  ->default('kg')
                  ->comment('kg, lbs');
            
            $table->decimal('bmi', 5, 2)
                  ->nullable()
                  ->comment('BMI calculated from height/weight');
            
            $table->decimal('pain_score', 4, 1)
                  ->nullable()
                  ->comment('Pain scale 0-10');
            
            $table->enum('pain_scale_type', ['numeric', 'faces', 'visual_analog'])
                  ->default('numeric')
                  ->comment('Type of pain scale used');
            
            $table->string('pain_location', 200)
                  ->nullable()
                  ->comment('Where pain is located');

            // ── Pediatric Vitals ────────────────────────────────────────────
            $table->decimal('head_circumference', 6, 2)
                  ->nullable()
                  ->comment('Head circumference in cm (pediatric)');
            
            $table->decimal('length', 6, 2)
                  ->nullable()
                  ->comment('Length in cm (pediatric)');

            // ── Measurement Context ─────────────────────────────────────────
            $table->timestamp('measured_at')
                  ->useCurrent()
                  ->comment('When vitals were measured');
            
            $table->string('measurement_method', 100)
                  ->nullable()
                  ->comment('Equipment or method used');
            
            $table->string('device_id', 100)
                  ->nullable()
                  ->comment('ID of monitoring device if applicable');
            
            $table->enum('consciousness_level', ['alert', 'verbal', 'pain', 'unresponsive'])
                  ->nullable()
                  ->comment('AVPU scale');
            
            $table->text('general_appearance')
                  ->nullable()
                  ->comment('General observation notes');

            // ── Custom Fields ───────────────────────────────────────────────
            $table->json('custom_fields')
                  ->nullable()
                  ->comment('Facility-specific vitals measurements: {"field_name": {"value": "...", "unit": "...", "normal_range": "..."}}');
            
            $table->json('percentiles')
                  ->nullable()
                  ->comment('Growth percentiles for pediatric measurements');

            // ── Flagging & Alerts ──────────────────────────────────────────
            $table->json('flag_status')
                  ->nullable()
                  ->comment('{"temperature": "normal|high|low", "bp": "hypertensive", "warning": "critical"}');
            
            $table->text('clinical_alert')
                  ->nullable()
                  ->comment('Auto-generated or manual clinical alert notes');

            $table->timestamps();

            // ── Indexes ────────────────────────────────────────────────────
            $table->index('facility_id');
            $table->index('visit_id');
            $table->index('patient_id');
            $table->index('staff_id');
            $table->index('measured_at');
            $table->index(['patient_id', 'measured_at']);
            $table->index(['visit_id', 'measured_at']);
            $table->index('consciousness_level');
            $table->index(['patient_id', 'measured_at', 'heart_rate']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vitals');
    }
};