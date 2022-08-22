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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id()->from(time());
            $table->unsignedBigInteger('user_id');
            $table->string('identity')->unique();
            $table->string('reference')->unique();
            $table->string('type');
            $table->string('channel');
            $table->decimal('amount', 15, 2);
            $table->string('narration');
            $table->string('status');
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
        Schema::dropIfExists('transactions');
    }
};
