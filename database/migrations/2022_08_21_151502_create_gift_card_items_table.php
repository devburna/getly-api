<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGiftCardItemsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('gift_card_items', function (Blueprint $table) {
            $table->id()->from(time());
            $table->unsignedBigInteger('gift_card_id');
            $table->longText('link');
            $table->string('name');
            $table->decimal('price', 15, 2);
            $table->unsignedBigInteger('quantity');
            $table->string('image_url');
            $table->softDeletes();
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
        Schema::dropIfExists('gift_card_items');
    }
}
