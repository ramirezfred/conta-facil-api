<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class IngresosRecurrentesMigration extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ingresos_recurrentes', function (Blueprint $table) {
            //$table->id();
            $table->increments('id');
            $table->integer('user_id')->nullable();
            $table->integer('ingreso_id')->nullable();
            $table->integer('status')->nullable();
            $table->string('titulo')->nullable();
            $table->integer('frecuencia')->nullable();
            $table->string('hora')->nullable();
            $table->string('fecha')->nullable();
            $table->integer('dia_semana')->nullable();
            $table->integer('dia_mes')->nullable();
            $table->string('concepto')->nullable();
            $table->string('date_last_run')->nullable();
            $table->text('log_run')->nullable();
            $table->text('registros')->nullable();
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
        Schema::dropIfExists('ingresos_recurrentes');
    }
}
