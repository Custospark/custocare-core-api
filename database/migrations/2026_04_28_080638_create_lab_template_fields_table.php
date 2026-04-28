<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('lab_template_fields', function (Blueprint $table) {
            $table->id();
            $table->uuid('field_uuid')->unique()->index();

            // Relationship
            $table->unsignedBigInteger('template_id')->index();

            // Field definition
            $table->string('name', 150);
            $table->string('code', 50)->nullable()->index();

            $table->enum('data_type', [
                'number',
                'text',
                'boolean',
                'select'
            ])->index();

            $table->string('unit', 50)->nullable();

            // Reference ranges (default values)
            $table->decimal('reference_min', 12, 4)->nullable();
            $table->decimal('reference_max', 12, 4)->nullable();

            // Display control
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->boolean('is_required')->default(true);
            $table->boolean('is_active')->default(true);

            // Clinical metadata
            $table->boolean('is_critical')->default(false);
            $table->text('clinical_notes')->nullable();

            $table->timestamps();
            $table->softDeletes();
            $table->json('metadata')->nullable();

            // Foreign key
            $table->foreign('template_id')
                ->references('id')
                ->on('lab_templates')
                ->onDelete('cascade');

            // Indexes
            $table->index(['template_id', 'is_active']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('lab_template_fields');
    }
};