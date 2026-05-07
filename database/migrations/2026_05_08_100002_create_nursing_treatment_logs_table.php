<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Non-prescription nursing treatments (dressings, wound care, education, etc.).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nursing_treatment_logs', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('facility_id');
            $table->foreign('facility_id', 'ntl_facility_fk')->references('id')->on('facilities')->onDelete('cascade');

            $table->unsignedBigInteger('visit_id');
            $table->foreign('visit_id', 'ntl_visit_fk')->references('id')->on('visits')->onDelete('cascade');

            $table->unsignedBigInteger('patient_id');
            $table->foreign('patient_id', 'ntl_patient_fk')->references('id')->on('patients')->onDelete('cascade');

            $table->unsignedBigInteger('ward_id')->nullable();
            $table->foreign('ward_id', 'ntl_ward_fk')->references('id')->on('wards')->nullOnDelete();

            $table->unsignedBigInteger('logged_by_user_id');
            $table->foreign('logged_by_user_id', 'ntl_logged_by_user_fk')->references('id')->on('users')->onDelete('cascade');

            $table->dateTime('performed_at');

            $table->enum('category', [
                'wound_care',
                'dressing_change',
                'physiotherapy',
                'education',
                'monitoring',
                'comfort_measures',
                'device_care',
                'other',
            ])->default('other')->index();

            $table->string('title', 255);
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['facility_id', 'performed_at'], 'ntl_facility_perf_idx');
            $table->index(['visit_id', 'performed_at'], 'ntl_visit_perf_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nursing_treatment_logs');
    }
};
