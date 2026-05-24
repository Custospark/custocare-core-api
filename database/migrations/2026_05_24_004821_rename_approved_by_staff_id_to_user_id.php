<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('payments', 'approved_by_staff_id')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->dropForeign(['approved_by_staff_id']);
                $table->renameColumn('approved_by_staff_id', 'approved_by_user_id');
                $table->foreign('approved_by_user_id')->references('id')->on('users')->nullOnDelete();
            });
        }

        if (Schema::hasColumn('subscriptions', 'approved_by_staff_id')) {
            Schema::table('subscriptions', function (Blueprint $table) {
                $table->dropForeign(['approved_by_staff_id']);
                $table->renameColumn('approved_by_staff_id', 'approved_by_user_id');
                $table->foreign('approved_by_user_id')->references('id')->on('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropForeign(['approved_by_user_id']);
            $table->renameColumn('approved_by_user_id', 'approved_by_staff_id');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['approved_by_user_id']);
            $table->renameColumn('approved_by_user_id', 'approved_by_staff_id');
        });
    }
};
