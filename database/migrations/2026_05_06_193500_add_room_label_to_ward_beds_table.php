<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ward_beds', function (Blueprint $table) {
            $table->string('room_label', 50)->nullable()->after('ward_id');
            $table->index(['ward_id', 'room_label']);
        });
    }

    public function down(): void
    {
        Schema::table('ward_beds', function (Blueprint $table) {
            $table->dropIndex(['ward_id', 'room_label']);
            $table->dropColumn('room_label');
        });
    }
};

