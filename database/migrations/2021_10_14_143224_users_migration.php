<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UsersMigration extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('users', function (Blueprint $table) {
            //$table->id();
            $table->increments('id');
            $table->integer('rol')->nullable();
            $table->integer('status')->nullable();
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->dateTime('last_login')->nullable();

            $table->string('nombre')->nullable();
            $table->string('flag_aprobado')->nullable();

            $table->string('color_a')->nullable();
            $table->string('color_b')->nullable();
            $table->string('color_c')->nullable();
            $table->string('header')->nullable();
            $table->string('footer')->nullable();
            $table->string('logo')->nullable();
            $table->string('logo_allow_origin')->nullable();

            $table->integer('count_facturas')->nullable();
            $table->string('flag_eliminado')->nullable();
            
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
        Schema::dropIfExists('users');
    }
}
