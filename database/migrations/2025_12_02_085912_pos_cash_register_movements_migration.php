<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class PosCashRegisterMovementsMigration extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('pos_cash_register_movements', function (Blueprint $table) {
            $table->id();   

            $table->unsignedBigInteger('cash_register_id');
            $table->unsignedBigInteger('order_id')->nullable(); // Solo si es devolución de una venta
            $table->unsignedBigInteger('user_id'); // Vendedor/cajero

            $table->string('tipo'); //'ingreso', 'egreso'
            $table->decimal('monto', 15, 2);
            $table->string('referencia')->nullable(); // referencia del movimiento
            $table->text('notas')->nullable();

            $table->timestamps();

            $table->foreign('cash_register_id')
                ->references('id')->on('pos_cash_registers')
                ->onDelete('cascade');

            $table->foreign('order_id')
                ->references('id')->on('pos_orders')
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
        Schema::dropIfExists('pos_cash_register_movements');
    }
}
