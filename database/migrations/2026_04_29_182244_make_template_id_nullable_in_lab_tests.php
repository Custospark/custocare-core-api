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
        // First, drop the existing foreign key constraint
        Schema::table('lab_tests', function (Blueprint $table) {
            // Drop the foreign key constraint (the name might be different, check your database)
            $table->dropForeign(['template_id']);
            
            // Alternative if you know the constraint name:
            // $table->dropForeign('lab_tests_template_id_foreign');
        });

        // Then modify the column to be nullable
        Schema::table('lab_tests', function (Blueprint $table) {
            $table->unsignedBigInteger('template_id')->nullable()->change();
        });

        // Re-add the foreign key constraint with ON DELETE SET NULL
        Schema::table('lab_tests', function (Blueprint $table) {
            $table->foreign('template_id')
                ->references('id')
                ->on('lab_templates')
                ->onDelete('set null');  // Changed from 'restrict' to 'set null'
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop the foreign key constraint
        Schema::table('lab_tests', function (Blueprint $table) {
            $table->dropForeign(['template_id']);
        });

        // Make template_id not nullable again
        Schema::table('lab_tests', function (Blueprint $table) {
            $table->unsignedBigInteger('template_id')->nullable(false)->change();
        });

        // Re-add the foreign key constraint with original behavior
        Schema::table('lab_tests', function (Blueprint $table) {
            $table->foreign('template_id')
                ->references('id')
                ->on('lab_templates')
                ->onDelete('restrict');
        });
    }
};