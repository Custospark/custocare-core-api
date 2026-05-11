<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hub_support_tickets', function (Blueprint $table) {
            $table->text('admin_internal_notes')->nullable()->after('staff_reply');
        });
    }

    public function down(): void
    {
        Schema::table('hub_support_tickets', function (Blueprint $table) {
            $table->dropColumn('admin_internal_notes');
        });
    }
};
