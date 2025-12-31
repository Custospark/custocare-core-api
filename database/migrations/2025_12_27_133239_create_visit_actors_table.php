<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        /**
         * VISIT_ACTORS - Staff participation in visits
         * Purpose: Track who did what during the visit (for billing & compliance)
         */
        Schema::create('visit_actors', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('facility_id')->index();
            $table->unsignedBigInteger('visit_id')->index();
            $table->unsignedBigInteger('staff_id')->index();
            
            // Role snapshot (frozen at time of participation)
            $table->string('role_at_time', 100)->comment('Snapshot from facility_staff_roles');
            $table->unsignedBigInteger('credential_snapshot_id')->nullable();
            
            // Participation type
            $table->enum('participation_type', [
                'primary_provider',
                'consulting_provider',
                'assisting_provider',
                'supervising_provider',
                'nurse_primary',
                'nurse_assisting',
                'triage_nurse',
                'anesthesiologist',
                'surgical_assistant',
                'pharmacist',
                'technician',
                'therapist',
                'documenting_staff',
                'administrative',
                'observer_trainee'
            ])->index();
            
            // Time involvement
            $table->timestamp('participation_started_at')->index();
            $table->timestamp('participation_ended_at')->nullable()->index();
            $table->unsignedSmallInteger('time_involvement_minutes')->nullable();
            
            // Context
            $table->unsignedBigInteger('department_id_at_time')->nullable();
            $table->json('services_performed')->nullable()->comment('CPT codes performed by this staff');
            $table->json('procedures_assisted')->nullable();
            
            // Billing relevance
            $table->boolean('is_billable_provider')->default(false);
            $table->decimal('provider_charge_amount', 10, 2)->nullable();
            
            // Quality & teaching
            $table->boolean('is_teaching_case')->default(false);
            $table->unsignedBigInteger('supervising_staff_id')->nullable();
            
            // Audit
            $table->timestamps();
            $table->json('metadata')->nullable();
            
            // Foreign keys
            $table->foreign('visit_id')->references('id')->on('visits')->onDelete('cascade');
            $table->foreign('staff_id')->references('id')->on('staff')->onDelete('restrict');
            $table->foreign('credential_snapshot_id')->references('id')->on('staff_credentials')->onDelete('set null');
            
            // Prevent duplicate participation records
            $table->unique(['visit_id', 'staff_id', 'participation_type', 'participation_started_at'],'visit_staff_part_unique');
            
            // Performance indexes
            $table->index(['facility_id', 'staff_id', 'participation_started_at'],'fac_staff_part_start_unique');
            $table->index(['staff_id', 'participation_started_at']);
            $table->index(['visit_id', 'participation_type']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('visit_actors');
    }
};