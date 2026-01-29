<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('facility_roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code');
            $table->text('description')->nullable();
            $table->boolean('is_system_role')->default(true)->index();
            $table->unsignedBigInteger('facility_id')->nullable();
            $table->foreign('facility_id')->references('id')->on('facilities')->onDelete('cascade');
            $table->timestamps();
            
            // Add composite unique constraint for code + facility_id
            $table->unique(['code', 'facility_id']);
            
            // Optionally add individual indexes if needed for querying
            $table->index('code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facility_roles');
    }
};