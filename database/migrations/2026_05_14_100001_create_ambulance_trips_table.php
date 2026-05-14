<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ambulance_trips', function (Blueprint $table) {
            $table->id();
            $table->string('trip_uuid')->unique()->index();

            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('visit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ambulance_id')->nullable()->constrained()->nullOnDelete();

            $table->foreignId('dispatch_staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->foreignId('requesting_staff_id')->nullable()->constrained('staff')->nullOnDelete();

            $table->string('trip_type', 40)->index()->comment('emergency, non_emergency, inter_facility_transfer, standby, special_event');
            $table->string('priority', 20)->default('medium')->comment('low, medium, high, urgent');
            $table->string('status', 30)->default('requested')->index()->comment('requested, dispatched, en_route, on_scene, transporting, at_destination, completed, cancelled');

            $table->text('pickup_location')->nullable();
            $table->foreignId('pickup_facility_id')->nullable()->constrained('facilities')->nullOnDelete();
            $table->text('destination_location')->nullable();
            $table->foreignId('destination_facility_id')->nullable()->constrained('facilities')->nullOnDelete();

            $table->text('dispatch_notes')->nullable();
            $table->text('trip_notes')->nullable();

            $table->decimal('mileage', 8, 2)->nullable()->unsigned();
            $table->unsignedInteger('estimated_duration_minutes')->nullable();

            // Timeline timestamps
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('en_route_at')->nullable();
            $table->timestamp('on_scene_at')->nullable();
            $table->timestamp('patient_contact_at')->nullable();
            $table->timestamp('depart_scene_at')->nullable();
            $table->timestamp('at_destination_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancellation_reason')->nullable();

            $table->json('metadata')->nullable();

            $table->foreignId('created_by_staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->foreignId('updated_by_staff_id')->nullable()->constrained('staff')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['facility_id', 'status']);
            $table->index(['patient_id', 'status']);
            $table->index(['ambulance_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ambulance_trips');
    }
};
