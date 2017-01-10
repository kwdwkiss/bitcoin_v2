<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateBitTradeTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('bit_trade', function (Blueprint $table) {
            $table->increments('id');

            $table->string('site');//平台 ok huo
            $table->bigInteger('order_id');//平台订单id
            $table->string('type');//buy sell buy_market sell_market
            //ok: -1-已撤销 0-未成交 1-部分成交 2-完全成交 4-撤单处理中
            //huo:0未成交　1部分成交　2已完成　3已取消 4废弃（该状态已不再使用） 5异常 6部分成交已取消 7队列中
            //status:0-未成交 1-部分成交 2-完全成交 3-已撤销 4-撤单处理中 5异常 6部分成交已取消 7队列中
            $table->integer('status');
            $table->decimal('price', 10, 4);//委托价格
            $table->decimal('avg_price', 10, 4);//成交均价
            $table->decimal('amount', 10, 4);//委托数量
            $table->decimal('deal_amount', 10, 4);//成交数量

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
        Schema::dropIfExists('bit_trade');
    }
}
