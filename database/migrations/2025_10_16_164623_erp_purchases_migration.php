<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ErpPurchasesMigration extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('erp_purchases', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('supplier_id'); // proveedor
            $table->date('fecha_compra');
            $table->string('folio')->nullable(); // folio o número de factura o documento
            $table->string('tipo_documento')->nullable(); // “Factura”, “Nota”, “Ticket”, etc.
            $table->string('metodo_pago')->nullable();
            $table->string('forma_pago')->nullable();
            $table->string('moneda')->default('MXN');
            $table->decimal('tipo_cambio', 10, 4)->default(1);
            
            // Totales
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('descuento', 15, 2)->default(0);
            $table->decimal('impuesto', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            
            $table->text('notas')->nullable();
            $table->boolean('eliminado')->default(false);
            $table->unsignedInteger('user_id')->nullable();
            $table->timestamps();

            // Relaciones
            $table->foreign('supplier_id')->references('id')->on('erp_suppliers')->onDelete('restrict');
            // $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('erp_purchases');
    }
}
