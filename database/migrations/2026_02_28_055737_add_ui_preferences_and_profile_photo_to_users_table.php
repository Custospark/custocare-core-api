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
        Schema::table('users', function (Blueprint $table) {
            // ==========================
            // UI / THEME PREFERENCES
            // ==========================
            $table->enum('theme_mode', ['light', 'dark', 'system'])
                ->default('system')
                ->after('metadata');

            $table->enum('ui_density', ['comfortable', 'compact'])
                ->default('comfortable')
                ->after('theme_mode');

            $table->string('timezone', 50)
                ->nullable()
                ->after('ui_density');

            $table->string('locale', 10)
                ->nullable()
                ->after('timezone');

            // ==========================
            // PROFILE PHOTO
            // ==========================
            $table->string('profile_photo_path', 512)
                ->nullable()
                ->after('locale');

            $table->string('profile_photo_disk', 50)
                ->nullable()
                ->after('profile_photo_path');

            $table->timestamp('profile_photo_updated_at')
                ->nullable()
                ->after('profile_photo_disk');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'theme_mode',
                'ui_density',
                'timezone',
                'locale',
                'profile_photo_path',
                'profile_photo_disk',
                'profile_photo_updated_at'
            ]);
        });
    }
};