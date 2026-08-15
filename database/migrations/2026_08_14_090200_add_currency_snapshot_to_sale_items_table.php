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
        Schema::table('sale_items', function (Blueprint $table): void {
            // Snapshot de la moneda y la tasa aplicadas, por la misma razón que
            // ya se congelan los precios: que una devaluación futura no
            // reescriba la ganancia del pasado.
            //
            // `precio_*_unit` quedan en la moneda del producto; `subtotal` (y
            // `sales.total`) ya van convertidos a moneda base. `tasa_cambio`
            // tiene default 1 para que las agregaciones no necesiten COALESCE.
            $table->string('moneda_codigo', 3)->nullable()->after('precio_compra_unit');
            $table->decimal('tasa_cambio', 16, 6)->default(1)->after('moneda_codigo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sale_items', function (Blueprint $table): void {
            $table->dropColumn(['moneda_codigo', 'tasa_cambio']);
        });
    }
};
