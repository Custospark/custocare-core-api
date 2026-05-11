<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('learning_materials', function (Blueprint $table) {
            /** Same pattern as users.profile_photo_path — relative path on the public disk */
            $table->string('thumbnail_path', 512)->nullable()->after('thumbnail_url');
        });
    }

    public function down(): void
    {
        Schema::table('learning_materials', function (Blueprint $table) {
            $table->dropColumn('thumbnail_path');
        });
    }
};
