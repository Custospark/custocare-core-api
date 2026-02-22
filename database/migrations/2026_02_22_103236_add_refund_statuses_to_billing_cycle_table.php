<?php

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
        // First, modify the enum to include new statuses
        DB::statement("ALTER TABLE billing_cycles MODIFY COLUMN billing_status ENUM(
            'draft',
            'pending_review',
            'pending_submission',
            'submitted_to_insurance',
            'partially_paid',
            'paid_in_full',
            'payment_plan',
            'collections',
            'disputed',
            'written_off',
            'charity_care',
            'partially_refunded',
            'fully_refunded'
        ) NOT NULL DEFAULT 'draft'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to original enum without refund statuses
        DB::statement("ALTER TABLE billing_cycles MODIFY COLUMN billing_status ENUM(
            'draft',
            'pending_review',
            'pending_submission',
            'submitted_to_insurance',
            'partially_paid',
            'paid_in_full',
            'payment_plan',
            'collections',
            'disputed',
            'written_off',
            'charity_care'
        ) NOT NULL DEFAULT 'draft'");
    }
};