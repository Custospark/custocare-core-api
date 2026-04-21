<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CLINICAL TEMPLATES
 * 
 * Pre-configured templates that auto-fill prescription fields
 * Reduces doctor typing and ensures standard care protocols
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('clinical_templates');
        Schema::create('clinical_templates', function (Blueprint $table) {
            $table->id();
            
            // ─── Scope ─────────────────────────────────────────────────────
            $table->unsignedBigInteger('facility_id');
            $table->foreign('facility_id')->references('id')->on('facilities')->onDelete('cascade');
            
            // ─── Template Identity ─────────────────────────────────────────
            $table->string('name', 255)->comment('e.g., "Hypertension Protocol", "Pediatric Fever"');
            $table->string('slug', 255)->unique();
            $table->text('description')->nullable();
            
            // ─── ENUMS: Template Category (UI-friendly) ────────────────────
            $table->enum('category', [
                'General Practice',
                'Emergency Medicine',
                'Pediatrics',
                'Geriatrics',
                'Cardiology',
                'Neurology',
                'Pulmonology',
                'Gastroenterology',
                'Endocrinology',
                'Infectious Diseases',
                'Psychiatry',
                'Obstetrics & Gynecology',
                'Orthopedics',
                'Dermatology',
                'Ophthalmology',
                'Dentistry',
                'Urology',
                'Nephrology',
                'Oncology',
                'Rheumatology',
                'Allergy & Immunology',
                'Sports Medicine',
                'Pain Management',
                'Palliative Care'
            ])->default('General Practice');
            
            // ─── Auto-fill Content ─────────────────────────────────────────
            $table->text('default_diagnosis')->nullable()->comment('Auto-fills the diagnosis field');
            $table->text('default_notes')->nullable()->comment('Auto-fills clinical notes');
            $table->text('patient_instructions')->nullable()->comment('Auto-fills patient instructions');
            
            // ─── Pre-configured Medications (JSON) ─────────────────────────
            $table->json('default_medications')->nullable()->comment('Auto-fills prescription items');
            
            // ─── Usage Tracking ────────────────────────────────────────────
            $table->integer('usage_count')->default(0);
            $table->boolean('is_active')->default(true);
            
            // ─── ENUMS: Visibility ─────────────────────────────────────────
            $table->enum('visibility', [
                'System Wide (All Facilities)',
                'This Facility Only',
                'My Department Only',
                'Only Me (Private)'
            ])->default('This Facility Only');
            
            // ─── Audit ─────────────────────────────────────────────────────
            $table->unsignedBigInteger('created_by')->nullable();
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
            
            $table->timestamps();
            $table->softDeletes();
            
            // ─── Indexes ───────────────────────────────────────────────────
            $table->index(['facility_id', 'category']);
            $table->index('slug');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinical_templates');
    }
};