<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class PosOrderDetailsMigration extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('pos_order_details', function (Blueprint $table) {
            $table->id();
            
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('product_id'); // relación directa a tus productos
            $table->decimal('cantidad', 15, 4);
            $table->decimal('precio_unitario', 15, 4);
            $table->decimal('porcentaje_desc', 5, 2)->default(0);
            $table->decimal('porcentaje_impuesto', 5, 2)->default(0); // IVA aplicado (0 o 16)

            // Campos adicionales para histórico
            $table->string('producto_nombre')->nullable();
            $table->string('producto_codigo')->nullable();
            
            // Totales por línea
            $table->decimal('subtotal', 15, 4)->default(0);
            $table->decimal('impuesto', 15, 4)->default(0);
            $table->decimal('descuento', 15, 4)->default(0);
            $table->decimal('total', 15, 4)->default(0);
            $table->timestamps();

            $table->foreign('order_id')->references('id')->on('pos_orders')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('erp_products')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('pos_order_details');
    }
}
