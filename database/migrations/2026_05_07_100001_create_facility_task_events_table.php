<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('facility_task_events', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('facility_task_id')->index();
            $table->unsignedBigInteger('actor_user_id')->nullable()->index();

            /**
             * created | assigned | updated | status_changed | completed | cancelled | comment
             */
            $table->string('event_type', 40)->index();

            $table->json('meta')->nullable();

            $table->timestamps();

            $table->foreign('facility_task_id')
                ->references('id')
                ->on('facility_tasks')
                ->cascadeOnDelete();

            $table->foreign('actor_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facility_task_events');
    }
};
