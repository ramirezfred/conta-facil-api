<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CatGastosMigration extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cat_gastos', function (Blueprint $table) {
            //$table->id();
            $table->increments('id');
            $table->string('clave')->nullable();
            $table->string('descripcion')->nullable();
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
        Schema::dropIfExists('cat_gastos');
    }
}
