<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProductosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('productos', function (Blueprint $table) {
            //$table->id();
            $table->increments('id');

            $table->integer('user_id')->nullable();

            $table->string('nombre');
            $table->text('descripcion')->nullable();
            $table->decimal('precio', 10, 2);
            $table->integer('stock')->default(0);

            // $table->integer('IdProdServ')->nullable();
            $table->string('ClaveProdServ')->nullable();
            // $table->string('ProdServ')->nullable();

            // $table->integer('IdUnidad')->nullable();
            $table->string('ClaveUnidad')->nullable();
            $table->string('Unidad')->nullable();

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
        Schema::dropIfExists('productos');
    }
}
