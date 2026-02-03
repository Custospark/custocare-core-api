<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wards', function (Blueprint $table) {
            $table->id();

            // Facility-scoped
            $table->unsignedBigInteger('facility_id')->index();

            // Enterprise identifiers
            $table->string('code', 50)->nullable(); // e.g. "MED-WD-01" (optional but recommended)
            $table->string('name', 120);            // e.g. "Medical Ward", "Maternity Ward"

            // Ward classification (expandable)
            $table->enum('ward_type', [
                'medical',
                'surgical',
                'maternity',
                'pediatric',
                'icu',
                'nicu',
                'psychiatric',
                'isolation',
                'emergency_observation',
                'general',
            ])->default('general')->index();

            // Physical context (optional: keep flexible strings)
            $table->string('building', 80)->nullable();
            $table->string('floor', 50)->nullable();

            // Operational state
            $table->enum('status', ['active', 'inactive', 'temporarily_closed'])
                ->default('active')
                ->index();

            // Capacity (optional now; useful later for reporting/planning)
            $table->unsignedInteger('capacity_declared')->nullable();     // official capacity
            $table->unsignedInteger('capacity_operational')->nullable();  // usable capacity today

            // Optional restrictions (helps patient assignment rules later)
            $table->enum('sex_restriction', ['mixed', 'male_only', 'female_only'])
                ->default('mixed')
                ->index();

            $table->enum('age_group', ['all', 'adult', 'pediatric', 'neonatal'])
                ->default('all')
                ->index();

            // Notes / audit
            $table->text('note')->nullable();
            $table->unsignedBigInteger('created_by_user_id')->nullable()->index();
            $table->unsignedBigInteger('updated_by_user_id')->nullable()->index();

            $table->timestamps();

            // Constraints
            $table->foreign('facility_id')->references('id')->on('facilities')->cascadeOnDelete();

            // If you have a users table, keep these. If not, remove them.
            $table->foreign('created_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by_user_id')->references('id')->on('users')->nullOnDelete();

            // Uniqueness (facility-scoped)
            $table->unique(['facility_id', 'name'], 'uniq_ward_name_per_facility');
            $table->unique(['facility_id', 'code'], 'uniq_ward_code_per_facility');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wards');
    }
};
