<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('facility_spaces', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('facility_id')->index();

            $table->string('name'); // "Room 203", "Consultation 1"
            $table->enum('type', ['consultation', 'triage', 'lab', 'theatre', 'ward','pharmacy'])->index();

            $table->string('floor')->nullable();    // flexible: "2", "Ground", "B1"
            $table->string('building')->nullable(); // flexible: "Main", "Annex A"

            $table->boolean('is_active')->default(true)->index();

            $table->timestamps();

            $table->foreign('facility_id')->references('id')->on('facilities')->cascadeOnDelete();

            // Prevent duplicate space names within the same facility (optional but recommended)
            $table->unique(['facility_id', 'name'], 'uniq_space_name_per_facility');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facility_spaces');
    }
};
