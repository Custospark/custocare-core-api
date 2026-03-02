<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MESSAGE USER STATES
 *
 * This is the central per-user, per-message state table.
 * It answers: "Where does this message live for THIS user, and what UI state
 * has the user applied to it?"
 *
 * How folders work
 * ────────────────
 *   • When a message is SENT:
 *       sender  → folder = 'sent'
 *       each recipient → folder = 'inbox'
 *   • When a draft is saved:
 *       sender  → folder = 'drafts'
 *   • When a user ARCHIVES:
 *       folder changes to 'archive'  (original_folder preserved)
 *   • When a user DELETES (moves to Trash):
 *       original_folder = current folder value
 *       folder          = 'trash'
 *       trashed_at      = now()
 *       trash_expires_at= now() + 30 days  (auto-purge window)
 *   • When user RESTORES from Trash:
 *       folder = original_folder
 *       original_folder = null
 *       trashed_at = null, trash_expires_at = null
 *
 * Unique constraint on (message_id, user_id) ensures one row per pair.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_user_states', function (Blueprint $table) {
            $table->id();

            // ── Relations ─────────────────────────────────────────────────
            $table->unsignedBigInteger('message_id');
            $table->foreign('message_id')
                  ->references('id')->on('messages')
                  ->onDelete('cascade');

            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')
                  ->references('id')->on('users')
                  ->onDelete('cascade');

            // ── Folder placement ──────────────────────────────────────────
            $table->enum('folder', ['inbox', 'sent', 'drafts', 'archive', 'trash'])
                  ->default('inbox')
                  ->comment('Current mailbox folder for this user');

            $table->enum('original_folder', ['inbox', 'sent', 'drafts', 'archive'])
                  ->nullable()
                  ->comment('Folder before trash move; used by restore operation');

            // ── Read state ────────────────────────────────────────────────
            $table->boolean('is_read')->default(false)->index();
            $table->timestamp('read_at')->nullable();

            // ── Star / bookmark ────────────────────────────────────────────
            $table->boolean('is_starred')->default(false)->index();
            $table->timestamp('starred_at')->nullable();

            // ── Archive flag (redundant with folder=archive but handy) ─────
            $table->boolean('is_archived')->default(false);
            $table->timestamp('archived_at')->nullable();

            // ── Trash lifecycle ────────────────────────────────────────────
            $table->timestamp('trashed_at')->nullable()
                  ->comment('When the user moved this to trash');
            $table->timestamp('trash_expires_at')->nullable()
                  ->comment('Auto-purge date; null = will not auto-purge');

            $table->timestamps();

            // ── Constraints & indexes ──────────────────────────────────────
            $table->unique(['message_id', 'user_id'])
                  ->comment('One state row per user-message pair');
            $table->index(['user_id', 'folder']);
            $table->index(['user_id', 'folder', 'is_read']);
            $table->index(['user_id', 'folder', 'is_starred']);
            $table->index('trash_expires_at');          // scheduler cleanup job
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_user_states');
    }
};
