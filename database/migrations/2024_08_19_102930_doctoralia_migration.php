<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class DoctoraliaMigration extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('doctoralia', function (Blueprint $table) {
            //$table->id();
            $table->increments('id');
            $table->integer('user_id')->nullable();
            $table->integer('status')->nullable();
            $table->integer('count_vistas')->nullable();
            $table->string('nombre')->nullable();
            $table->string('cedula')->nullable();
            $table->string('telefono')->nullable();
            $table->string('direccion')->nullable();
            $table->string('lat')->nullable();
            $table->string('lng')->nullable();
            $table->float('costo_asesoria')->nullable();
            $table->string('imagen')->nullable();
            $table->string('ccf')->nullable(); //constacia de cotizacion fiscal
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
        Schema::dropIfExists('doctoralia');
    }
}
