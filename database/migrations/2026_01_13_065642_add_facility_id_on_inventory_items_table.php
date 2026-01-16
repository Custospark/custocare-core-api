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
        Schema::table('inventory_items', function (Blueprint $table) {
            // Ensure column exists before adding FK
            if (!Schema::hasColumn('inventory_items', 'facility_id')) {
                $table->unsignedBigInteger('facility_id')->after('id');
            }

            $table->foreign('facility_id')
                ->references('id')
                ->on('facilities')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            // Drop FK first (important)
            $table->dropForeign(['facility_id']);

            // Then drop column if needed
            $table->dropColumn('facility_id');
        });
    }
};
