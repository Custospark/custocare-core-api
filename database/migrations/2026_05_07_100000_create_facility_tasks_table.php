<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('facility_tasks', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('facility_id')->index();

            $table->string('title', 200);
            $table->text('description')->nullable();

            $table->enum('category', [
                'patient_care',
                'ward_ops',
                'medication',
                'documentation',
                'clinical_escalation',
                'other',
            ])->default('other')->index();

            $table->enum('priority', ['low', 'normal', 'high', 'urgent'])
                ->default('normal')
                ->index();

            $table->enum('status', ['pending', 'in_progress', 'completed', 'cancelled'])
                ->default('pending')
                ->index();

            $table->timestamp('due_at')->nullable()->index();

            $table->unsignedBigInteger('assigned_to_user_id')->nullable()->index();
            $table->unsignedBigInteger('assigned_by_user_id')->nullable()->index();

            $table->unsignedBigInteger('ward_id')->nullable()->index();

            /** Links optional clinical context (matches visits.visit_uuid) */
            $table->uuid('visit_uuid')->nullable()->index();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable()->index();
            $table->timestamp('cancelled_at')->nullable();

            $table->string('cancellation_reason', 500)->nullable();
            $table->text('completion_notes')->nullable();

            $table->unsignedBigInteger('created_by_user_id')->nullable()->index();
            $table->unsignedBigInteger('updated_by_user_id')->nullable()->index();

            $table->timestamps();

            $table->foreign('facility_id')->references('id')->on('facilities')->cascadeOnDelete();
            $table->foreign('assigned_to_user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('assigned_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('ward_id')->references('id')->on('wards')->nullOnDelete();
            $table->foreign('created_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by_user_id')->references('id')->on('users')->nullOnDelete();

            $table->foreign('visit_uuid')->references('visit_uuid')->on('visits')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facility_tasks');
    }
};
