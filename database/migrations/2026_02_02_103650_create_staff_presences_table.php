<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('staff_presences', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('staff_id')->index();
            $table->unsignedBigInteger('facility_id')->index();

            $table->enum('status', [
                'off_duty',
                'on_duty',
                'on_break',
                'busy',
                'unavailable',
            ])->default('off_duty')->index();

            $table->timestamp('started_at')->nullable()->index();
            $table->timestamp('ended_at')->nullable()->index();

            // Who updated it (auditable)
            $table->enum('updated_by', ['system', 'staff', 'admin'])->default('system')->index();
            $table->unsignedBigInteger('updated_by_user_id')->nullable()->index();

            $table->text('note')->nullable(); // optional: "stepped out", "emergency", etc.

            $table->timestamps();

            $table->foreign('staff_id')->references('id')->on('staff')->cascadeOnDelete();
            $table->foreign('facility_id')->references('id')->on('facilities')->cascadeOnDelete();

            // Prevent multiple open presence sessions per staff in same facility
            $table->unique(['staff_id', 'facility_id', 'ended_at'], 'uniq_open_presence_per_facility');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_presences');
    }
};
