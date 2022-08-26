<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('mono_account_holders', function (Blueprint $table) {
            $table->id()->from(time());
            $table->unsignedBigInteger('user_id')->unique();
            $table->string('identity')->unique();
            $table->string('meta');
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
        Schema::dropIfExists('mono_account_holders');
    }
};
