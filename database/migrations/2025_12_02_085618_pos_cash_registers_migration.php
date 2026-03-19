<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class PosCashRegistersMigration extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('pos_cash_registers', function (Blueprint $table) {
            $table->id();            

            $table->string('nombre');
            $table->string('estado')->default('cerrada'); //'abierta', 'cerrada'

            $table->datetime('fecha_apertura')->nullable();
            $table->decimal('monto_inicial', 15, 2)->default(0);
            $table->text('notas_apertura')->nullable();

            $table->datetime('fecha_cierre')->nullable();
            $table->decimal('monto_final', 15, 2)->default(0);
            $table->text('notas_cierre')->nullable();

            $table->decimal('monto_esperado', 15, 2)->default(0); //Monto esperado según ventas
            $table->decimal('diferencia', 15, 2)->default(0); //Diferencia en arqueo

            $table->unsignedBigInteger('user_id_apertura')->nullable();
            $table->unsignedBigInteger('user_id_cierre')->nullable();

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
        Schema::dropIfExists('pos_cash_registers');
    }
}
