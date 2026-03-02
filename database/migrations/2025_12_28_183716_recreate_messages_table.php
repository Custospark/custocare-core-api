<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * MESSAGES — Core message store.
 *
 * A message is created by a sender (draft → sent → delivered).
 * Each message can be a standalone compose, a reply, or a forward.
 * Per-user folder placement and UI state live in message_user_states.
 *
 * Shard key candidate: sender_id (same user's drafts/sent in one shard).
 */
return new class extends Migration
{
    /**
     * Message status enum values.
     */
    private const STATUSES = ['draft', 'scheduled', 'sending', 'sent', 'failed'];
    
    /**
     * Body type enum values.
     */
    private const BODY_TYPES = ['plain', 'html', 'markdown'];
    
    /**
     * Priority enum values.
     */
    private const PRIORITIES = ['low', 'normal', 'high'];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // For fresh installations, we can safely drop
        if (!$this->isFreshInstallation()) {
            $this->handleExistingTables();
        }

        // Disable foreign key checks for safety
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        try {
            // Drop if exists (now safe with disabled checks)
            Schema::dropIfExists('messages');
            
            // Create the new messages table
            $this->createMessagesTable();
            
        } finally {
            // Always re-enable foreign key checks
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Disable foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        try {
            // Drop foreign keys from dependent tables first
            $this->dropDependentForeignKeys();
            
            // Now drop the messages table
            Schema::dropIfExists('messages');
            
        } finally {
            // Always re-enable foreign key checks
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }
    }

    /**
     * Create the messages table with all columns and constraints.
     */
    private function createMessagesTable(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            // ── Identity ─────────────────────────────────────────────────
            $table->id();
            $table->uuid('uuid')->unique()->index()
                  ->comment('Public-facing stable identifier (safe for URLs/APIs)');

            // ── Sender ───────────────────────────────────────────────────
            $table->unsignedBigInteger('sender_id')
                  ->comment('FK → users.id (null = system message)');
            $table->foreign('sender_id')
                  ->references('id')->on('users')
                  ->onDelete('cascade');

            // ── Content ──────────────────────────────────────────────────
            $table->string('subject', 998)->nullable()
                  ->comment('Nullable for in-progress drafts');
            $table->longText('body')
                  ->nullable()
                  ->comment('Plain-text or HTML body depending on body_type');
            $table->enum('body_type', self::BODY_TYPES)
                  ->default('plain')
                  ->comment('Governs how body is rendered on the client');

            // ── Lifecycle status ─────────────────────────────────────────
            $table->enum('status', self::STATUSES)
                  ->default('draft')
                  ->index()
                  ->comment('Overall lifecycle state of the message');

            // ── Priority ─────────────────────────────────────────────────
            $table->enum('priority', self::PRIORITIES)
                  ->default('normal')
                  ->index();

            // ── Scheduling ───────────────────────────────────────────────
            $table->timestamp('scheduled_send_at')->nullable()
                  ->comment('When to dispatch if status=scheduled');
            $table->timestamp('sent_at')->nullable()
                  ->comment('Timestamp when the message transitioned to sent');

            // ── Delivery options ─────────────────────────────────────────
            $table->boolean('read_receipt_requested')->default(false)
                  ->comment('Request the recipient to send a read receipt');
            $table->boolean('delivery_confirmation_requested')->default(false)
                  ->comment('Request a server-level delivery confirmation');

            // ── Thread / Reply chain ──────────────────────────────────────
            $table->unsignedBigInteger('parent_id')->nullable()
                  ->comment('Direct parent message (reply context)');
            $table->foreign('parent_id')
                  ->references('id')->on('messages')
                  ->onDelete('set null');

            $table->unsignedBigInteger('thread_root_id')->nullable()
                  ->comment('Root message of the thread (null = this is the root)');
            $table->foreign('thread_root_id')
                  ->references('id')->on('messages')
                  ->onDelete('set null');

            // ── Composition metrics ───────────────────────────────────────
            $table->unsignedInteger('word_count')->nullable()
                  ->comment('Computed on save; useful for draft completion hints');
            $table->unsignedInteger('character_count')->nullable();

            // ── Auto-save tracking ────────────────────────────────────────
            $table->timestamp('last_auto_saved_at')->nullable()
                  ->comment('Last time the draft was auto-persisted');

            // ── Extensibility ─────────────────────────────────────────────
            $table->json('metadata')->nullable()
                  ->comment('Arbitrary key-value pairs (e.g. client app version)');

            // ── Audit / soft-delete ────────────────────────────────────────
            $table->timestamps();
            $table->softDeletes()
                  ->comment('Permanent deletion; per-user trash uses message_user_states');

            // ── Indexes ────────────────────────────────────────────────────
            $table->index(['sender_id', 'status'], 'messages_sender_status_idx');
            $table->index(['sender_id', 'created_at'], 'messages_sender_created_idx');
            $table->index('scheduled_send_at', 'messages_scheduled_idx');          // scheduler poll
            $table->index('thread_root_id', 'messages_thread_root_idx');
            $table->index('uuid', 'messages_uuid_idx');
        });
    }

    /**
     * Handle existing tables by dropping foreign keys first.
     */
    private function handleExistingTables(): void
    {
        // List of tables that might reference messages
        $dependentTables = [
            'message_receipts',
            'message_attachments',
            'message_labels',
            'message_recipients',
            'message_user_states',
            'message_threads',
            'conversations',
        ];

        foreach ($dependentTables as $table) {
            if (Schema::hasTable($table)) {
                $this->dropForeignKeysFromTable($table, 'messages');
            }
        }
    }

    /**
     * Drop foreign keys from a specific table that reference the messages table.
     */
    private function dropForeignKeysFromTable(string $table, string $referencedTable): void
    {
        try {
            $foreignKeys = $this->getForeignKeys($table, $referencedTable);
            
            foreach ($foreignKeys as $foreignKey) {
                Schema::table($table, function (Blueprint $table) use ($foreignKey) {
                    $table->dropForeign($foreignKey);
                });
            }
        } catch (\Throwable $e) {
            // Log but continue - foreign key might not exist
            echo "Note: Could not process foreign keys for table {$table}: {$e->getMessage()}\n";
        }
    }

    /**
     * Drop all foreign keys from dependent tables.
     */
    private function dropDependentForeignKeys(): void
    {
        $tables = [
            'message_receipts' => 'message_receipts_message_id_foreign',
            'message_attachments' => 'message_attachments_message_id_foreign',
            'message_labels' => 'message_labels_message_id_foreign',
            'message_recipients' => 'message_recipients_message_id_foreign',
            'message_user_states' => 'message_user_states_message_id_foreign',
        ];

        foreach ($tables as $table => $foreignKey) {
            if (Schema::hasTable($table)) {
                try {
                    Schema::table($table, function (Blueprint $table) use ($foreignKey) {
                        $table->dropForeign($foreignKey);
                    });
                } catch (\Throwable $e) {
                    // Foreign key might not exist, continue
                    echo "Note: Could not drop foreign key {$foreignKey} on table {$table}: {$e->getMessage()}\n";
                }
            }
        }
    }

    /**
     * Get foreign keys from a table that reference a specific table.
     */
    private function getForeignKeys(string $table, string $referencedTable): array
    {
        $database = DB::connection()->getDatabaseName();
        $foreignKeys = [];
        
        try {
            $results = DB::select("
                SELECT CONSTRAINT_NAME 
                FROM information_schema.KEY_COLUMN_USAGE 
                WHERE TABLE_SCHEMA = ? 
                AND TABLE_NAME = ? 
                AND REFERENCED_TABLE_SCHEMA = ? 
                AND REFERENCED_TABLE_NAME = ?
            ", [$database, $table, $database, $referencedTable]);

            foreach ($results as $row) {
                $foreignKeys[] = $row->CONSTRAINT_NAME;
            }
        } catch (\Throwable $e) {
            // Information schema query might fail on some DB platforms
            echo "Note: Could not query foreign keys: {$e->getMessage()}\n";
        }

        return $foreignKeys;
    }

    /**
     * Check if this is a fresh installation (no messages table).
     */
    private function isFreshInstallation(): bool
    {
        return !Schema::hasTable('messages');
    }

    /**
     * Get the table dependencies for reference.
     */
    private function getTableDependencies(): array
    {
        return [
            'messages' => [
                'message_receipts',
                'message_attachments',
                'message_labels',
                'message_recipients',
                'message_user_states',
            ],
        ];
    }
};