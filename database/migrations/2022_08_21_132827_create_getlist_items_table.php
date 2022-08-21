<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGetlistItemsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('getlist_items', function (Blueprint $table) {
            $table->id()->from(time());
            $table->unsignedBigInteger('getlist_id');
            $table->string('name');
            $table->decimal('price', 15, 2);
            $table->unsignedBigInteger('quantity');
            $table->longText('details');
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
        Schema::dropIfExists('getlist_items');
    }
}
