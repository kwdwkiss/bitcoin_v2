<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateBitFlowTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('bit_flow', function (Blueprint $table) {
            $table->increments('id');

            $table->integer('type');//1-okToHuo 2-huoToOk
            $table->decimal('bid', 10, 4);
            $table->decimal('ask', 10, 4);
            $table->decimal('diff', 10, 4);
            $table->decimal('diff_avg', 10, 4);

            $table->string('s_target');//ok or huo
            $table->bigInteger('s_order_id');
            $table->string('s_type');
            $table->integer('s_status');
            $table->decimal('s_price', 10, 4);
            $table->decimal('s_avg_price', 10, 4);
            $table->decimal('s_amount', 10, 4);
            $table->decimal('s_deal_amount', 10, 4);

            $table->string('b_target');//ok or huo
            $table->bigInteger('b_order_id');
            $table->string('b_type');
            $table->integer('b_status');
            $table->decimal('b_price', 10, 4);
            $table->decimal('b_avg_price', 10, 4);
            $table->decimal('b_amount', 10, 4);
            $table->decimal('b_deal_amount', 10, 4);

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
        Schema::dropIfExists('bit_flow');
    }
}
