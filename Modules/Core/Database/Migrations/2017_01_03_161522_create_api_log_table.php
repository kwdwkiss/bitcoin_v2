<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateApiLogTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('core_api_log', function (Blueprint $table) {
            $table->increments('id');
            $table->string('url');
            $table->string('host');
            $table->string('action');
            $table->text('params');
            $table->integer('status_code');
            $table->text('data');
            $table->double('start_time', 14, 4);
            $table->double('end_time', 14, 4);
            $table->double('cost_time', 14, 4);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('core_api_log');
    }
}
