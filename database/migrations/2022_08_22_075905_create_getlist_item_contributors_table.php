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
        Schema::create('getlist_item_contributors', function (Blueprint $table) {
            $table->id()->from(time());
            $table->unsignedBigInteger('getlist_item_id');
            $table->string('reference')->unique();
            $table->string('full_name');
            $table->string('email_address');
            $table->string('phone_number');
            $table->string('type');
            $table->decimal('amount', 15, 2);
            $table->longText('meta');
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
        Schema::dropIfExists('getlist_item_contributors');
    }
};
