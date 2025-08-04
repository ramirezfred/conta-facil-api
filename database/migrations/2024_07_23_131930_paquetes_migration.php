<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class PaquetesMigration extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('paquetes', function (Blueprint $table) {
            //$table->id();
            $table->increments('id');
            $table->string('nombre')->nullable();
            $table->text('descripcion')->nullable();
            $table->float('precio')->nullable();
            $table->integer('status')->nullable();
            $table->integer('tipo')->nullable();
            $table->integer('cantidad')->nullable();
            $table->string('imagen')->nullable();
            $table->integer('flag_eliminado')->nullable();
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
        Schema::dropIfExists('paquetes');
    }
}
