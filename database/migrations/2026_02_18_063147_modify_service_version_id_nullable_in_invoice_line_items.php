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
        // First drop the foreign key constraint
        Schema::table('invoice_line_items', function (Blueprint $table) {
            $table->dropForeign(['service_version_id']);
        });

        // Then modify the column to be nullable
        Schema::table('invoice_line_items', function (Blueprint $table) {
            $table->unsignedBigInteger('service_version_id')->nullable()->change();
        });

        // Re-add the foreign key constraint with SET NULL on delete
        Schema::table('invoice_line_items', function (Blueprint $table) {
            $table->foreign('service_version_id')
                  ->references('id')
                  ->on('service_versions')
                  ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop the foreign key constraint with SET NULL
        Schema::table('invoice_line_items', function (Blueprint $table) {
            $table->dropForeign(['service_version_id']);
        });

        // Change back to NOT NULL
        Schema::table('invoice_line_items', function (Blueprint $table) {
            $table->unsignedBigInteger('service_version_id')->nullable(false)->change();
        });

        // Re-add original foreign key with RESTRICT
        Schema::table('invoice_line_items', function (Blueprint $table) {
            $table->foreign('service_version_id')
                  ->references('id')
                  ->on('service_versions')
                  ->onDelete('restrict');
        });
    }
};