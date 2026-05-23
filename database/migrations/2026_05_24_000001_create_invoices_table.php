<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('subscription_id');
            $table->foreign('subscription_id')
                  ->references('id')->on('subscriptions')
                  ->onDelete('restrict');

            $table->unsignedBigInteger('facility_id');
            $table->foreign('facility_id')
                  ->references('id')->on('facilities')
                  ->onDelete('restrict');

            $table->string('invoice_number', 50)->unique()
                  ->comment('Human-readable invoice number, e.g. INV-2026-0001');

            $table->enum('invoice_type', [
                'subscription',
                'renewal',
                'onboarding',
                'adjustment',
            ])->default('subscription');

            $table->enum('status', [
                'paid',
                'unpaid',
                'overdue',
                'partially_paid',
                'cancelled',
                'refunded',
            ])->default('unpaid')->index();

            $table->decimal('amount', 14, 2);
            $table->string('currency', 3)->default('UGX');
            $table->decimal('paid_amount', 14, 2)->default(0.00)
                  ->comment('Cumulative amount paid toward this invoice');

            $table->text('description')->nullable();
            $table->json('line_items')->nullable()
                  ->comment('Array of { description, quantity, unit_price, total }');

            $table->date('issued_at');
            $table->date('due_at');
            $table->date('paid_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->timestamps();

            $table->index(['facility_id', 'status']);
            $table->index(['subscription_id', 'status']);
            $table->index('due_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
