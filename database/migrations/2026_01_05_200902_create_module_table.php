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
        Schema::create('modules', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique()->index()->comment('Unique module code e.g., clinical, billing');
            $table->string('name')->comment('Human-readable module name');
            $table->text('description')->nullable()->comment('Optional description of module');
            $table->boolean('is_active')->default(true)->comment('Enable or disable module globally');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('modules');
    }
};
