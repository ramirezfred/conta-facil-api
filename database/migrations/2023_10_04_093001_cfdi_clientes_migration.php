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
            // $table->integer('status')->nullable();
            $table->boolean('status')->default(true);
            $table->string('Rfc')->nullable();
            $table->string('Nombre')->nullable();
            $table->string('DomicilioFiscalReceptor')->nullable();
            $table->string('ResidenciaFiscal')->nullable();
            $table->string('NumRegIdTrib')->nullable();
            $table->string('RegimenFiscalReceptor')->nullable();
            $table->string('UsoCFDI')->nullable();
            $table->string('Email')->nullable();

            // Campos nuevos para CRM y POS
            $table->boolean('status')->default(true); //actualizado para manejarlo como boolean
            $table->string('telefono', 30)->nullable();
            $table->text('direccion')->nullable();
            $table->string('tipo_entidad')->default('cliente'); //'cliente', 'proveedor', 'ambos'
            $table->string('tipo_cliente')->default('prospecto')->nullable(); //'prospecto', 'cliente'
            $table->string('origen')->default('crm'); //'crm', 'pos', 'erp', 'cfdi', 'api'
            $table->string('tipo_operacion')->nullable(); //para proveedores
            $table->boolean('eliminado')->default(false);

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
