<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PLANS
 *
 * Platform-level subscription plans sold to healthcare facilities.
 * No app_id — plans are global to the Custocare platform.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('plans');
        Schema::create('plans', function (Blueprint $table) {
            $table->id();

            // ── Identity ──────────────────────────────────────────────────
            $table->string('name', 100)
                  ->comment('e.g. Essential, Professional, Enterprise');
            $table->string('slug', 100)->unique()
                  ->comment('URL-friendly identifier, e.g. essential');

            $table->text('description')->nullable();

            // ── Pricing ───────────────────────────────────────────────────
            $table->decimal('price_usd', 10, 2)->default(0.00)
                  ->comment('Monthly price in USD');
            $table->decimal('price_ugx', 14, 2)->default(0.00)
                  ->comment('Monthly price in Ugandan Shillings');

            // ── One-time onboarding fee ───────────────────────────────────
            $table->decimal('onboarding_fee_usd', 10, 2)->default(0.00);
            $table->decimal('onboarding_fee_ugx', 14, 2)->default(0.00);

            // ── Billing configuration ──────────────────────────────────────
            $table->enum('billing_cycle', ['monthly'])->default('monthly')
                  ->comment('Billing frequency; yearly can be added later');
            $table->unsignedTinyInteger('trial_days')->default(7)
                  ->comment('Free trial length; subscription starts as trial');

            // ── Feature limits ────────────────────────────────────────────
            $table->json('features')->nullable()
                  ->comment('Feature flags and limits as key-value pairs');
            $table->unsignedInteger('max_staff')->nullable()
                  ->comment('Max staff accounts; null = unlimited');
            $table->unsignedInteger('max_departments')->nullable();
            $table->unsignedInteger('max_patients_per_month')->nullable();

            // ── Display controls ──────────────────────────────────────────
            $table->unsignedTinyInteger('sort_order')->default(0)
                  ->comment('Display order on pricing page');
            $table->boolean('is_popular')->default(false)
                  ->comment('Highlighted as the recommended plan');
            $table->boolean('is_active')->default(true)
                  ->comment('Inactive plans cannot be subscribed to');

            $table->json('metadata')->nullable();
            $table->timestamps();

            // ── Indexes ───────────────────────────────────────────────────
            $table->index('is_active');
            $table->index('sort_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
