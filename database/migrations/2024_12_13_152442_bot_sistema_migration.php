<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class BotSistemaMigration extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('bot_sistema', function (Blueprint $table) {
            //$table->id();
            $table->increments('id');
            $table->string('key')->nullable();
            $table->boolean('active')->default(false); // Indica si la key está activa
            $table->string('activated_at')->nullable(); // Fecha de activación de la key
            $table->string('pdf_url')->nullable();
            $table->string('file_create_at')->nullable();
            $table->string('file_uri')->nullable();
            $table->string('file_state')->nullable();
            $table->string('cache_name')->nullable();
            $table->string('cache_create_at')->nullable();

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
        Schema::dropIfExists('bot_sistema');
    }
}
