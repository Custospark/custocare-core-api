<?php

declare(strict_types=1);

use App\Services\Message\MessageBodyCipher;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            if (!Schema::hasColumn('messages', 'body_encrypted')) {
                $table->longText('body_encrypted')->nullable()->after('body');
            }
        });

        DB::table('messages')
            ->whereNotNull('body')
            ->where('body', '!=', '')
            ->whereNull('body_encrypted')
            ->orderBy('id')
            ->chunkById(100, function ($rows): void {
                foreach ($rows as $row) {
                    DB::table('messages')
                        ->where('id', $row->id)
                        ->update([
                            'body_encrypted' => MessageBodyCipher::encrypt((string) $row->body),
                            'body'           => null,
                        ]);
                }
            });
    }

    public function down(): void
    {
        DB::table('messages')
            ->whereNotNull('body_encrypted')
            ->orderBy('id')
            ->chunkById(100, function ($rows): void {
                foreach ($rows as $row) {
                    $plain = MessageBodyCipher::decrypt($row->body_encrypted);
                    if ($plain === null) {
                        continue;
                    }

                    DB::table('messages')
                        ->where('id', $row->id)
                        ->update([
                            'body'           => $plain,
                            'body_encrypted' => null,
                        ]);
                }
            });

        Schema::table('messages', function (Blueprint $table) {
            if (Schema::hasColumn('messages', 'body_encrypted')) {
                $table->dropColumn('body_encrypted');
            }
        });
    }
};
