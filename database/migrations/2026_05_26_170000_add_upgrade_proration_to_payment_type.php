<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE payments MODIFY COLUMN payment_type ENUM(
            'onboarding',
            'subscription',
            'renewal',
            'upgrade_proration'
        ) NOT NULL COMMENT 'What this payment covers'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE payments MODIFY COLUMN payment_type ENUM(
            'onboarding',
            'subscription',
            'renewal'
        ) NOT NULL COMMENT 'What this payment covers'");
    }
};
