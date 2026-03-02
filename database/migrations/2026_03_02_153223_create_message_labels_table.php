<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MESSAGE LABELS
 *
 * Freeform per-user tags on a message (e.g. "urgent", "credentialing").
 * Stored as individual rows so they can be indexed and queried efficiently.
 * A user can apply any label string to any message in their mailbox.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_labels', function (Blueprint $table) {
            $table->id();

            // ── Relations ─────────────────────────────────────────────────
            $table->unsignedBigInteger('message_id');
            $table->foreign('message_id')
                  ->references('id')->on('messages')
                  ->onDelete('cascade');

            $table->unsignedBigInteger('user_id')
                  ->comment('The user who applied this label');
            $table->foreign('user_id')
                  ->references('id')->on('users')
                  ->onDelete('cascade');

            // ── Label value ───────────────────────────────────────────────
            $table->string('label', 100)
                  ->comment('Normalised lowercase label string');

            $table->timestamp('created_at')->useCurrent();

            // ── Uniqueness: one label per user-message pair ───────────────
            $table->unique(['message_id', 'user_id', 'label']);
            $table->index(['user_id', 'label']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_labels');
    }
};
