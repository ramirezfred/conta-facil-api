<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class PosOrdersMigration extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('pos_orders', function (Blueprint $table) {
            $table->id();

            $table->string('folio')->unique();
            $table->unsignedBigInteger('quote_id')->nullable(); // Si viene de CRM
            $table->unsignedBigInteger('opportunity_id')->nullable(); // Trazabilidad con CRM
            $table->unsignedBigInteger('contacto_id')->nullable(); // referencia a CfdiCliente (cliente o prospecto)
            $table->unsignedBigInteger('user_id'); // Vendedor/cajero
            $table->unsignedBigInteger('cash_register_id')->nullable(); // caja donde se registró la venta
            
            // Totales
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('descuento', 15, 2)->default(0);
            $table->decimal('impuesto', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->decimal('total_recibido', 15, 2)->default(0);
            $table->decimal('cambio', 15, 2)->default(0);

            $table->string('status')->default('pendiente'); //'pendiente', 'pagada', 'cancelada'
            
            $table->boolean('facturada')->default(false); //Si ya se facturó
            $table->unsignedBigInteger('comprobante_id')->nullable(); //Relación con factura

            $table->text('notas')->nullable();
            $table->string('pdf')->nullable();
            $table->boolean('eliminado')->default(false);

            $table->timestamps();

            $table->foreign('cash_register_id')
                ->references('id')->on('pos_cash_registers')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('pos_orders');
    }
}
