<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ErpPurchaseDetailsMigration extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('erp_purchase_details', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('purchase_id');
            $table->unsignedBigInteger('product_id');

            $table->decimal('cantidad', 15, 4);
            $table->decimal('precio_unitario', 15, 4);
            $table->decimal('porcentaje_desc', 5, 2)->default(0);
            $table->decimal('porcentaje_impuesto', 5, 2)->default(0); // IVA aplicado (0 o 16)
            
            // Totales por línea
            $table->decimal('subtotal', 15, 4)->default(0);
            $table->decimal('impuesto', 15, 4)->default(0);
            $table->decimal('descuento', 15, 4)->default(0);
            $table->decimal('total', 15, 4)->default(0);

            $table->timestamps();

            // Relaciones
            $table->foreign('purchase_id')->references('id')->on('erp_purchases')->onDelete('cascade');
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
        Schema::dropIfExists('erp_purchase_details');
    }
}
