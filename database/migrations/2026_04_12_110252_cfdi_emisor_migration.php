<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CfdiEmisorMigration extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cfdi_emisor', function (Blueprint $table) {
            //$table->id();
            $table->increments('id');
            $table->integer('comprobante_id')->nullable();
            $table->string('Rfc')->nullable();
            $table->string('RazonSocial')->nullable();
            $table->string('RegimenFiscal')->nullable();
            $table->string('CP')->nullable();
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
        Schema::dropIfExists('cfdi_emisor');
    }
}
