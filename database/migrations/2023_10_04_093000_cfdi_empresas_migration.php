<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CfdiEmpresasMigration extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cfdi_empresas', function (Blueprint $table) {
            //$table->id();
            $table->increments('id');
            $table->integer('user_id')->nullable();
            $table->integer('tipo_persona')->nullable();
            $table->string('Rfc')->nullable();
            $table->string('RazonSocial')->nullable();
            $table->string('RegimenFiscal')->nullable();
            $table->string('FacAtrAdquirente')->nullable();
            $table->string('CP')->nullable();
            //$table->string('ObjetoImp')->nullable();
            $table->string('cer')->nullable();
            $table->string('key')->nullable();
            $table->string('pass')->nullable();

            $table->integer('flag_descuento')->nullable();
            $table->integer('flag_objetoImp')->nullable();
            $table->integer('flag_retencion')->nullable();
            $table->integer('flag_producto')->nullable();
            $table->string('folio_venta')->nullable();
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
        Schema::dropIfExists('cfdi_empresas');
    }
}
