<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CfdiClientesMigration extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cfdi_clientes', function (Blueprint $table) {
            //$table->id();
            $table->increments('id');
            $table->integer('user_id')->nullable();
            $table->integer('empresa_id')->nullable();
            $table->integer('status')->nullable();
            $table->string('Rfc')->nullable();
            $table->string('Nombre')->nullable();
            $table->string('DomicilioFiscalReceptor')->nullable();
            $table->string('ResidenciaFiscal')->nullable();
            $table->string('NumRegIdTrib')->nullable();
            $table->string('RegimenFiscalReceptor')->nullable();
            $table->string('UsoCFDI')->nullable();
            $table->string('Email')->nullable();
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
        Schema::dropIfExists('cfdi_clientes');
    }
}
