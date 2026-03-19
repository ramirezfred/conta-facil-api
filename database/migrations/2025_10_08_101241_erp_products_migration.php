<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ErpProductsMigration extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('erp_products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('sku')->unique();
            $table->text('description')->nullable();

            // Precios y stock
            $table->decimal('purchase_price', 14, 4)->default(0);
            $table->decimal('sale_price', 14, 4)->default(0);
            $table->decimal('impuesto', 14, 4)->default(0);
            $table->decimal('stock', 14, 4)->default(0);
            $table->decimal('stock_minimum', 14, 4)->default(0);

            // Campos SAT
            $table->string('ClaveProdServ')->nullable();
            $table->string('ClaveUnidad')->nullable();
            $table->string('Unidad')->nullable();

            // Estado y tipo
            $table->boolean('is_service')->default(false);
            $table->boolean('status')->default(true);
            $table->boolean('eliminado')->default(false);

            // Relaciones
            $table->foreignId('category_id')
                ->nullable()
                ->constrained('erp_categories')
                ->nullOnDelete();

            // Usuario que registró o actualizó el producto (sin FK directa)
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
        Schema::dropIfExists('erp_products');
    }
}
