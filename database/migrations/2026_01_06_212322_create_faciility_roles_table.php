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
            $table->string('code')->unique()->index();
            $table->text('description')->nullable();
            $table->boolean('is_system_role')->default(true)->index();
            $table->unsignedBigInteger('facility_id')->nullable();
            $table->foreign('facility_id')->references('id')->on('facilities')->onDelete('cascade');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facility_roles');
    }
};
