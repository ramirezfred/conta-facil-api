<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class GastoConceptosMigration extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('gasto_conceptos', function (Blueprint $table) {
            //$table->id();
            $table->increments('id');
            $table->integer('gasto_id')->nullable();
            $table->string('Descripcion',1000)->nullable();
            $table->float('Cantidad')->nullable();
            $table->float('ValorUnitario')->nullable();
            $table->float('Importe')->nullable();
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
        Schema::dropIfExists('gasto_conceptos');
    }
}
