<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateBitDepthTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('bit_depth', function (Blueprint $table) {
            $table->increments('id');

            $table->decimal('okDiff', 10, 4);
            $table->decimal('huoDiff', 10, 4);
            $table->decimal('okAsk', 10, 4);
            $table->decimal('okBid', 10, 4);
            $table->decimal('huoAsk', 10, 4);
            $table->decimal('huoBid', 10, 4);

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
        Schema::dropIfExists('bit_depth');
    }
}
