<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
      Schema::create('facility_walkin_customers', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('facility_id')->unique()->index();
        $table->unsignedBigInteger('system_user_id')->index();   // global system user
        $table->unsignedBigInteger('patient_id')->unique()->index(); // facility-specific patient
        $table->timestamps();

        $table->foreign('facility_id')->references('id')->on('facilities')->onDelete('cascade');
        $table->foreign('system_user_id')->references('id')->on('users')->onDelete('restrict');
        $table->foreign('patient_id')->references('id')->on('patients')->onDelete('cascade');
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('facility_walkin_customers');
    }
};
