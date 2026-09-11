<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * PDPO Google decision (18 Jul 2025): cross-border compliance is proven
     * through records of legal basis, safeguards and justification available
     * for inspection - not per-transfer approvals. These columns carry that
     * evidence. Forward-only, additive, all nullable.
     */
    public function up(): void
    {
        Schema::table('data_transfer_records', function (Blueprint $table) {
            $table->text('safeguards')->nullable()->after('adequacy_notes')
                ->comment('Technical/org safeguards for this transfer');
            $table->text('justification')->nullable()->after('safeguards')
                ->comment('Why this transfer is necessary and proportionate');
        });
    }

    public function down(): void
    {
        Schema::table('data_transfer_records', function (Blueprint $table) {
            $table->dropColumn(['safeguards', 'justification']);
        });
    }
};
