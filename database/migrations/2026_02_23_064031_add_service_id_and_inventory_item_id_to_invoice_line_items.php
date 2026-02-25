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
        Schema::table('invoice_line_items', function (Blueprint $table) {
            // First, modify existing service_code to be nullable
            // (assuming it currently exists and is NOT NULL)
            $table->string('service_code')->nullable()->change();
            
            // Add new foreign key columns (both nullable)
            $table->unsignedBigInteger('service_catalog_id')->nullable()->after('service_code');
            $table->unsignedBigInteger('inventory_item_id')->nullable()->after('service_catalog_id');
            
            // Add foreign key constraints
            $table->foreign('service_catalog_id')
                  ->references('id')
                  ->on('service_catalogs')
                  ->onDelete('set null');
            
            $table->foreign('inventory_item_id')
                  ->references('id')
                  ->on('inventory_items')
                  ->onDelete('set null');
            
            // Optional: Add an index for better query performance
            $table->index('service_catalog_id');
            $table->index('inventory_item_id');
            
            // Optional: Add a composite index if you often query by both
            // $table->index(['service_catalog_id', 'inventory_item_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoice_line_items', function (Blueprint $table) {
            // Drop foreign key constraints first
            $table->dropForeign(['service_catalog_id']);
            $table->dropForeign(['inventory_item_id']);
            
            // Drop indexes
            $table->dropIndex(['service_catalog_id']);
            $table->dropIndex(['inventory_item_id']);
            
            // Drop the columns
            $table->dropColumn(['service_catalog_id', 'inventory_item_id']);
            
            // Revert service_code to NOT NULL (if it was originally NOT NULL)
            // Adjust this based on your original schema
            $table->string('service_code')->nullable(false)->change();
        });
    }
};