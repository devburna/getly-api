<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGetlistsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('getlists', function (Blueprint $table) {
            $table->id()->from(time());
            $table->unsignedBigInteger('user_id');
            $table->string('title');
            $table->date('event_date');
            $table->longText('message')->nullable();
            $table->boolean('privacy')->default(false);
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
        Schema::dropIfExists('getlists');
    }
}
