<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('lab_templates', function (Blueprint $table) {
            $table->id();
            $table->uuid('template_uuid')->unique()->index();

            // Basic info
            $table->string('name', 150)->index();
            $table->text('description')->nullable();

            // Scope
            $table->unsignedBigInteger('facility_id')->nullable()->index();
            $table->boolean('is_shared')->default(true)->index();

            // Structure type
            $table->enum('structure_type', [
                'standard',   // field-based (most lab tests)
                'simple',     // single value tests
                'panel'       // grouped tests
            ])->default('standard')->index();

            // Metadata
            $table->boolean('is_active')->default(true)->index();

            $table->timestamps();
            $table->softDeletes();
            $table->json('metadata')->nullable();

            // Foreign key
            $table->foreign('facility_id')
                ->references('id')
                ->on('facilities')
                ->onDelete('cascade');

            // Indexes
            $table->index(['name', 'is_active']);
            $table->index(['facility_id', 'is_active']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('lab_templates');
    }
};