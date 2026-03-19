<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrmOpportunitiesMigration extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('crm_opportunities', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('user_id'); // usuario dueño de la oportunidad
            $table->unsignedBigInteger('contacto_id'); // referencia a CfdiCliente (cliente o prospecto)

            $table->string('titulo');
            $table->text('descripcion')->nullable();

            $table->decimal('monto_estimado', 15, 2)->nullable();

            $table->string('fuente_lead')->nullable(); // redes, referido, llamada, web, otro

            $table->string('etapa')->default('nueva');     //'nueva', 'propuesta', 'negociacion', 'revision', 'pospuesta', 'ganada', 'perdida'
            $table->string('probabilidad')->default('5%'); //'5%',    '40%',       '60%',         '75%',       '25%',      '100%',   '0%'
            
            $table->date('fecha_cierre_estimada')->nullable();

            $table->text('comentarios')->nullable();

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
        Schema::dropIfExists('crm_opportunities');
    }
}
