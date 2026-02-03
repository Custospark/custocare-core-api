<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('staff_space_assignments', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('staff_id')->index();
            $table->unsignedBigInteger('facility_id')->index();
            $table->unsignedBigInteger('space_id')->index();

            $table->timestamp('assigned_at')->index();
            $table->timestamp('released_at')->nullable()->index();

            $table->unsignedBigInteger('assigned_by_user_id')->nullable()->index();
            $table->unsignedBigInteger('released_by_user_id')->nullable()->index();

            $table->text('note')->nullable(); // optional: "moved to triage", etc.
            $table->timestamps();

            $table->foreign('staff_id')->references('id')->on('staff')->cascadeOnDelete();
            $table->foreign('facility_id')->references('id')->on('facilities')->cascadeOnDelete();
            $table->foreign('space_id')->references('id')->on('facility_spaces')->cascadeOnDelete();

            // One active room assignment per staff in a facility (released_at null => current)
            $table->unique(['staff_id', 'facility_id', 'released_at'], 'uniq_active_space_assignment');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_space_assignments');
    }
};
