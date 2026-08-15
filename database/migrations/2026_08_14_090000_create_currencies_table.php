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
        Schema::create('currencies', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo', 3)->unique();
            $table->string('nombre');
            $table->string('simbolo', 8);
            // Cuántas unidades de la moneda base vale 1 unidad de esta moneda.
            // La moneda base tiene, por definición, tasa 1.
            $table->decimal('tasa', 16, 6)->default(1);
            $table->boolean('es_base')->default(false);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('currencies');
    }
};
