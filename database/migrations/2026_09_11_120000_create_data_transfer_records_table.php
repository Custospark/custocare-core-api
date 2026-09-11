<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cross-border transfer register (Guidelines Sec 5.4, Act Sec 19).
     * No offshore storage without PDPO authorisation + adequacy proof +
     * MoH written consent. Every transfer - or its explicit prohibition -
     * is recorded here. Forward-only, additive.
     */
    public function up(): void
    {
        Schema::create('data_transfer_records', function (Blueprint $table) {
            $table->id();
            $table->uuid('transfer_uuid')->unique()->index();
            $table->string('destination_country', 100)->index();
            $table->string('recipient', 255)->comment('Vendor/processor receiving data');
            $table->text('data_categories')->comment('What crosses the border');
            $table->enum('legal_basis', ['adequacy', 'consent', 'prohibited'])->index()
                ->comment('adequacy = equivalent measures; consent = subject consent; prohibited = blocked');
            $table->text('adequacy_notes')->nullable()
                ->comment('Why the destination is deemed equivalent, or why blocked');
            $table->string('pdpo_authorisation_ref', 255)->nullable();
            $table->boolean('moh_consent')->default(false);
            $table->enum('status', ['pending_review', 'authorized', 'blocked'])->default('pending_review')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_transfer_records');
    }
};
