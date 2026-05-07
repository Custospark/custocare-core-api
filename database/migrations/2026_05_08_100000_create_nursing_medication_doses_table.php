<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Expected medication administrations for nursing MAR / medication schedule.
 * Rows may be generated from active prescriptions or entered manually.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nursing_medication_doses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('facility_id');
            $table->foreign('facility_id', 'nmd_facility_fk')->references('id')->on('facilities')->onDelete('cascade');

            $table->unsignedBigInteger('visit_id');
            $table->foreign('visit_id', 'nmd_visit_fk')->references('id')->on('visits')->onDelete('cascade');

            $table->unsignedBigInteger('patient_id');
            $table->foreign('patient_id', 'nmd_patient_fk')->references('id')->on('patients')->onDelete('cascade');

            $table->unsignedBigInteger('prescription_id');
            $table->foreign('prescription_id', 'nmd_rx_fk')->references('id')->on('prescriptions')->onDelete('cascade');

            $table->unsignedBigInteger('prescription_item_id');
            $table->foreign('prescription_item_id', 'nmd_rx_item_fk')->references('id')->on('prescription_items')->onDelete('cascade');

            $table->dateTime('scheduled_for')->comment('When this dose is due');

            $table->enum('status', ['pending', 'administered', 'missed', 'skipped'])
                ->default('pending')
                ->index();

            $table->unsignedBigInteger('ward_id')->nullable();
            $table->foreign('ward_id', 'nmd_ward_fk')->references('id')->on('wards')->nullOnDelete();

            $table->text('schedule_notes')->nullable();

            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->foreign('created_by_user_id', 'nmd_created_by_user_fk')->references('id')->on('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['facility_id', 'scheduled_for'], 'nmd_facility_sched_idx');
            $table->index(['facility_id', 'status', 'scheduled_for'], 'nmd_facility_stat_sched_idx');
            $table->index(['visit_id', 'scheduled_for'], 'nmd_visit_sched_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nursing_medication_doses');
    }
};
