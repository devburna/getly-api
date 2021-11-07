<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGiftsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('gifts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('getlist_id')->default(0);
            $table->string('name');
            $table->unsignedDouble('price', null, 2);
            $table->unsignedBigInteger('quantity');
            $table->longText('short_message')->nullable();
            $table->string('image')->nullable();
            $table->string('link')->nullable();
            $table->string('receiver_name');
            $table->string('receiver_email');
            $table->unsignedBigInteger('receiver_phone');
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
        Schema::dropIfExists('gifts');
    }
}
