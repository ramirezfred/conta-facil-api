<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ErpStockMovementsMigration extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('erp_stock_movements', function (Blueprint $table) {
            $table->id();

            // Relación con producto
            $table->foreignId('product_id')->constrained('erp_products');

            // Detalle del movimiento
            $table->decimal('cantidad', 14, 4);

            // $table->enum('tipo', ['compra', 'venta', 'ajuste_positivo', 'ajuste_negativo']);
            $table->string('tipo');

            // Referencia a otra tabla (Order, Purchase, etc.)
            $table->string('referencia_type')->nullable(); 
            $table->unsignedBigInteger('referencia_id')->nullable();

            // Nuevos campos
            $table->decimal('stock_resultante', 14, 4)->default(0);
            $table->text('motivo')->nullable();

            // Usuario responsable del movimiento
            $table->unsignedInteger('user_id')->nullable(); 

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
        Schema::dropIfExists('erp_stock_movements');
    }
}
