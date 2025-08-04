<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class BotsMigration extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('bots', function (Blueprint $table) {
            //$table->id();
            $table->increments('id');

            $table->string('nombre')->nullable();
            $table->string('telefono')->nullable();
            $table->string('whatsapp_id')->nullable();
            $table->string('number_id')->nullable();
            $table->string('fecha_token')->nullable();
            $table->text('access_token')->nullable();

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
        Schema::dropIfExists('bots');
    }
}
