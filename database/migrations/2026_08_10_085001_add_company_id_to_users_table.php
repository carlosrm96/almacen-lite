<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Todo usuario pertenece a una empresa: el que se registra, porque la crea;
 * los demás, porque los da de alta el admin de la suya.
 *
 * `restrictOnDelete()`: borrar una empresa con usuarios dentro tiene que
 * fallar en la base de datos, no dejar cuentas apuntando al vacío.
 *
 * El email sigue siendo único global (no por empresa): el login ocurre antes
 * de saber de qué empresa es quien entra.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('company_id')->after('id')
                ->constrained('companies')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('company_id');
        });
    }
};
