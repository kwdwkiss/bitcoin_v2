<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateBitAccountTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('bit_account', function (Blueprint $table) {
            $table->increments('id');
            $table->string('site');
            $table->string('name');
            $table->string('email');

            $table->decimal('asset_net', 14, 4);
            $table->decimal('asset_total', 14, 4);

            $table->decimal('borrow_btc', 14, 4);
            $table->decimal('borrow_cny', 14, 4);
            $table->decimal('borrow_ltc', 14, 4);

            $table->decimal('free_btc', 14, 4);
            $table->decimal('free_cny', 14, 4);
            $table->decimal('free_ltc', 14, 4);

            $table->decimal('froze_btc', 14, 4);
            $table->decimal('froze_cny', 14, 4);
            $table->decimal('froze_ltc', 14, 4);

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
        Schema::dropIfExists('bit_account');
    }
}
