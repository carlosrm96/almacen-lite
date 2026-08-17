<?php

namespace App\Modules\Tenancy\Http\Middleware;

use App\Modules\Tenancy\Support\CurrentCompany;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fija la empresa de contexto a partir del usuario autenticado.
 *
 * Va SIEMPRE detrás de `auth:sanctum` (alias `tenant` en los grupos de rutas),
 * no en el grupo global de la API: ese corre antes que el middleware de ruta y
 * ahí el usuario todavía no está resuelto. El orden es el que hace o deshace
 * el aislamiento, así que queda explícito en las rutas en lugar de depender de
 * la ordenación por prioridad de Laravel.
 */
class ResolveCurrentCompany
{
    public function __construct(private CurrentCompany $current) {}

    public function handle(Request $request, Closure $next): Response
    {
        // Primero a null: en un worker de larga vida, heredar la empresa de la
        // petición anterior serviría los datos de otro negocio.
        $this->current->set(null);

        $company = $request->user()?->company;

        if ($company !== null) {
            abort_if(! $company->activo, Response::HTTP_FORBIDDEN, 'La empresa está desactivada.');

            $this->current->set($company);
        }

        return $next($request);
    }
}
