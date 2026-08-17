<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sale_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
            // Cantidad tal como se vendió y su equivalente en unidad base.
            $table->decimal('cantidad', 14, 3);
            $table->decimal('cantidad_base', 14, 3);
            // Snapshots: la ganancia histórica no depende de los precios actuales.
            $table->decimal('precio_venta_unit', 12, 2);
            $table->decimal('precio_compra_unit', 12, 2);
            $table->decimal('subtotal', 12, 2);
            $table->timestamps();

            $table->index('product_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sale_items');
    }
};
