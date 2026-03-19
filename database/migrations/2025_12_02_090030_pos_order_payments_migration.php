<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class PosOrderPaymentsMigration extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('pos_order_payments', function (Blueprint $table) {
            $table->id();
            
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('cash_register_id'); // caja donde se registró la venta
            $table->string('tipo_pago'); //'efectivo', 'tarjeta', 'transferencia', 'cheque', 'otro'
            $table->decimal('monto', 15, 2);
            $table->string('referencia')->nullable(); // número de autorización, folio, etc.

            $table->unsignedBigInteger('user_id'); //Vendedor/cajero

            $table->timestamps();

            $table->foreign('order_id')
                ->references('id')->on('pos_orders')
                ->onDelete('cascade');

            $table->foreign('cash_register_id')
                ->references('id')->on('pos_cash_registers')
                ->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('pos_order_payments');
    }
}
