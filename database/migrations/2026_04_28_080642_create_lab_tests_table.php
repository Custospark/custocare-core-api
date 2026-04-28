<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
{        Schema::dropIfExists('lab_tests');
        Schema::create('lab_tests', function (Blueprint $table) {
            $table->id();
            $table->uuid('test_uuid')->unique()->index();

            // Basic info
            $table->string('name', 150)->index();
            $table->string('code', 50)->nullable()->index()->comment('Lab internal code');

            // Template linkage
            $table->unsignedBigInteger('template_id')->index();

            // Scope control (global vs facility-specific)
            $table->unsignedBigInteger('facility_id')->nullable()->index();
            $table->boolean('is_shared')->default(true)->index();

            // Categorization
            $table->string('category', 100)->nullable()->index()->comment('Hematology, Biochemistry, etc.');
            $table->text('description')->nullable();

            // Operational flags
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('requires_fasting')->default(false);
            $table->unsignedSmallInteger('turnaround_time_hours')->nullable();

            // Audit
            $table->timestamps();
            $table->softDeletes();
            $table->json('metadata')->nullable();

            // Foreign keys
            $table->foreign('template_id')
                ->references('id')
                ->on('lab_templates')
                ->onDelete('restrict');

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
        Schema::dropIfExists('lab_tests');
    }
};