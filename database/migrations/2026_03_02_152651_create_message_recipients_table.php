<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MESSAGE RECIPIENTS
 *
 * Stores To / CC / BCC addressing for each message.
 * Supports both internal users (user_id filled) and external email addresses.
 * Also tracks per-recipient delivery and read status for receipts.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_recipients', function (Blueprint $table) {
            $table->id();

            // ── Relation to message ───────────────────────────────────────
            $table->unsignedBigInteger('message_id');
            $table->foreign('message_id')
                  ->references('id')->on('messages')
                  ->onDelete('cascade');

            // ── Recipient identity ────────────────────────────────────────
            $table->unsignedBigInteger('user_id')->nullable()
                  ->comment('FK → users.id when recipient is an internal user');
            $table->foreign('user_id')
                  ->references('id')->on('users')
                  ->onDelete('set null');

            $table->string('name', 255)->nullable()
                  ->comment('Display name; copied at send-time for immutability');
            $table->string('email', 255)
                  ->comment('Normalised email address');

            // ── Recipient type ────────────────────────────────────────────
            $table->enum('type', ['to', 'cc', 'bcc'])
                  ->default('to')
                  ->comment('Addressing field this recipient belongs to');

            // ── Per-recipient delivery tracking ───────────────────────────
            $table->enum('delivery_status', ['pending', 'sent', 'delivered', 'failed', 'read'])
                  ->default('pending')
                  ->index();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable()
                  ->comment('Set when recipient opens the message (read-receipt)');

            $table->timestamps();

            // ── Indexes ───────────────────────────────────────────────────
            $table->index(['message_id', 'type']);
            $table->index(['user_id', 'delivery_status']);
            $table->index('email');         // external recipient lookup
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_recipients');
    }
};
