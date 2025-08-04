<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CursosMigration extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cursos', function (Blueprint $table) {
            //$table->id();
            $table->increments('id');
            $table->integer('tipo')->nullable();
            $table->string('nombre')->nullable();
            $table->string('url')->nullable();
            $table->string('autor')->nullable();
            $table->text('descripcion')->nullable();
            $table->integer('likes_count')->default(0);
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
        Schema::dropIfExists('cursos');
    }
}
