<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('facility_shift_handovers', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('facility_id')->index();
            $table->unsignedBigInteger('ward_id')->nullable()->index();

            /** Calendar date this handover applies to */
            $table->date('shift_date')->index();

            /**
             * morning | afternoon | evening | night | custom
             */
            $table->string('shift_slot', 32)->default('morning')->index();

            /** When slot is custom, label shown in UI */
            $table->string('shift_label', 80)->nullable();

            $table->text('outgoing_summary');

            $table->text('pending_tasks_highlight')->nullable();
            $table->text('incidents_notes')->nullable();
            $table->text('equipment_issues')->nullable();
            $table->text('staffing_notes')->nullable();

            $table->unsignedBigInteger('handed_over_by_user_id')->nullable()->index();
            $table->timestamp('handed_over_at')->nullable()->index();

            $table->unsignedBigInteger('received_by_user_id')->nullable()->index();
            $table->timestamp('acknowledged_at')->nullable();

            $table->enum('status', ['draft', 'submitted', 'acknowledged'])
                ->default('draft')
                ->index();

            $table->unsignedBigInteger('created_by_user_id')->nullable()->index();
            $table->unsignedBigInteger('updated_by_user_id')->nullable()->index();

            $table->timestamps();

            $table->foreign('facility_id')->references('id')->on('facilities')->cascadeOnDelete();
            $table->foreign('ward_id')->references('id')->on('wards')->nullOnDelete();
            $table->foreign('handed_over_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('received_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('created_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by_user_id')->references('id')->on('users')->nullOnDelete();

            $table->index(['facility_id', 'shift_date', 'shift_slot'], 'handover_facility_date_slot_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facility_shift_handovers');
    }
};
