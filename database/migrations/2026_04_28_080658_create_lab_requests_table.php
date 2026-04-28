<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('lab_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('request_uuid')->unique()->index()->comment('Public-facing identifier');

            // Core relationships (denormalized but controlled)
            $table->unsignedBigInteger('visit_id')->index();
            $table->unsignedBigInteger('patient_id')->index();
            $table->unsignedBigInteger('facility_id')->index();

            // Who requested
            $table->unsignedBigInteger('requested_by_staff_id')->nullable()->index();

            // Classification
            $table->enum('priority', [
                'routine',
                'urgent',
                'stat'
            ])->default('routine')->index();

            $table->enum('status', [
                'pending',
                'in_progress',
                'completed',
                'reviewed',
                'cancelled'
            ])->default('pending')->index();

            // Clinical context
            $table->text('clinical_notes')->nullable()->comment('Reason for ordering tests');
            $table->json('diagnosis_context')->nullable()->comment('ICD codes or suspected conditions');

            // Workflow timestamps
            $table->timestamp('requested_at')->index();
            $table->timestamp('collected_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();

            // Verification
            $table->unsignedBigInteger('reviewed_by_staff_id')->nullable();

            // Cancellation
            $table->text('cancellation_reason')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            // Audit
            $table->timestamps();
            $table->softDeletes();
            $table->unsignedBigInteger('created_by_staff_id')->nullable();
            $table->unsignedBigInteger('updated_by_staff_id')->nullable();
            $table->json('metadata')->nullable();

            // Foreign keys
            $table->foreign('visit_id')->references('id')->on('visits')->onDelete('restrict');
            $table->foreign('patient_id')->references('id')->on('patients')->onDelete('restrict');
            $table->foreign('facility_id')->references('id')->on('facilities')->onDelete('restrict');
            $table->foreign('requested_by_staff_id')->references('id')->on('staff')->onDelete('set null');
            $table->foreign('reviewed_by_staff_id')->references('id')->on('staff')->onDelete('set null');
            $table->foreign('created_by_staff_id')->references('id')->on('staff')->onDelete('set null');
            $table->foreign('updated_by_staff_id')->references('id')->on('staff')->onDelete('set null');

            // Performance indexes
            $table->index(['facility_id', 'status']);
            $table->index(['patient_id', 'requested_at']);
            $table->index(['visit_id', 'status']);
            $table->index(['requested_by_staff_id', 'requested_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('lab_requests');
    }
};