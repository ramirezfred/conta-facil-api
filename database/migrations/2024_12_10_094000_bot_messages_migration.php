<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class BotMessagesMigration extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('bot_messages', function (Blueprint $table) {
            //$table->id();
            $table->increments('id');
            
            $table->string('wamid')->nullable();
            $table->integer('user_id')->nullable();
            $table->text('text')->nullable();
            $table->integer('autor')->nullable(); //0=bot 1=cliente
            $table->integer('status')->nullable(); //0=sin procesar 1=procesado
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
        Schema::dropIfExists('bot_chats');
    }
}
