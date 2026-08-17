<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La empresa: el negocio dueño de sus almacenes, su catálogo y sus ventas.
 *
 * Va la primera de las tablas del dominio porque todas las demás la
 * referencian por `company_id`.
 *
 * Ver docs/superpowers/specs/2026-08-17-multi-empresa-y-registro-design.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre');
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
