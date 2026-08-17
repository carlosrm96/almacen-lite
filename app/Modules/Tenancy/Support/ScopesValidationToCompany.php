<?php

namespace App\Modules\Tenancy\Support;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\Unique;

/**
 * Reglas `exists`/`unique` acotadas a la empresa de contexto.
 *
 * Las reglas nativas de Laravel consultan la tabla en crudo y NO pasan por
 * `CompanyScope`. Sin esto, un `exists:warehouses,id` aceptaría el almacén de
 * otro negocio —bastaría con adivinar el id para atarle un vendedor— y un
 * `unique` filtraría qué nombres o códigos existen en empresas ajenas.
 *
 * Regla práctica: toda regla `exists`/`unique` sobre una tabla con
 * `company_id` va por aquí.
 */
trait ScopesValidationToCompany
{
    protected function currentCompanyId(): ?int
    {
        return app(CurrentCompany::class)->id();
    }

    /**
     * @param  array<string, mixed>  $extra  Pares columna => valor para acotar más.
     */
    protected function companyScopedExists(string $table, string $column = 'NULL', array $extra = []): Exists
    {
        $rule = Rule::exists($table, $column)->where('company_id', $this->currentCompanyId());

        foreach ($extra as $col => $value) {
            $rule->where($col, $value);
        }

        return $rule;
    }

    /**
     * @param  array<string, mixed>  $extra  Pares columna => valor para acotar más.
     */
    protected function companyScopedUnique(string $table, string $column = 'NULL', array $extra = []): Unique
    {
        $rule = Rule::unique($table, $column)->where('company_id', $this->currentCompanyId());

        foreach ($extra as $col => $value) {
            $rule->where($col, $value);
        }

        return $rule;
    }
}
