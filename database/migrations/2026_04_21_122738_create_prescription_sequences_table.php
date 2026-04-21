<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prescription_sequences', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('facility_id');
            $table->integer('year');
            $table->integer('month');
            $table->integer('last_number')->default(0);
            $table->timestamps();
            
            $table->unique(['facility_id', 'year', 'month']);
            $table->foreign('facility_id')->references('id')->on('facilities')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prescription_sequences');
    }
};