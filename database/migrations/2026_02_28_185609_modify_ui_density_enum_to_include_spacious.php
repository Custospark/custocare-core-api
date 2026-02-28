<?php
// database/migrations/2024_01_01_000000_add_ui_density_to_users_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // If the column doesn't exist yet (fresh install)
        if (!Schema::hasColumn('users', 'ui_density')) {
            Schema::table('users', function (Blueprint $table) {
                $table->enum('ui_density', ['compact', 'comfortable', 'spacious'])
                      ->default('comfortable')
                      ->after('theme_mode'); // Adjust 'after' based on your table structure
            });
        } 
        // If the column exists but needs to be modified to add 'spacious'
        else {
            // For MySQL, we need to use raw SQL to modify an enum
            DB::statement("ALTER TABLE users MODIFY COLUMN ui_density ENUM('compact', 'comfortable', 'spacious') DEFAULT 'comfortable'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Check current values to decide rollback strategy
        $hasSpacious = DB::table('users')->where('ui_density', 'spacious')->exists();
        
        if ($hasSpacious) {
            // If there are users with 'spacious', we need to decide how to handle them
            // Option 1: Convert 'spacious' to 'comfortable' (safe default)
            DB::table('users')->where('ui_density', 'spacious')->update(['ui_density' => 'comfortable']);
            
            // Then revert the column
            DB::statement("ALTER TABLE users MODIFY COLUMN ui_density ENUM('compact', 'comfortable') DEFAULT 'comfortable'");
        } else {
            // No 'spacious' values, safe to revert directly
            DB::statement("ALTER TABLE users MODIFY COLUMN ui_density ENUM('compact', 'comfortable') DEFAULT 'comfortable'");
        }
    }
};