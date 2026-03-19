<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrmTasksMigration extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('crm_tasks', function (Blueprint $table) {
            $table->id();

            $table->foreignId('opportunity_id')
                ->nullable()
                ->constrained('crm_opportunities')
                ->onDelete('cascade');

            $table->string('titulo');
            $table->text('descripcion')->nullable();

            $table->dateTime('fecha_programada');
            $table->dateTime('fecha_completada')->nullable();

            $table->string('estado')->default('pendiente'); // 'pendiente','completada','vencida','cancelada'

            $table->text('notas')->nullable();

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
        Schema::dropIfExists('crm_tasks');
    }
}
