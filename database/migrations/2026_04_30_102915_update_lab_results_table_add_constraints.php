<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('lab_results', function (Blueprint $table) {

            $table->unsignedBigInteger('template_field_id')
                ->nullable()
                ->change();

            $table->dropIndex(['lab_request_item_id', 'template_field_id']);

            $table->unique(
                ['lab_request_item_id', 'template_field_id'],
                'lab_results_item_field_unique'
            );
        });
    }

    public function down()
    {
        Schema::table('lab_results', function (Blueprint $table) {

            $table->dropUnique('lab_results_item_field_unique');

            $table->index(['lab_request_item_id', 'template_field_id']);

            $table->unsignedBigInteger('template_field_id')
                ->nullable(false)
                ->change();
        });
    }
};
