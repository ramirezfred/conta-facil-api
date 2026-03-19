<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrmQuotesMigration extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('crm_quotes', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('opportunity_id'); // relación con la oportunidad
            $table->string('folio')->nullable();
            $table->date('fecha_emision')->nullable();
            $table->date('fecha_vencimiento')->nullable();
            
            // Totales
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('descuento', 15, 2)->default(0);
            $table->decimal('impuesto', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);

            $table->string('estado')->default('borrador'); // 'borrador', 'enviada', 'aceptada', 'rechazada'
            $table->text('notas')->nullable();
            $table->string('pdf')->nullable();
            $table->dateTime('processed_at')->nullable();
            $table->boolean('eliminado')->default(false);

            $table->timestamps();

            $table->foreign('opportunity_id')->references('id')->on('crm_opportunities')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('crm_quotes');
    }
}
