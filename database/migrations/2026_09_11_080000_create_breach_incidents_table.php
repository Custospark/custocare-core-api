<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Breach incident register (Act Sec 23 + Guidelines Sec 5.7/8.1).
     * Every suspected breach gets a row within the hour; PDPO notification
     * is immediate, subject notification within 24h when high risk.
     * Forward-only, additive.
     */
    public function up(): void
    {
        Schema::create('breach_incidents', function (Blueprint $table) {
            $table->id();
            $table->uuid('incident_uuid')->unique()->index();
            $table->unsignedBigInteger('facility_id')->nullable()->index();
            $table->unsignedBigInteger('reported_by_staff_id')->nullable();
            $table->text('description');
            $table->enum('severity', ['low', 'high'])->default('low')->index()
                ->comment('high = risk to rights/freedoms, triggers 24h subject notice');
            $table->enum('status', ['open', 'contained', 'notified_pdpo', 'subjects_notified', 'closed'])
                ->default('open')->index();
            $table->unsignedInteger('affected_subjects_estimate')->default(0);
            $table->timestamp('detected_at')->index();
            $table->timestamp('pdpo_notified_at')->nullable();
            $table->timestamp('subjects_notified_at')->nullable();
            $table->timestamp('contained_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->text('remediation_notes')->nullable();
            $table->boolean('is_drill')->default(false)->index()
                ->comment('Practice runs; excluded from real-incident counts');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('breach_incidents');
    }
};
