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
        Schema::create('virtual_cards', function (Blueprint $table) {
            $table->id()->from(time());
            $table->unsignedBigInteger('user_id')->unique();
            $table->string('identity')->unique();
            $table->string('account_id')->unique();
            $table->string('currency');
            $table->string('card_hash');
            $table->string('card_pan');
            $table->string('masked_pan');
            $table->string('name_on_card');
            $table->string('expiration');
            $table->string('cvv');
            $table->string('address_1');
            $table->string('address_2')->nullable();
            $table->string('city');
            $table->string('state');
            $table->string('zip_code');
            $table->string('callback_url');
            $table->boolean('is_active');
            $table->string('provider');
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
        Schema::dropIfExists('virtual_cards');
    }
};
