<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ambulance_trip_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained('ambulance_trips')->cascadeOnDelete();

            $table->string('event_type', 40)->comment('status_change, location_update, patient_condition, note, handoff, delay');
            $table->text('description')->nullable();
            $table->timestamp('recorded_at')->useCurrent();
            $table->foreignId('recorded_by_staff_id')->nullable()->constrained('staff')->nullOnDelete();

            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['trip_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ambulance_trip_logs');
    }
};
