<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ambulances', function (Blueprint $table) {
            $table->id();
            $table->string('ambulance_uuid')->unique()->index();

            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->foreignId('crew_team_lead_staff_id')->nullable()->constrained('staff')->nullOnDelete();

            $table->string('vehicle_identifier', 50)->unique()->comment('License plate or fleet number');
            $table->string('vehicle_type', 50)->index()->comment('bls, als, critical_care, patient_transport, type_i, type_ii, type_iii, medium_duty, specialty, other');
            $table->string('equipment_level', 50)->nullable();

            $table->string('status', 30)->default('available')->index()->comment('available, in_service, out_of_service, maintenance, decommissioned');

            $table->date('last_service_date')->nullable();
            $table->date('next_service_due_date')->nullable();
            $table->unsignedInteger('current_mileage')->default(0);
            $table->unsignedTinyInteger('capacity')->default(1)->comment('Max patient capacity');

            $table->json('features')->nullable()->comment('Onboard equipment list');
            $table->json('metadata')->nullable();

            $table->foreignId('created_by_staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->foreignId('updated_by_staff_id')->nullable()->constrained('staff')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['facility_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ambulances');
    }
};
