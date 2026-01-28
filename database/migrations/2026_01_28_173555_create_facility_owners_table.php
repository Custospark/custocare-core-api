<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('facility_owners', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('facility_id')->index();
            $table->unsignedBigInteger('staff_id')->index();

            $table->boolean('is_primary_owner')->default(false)->index();

            $table->timestamps();

            // A staff member can own a facility only once
            $table->unique(['facility_id', 'staff_id']);

            // Optional: only one primary owner per facility
            $table->unique(['facility_id'], 'unique_primary_owner_per_facility')
                  ->where('is_primary_owner', true);

            // Foreign keys
            $table->foreign('facility_id')
                  ->references('id')
                  ->on('facilities')
                  ->cascadeOnDelete();

            $table->foreign('staff_id')
                  ->references('id')
                  ->on('staff')
                  ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facility_owners');
    }
};
