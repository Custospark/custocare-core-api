<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Record of medication administered by nursing (MAR documentation).
 * Links optionally to a scheduled dose row; supports PRN / ad-hoc documentation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nursing_medication_administrations', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('nursing_medication_dose_id')->nullable();
            $table->foreign('nursing_medication_dose_id', 'nma_dose_fk')
                ->references('id')->on('nursing_medication_doses')->nullOnDelete();

            $table->unsignedBigInteger('facility_id');
            $table->foreign('facility_id', 'nma_facility_fk')->references('id')->on('facilities')->onDelete('cascade');

            $table->unsignedBigInteger('visit_id');
            $table->foreign('visit_id', 'nma_visit_fk')->references('id')->on('visits')->onDelete('cascade');

            $table->unsignedBigInteger('prescription_item_id');
            $table->foreign('prescription_item_id', 'nma_rx_item_fk')->references('id')->on('prescription_items')->onDelete('cascade');

            $table->unsignedBigInteger('administered_by_user_id');
            $table->foreign('administered_by_user_id', 'nma_admin_by_user_fk')->references('id')->on('users')->onDelete('cascade');

            $table->dateTime('administered_at');

            $table->enum('outcome', ['given', 'partial', 'refused', 'held', 'omitted'])->default('given');

            $table->decimal('quantity_given', 10, 3)->nullable();
            $table->string('quantity_unit', 64)->nullable();

            $table->text('notes')->nullable();
            $table->text('refusal_or_omission_reason')->nullable();

            $table->timestamps();

            $table->index(['facility_id', 'administered_at'], 'nma_facility_admin_at_idx');
            $table->index(['visit_id', 'administered_at'], 'nma_visit_admin_at_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nursing_medication_administrations');
    }
};
