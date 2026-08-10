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
        Schema::table('users', function (Blueprint $table): void {
            // `restrictOnDelete()` en vez de `nullOnDelete()`: un vendedor
            // nunca puede quedarse sin almacén, ni siquiera porque alguien
            // borre el almacén por debajo. Esta es la red de seguridad a
            // nivel de base de datos; el guard amigable en la aplicación
            // (422 en vez de un error 500 de la BD) llega en la Tarea 8.
            $table->foreignId('warehouse_id')->nullable()->after('email')
                ->constrained('warehouses')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('warehouse_id');
        });
    }
};
