<?php

namespace App\Modules\Tenancy\Http\Middleware;

use App\Modules\Tenancy\Support\CurrentCompany;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Deja la empresa de contexto sin fijar al principio de CADA petición.
 *
 * Va en el grupo global de la API, delante de todo, y por una razón concreta:
 * `User` también lleva `CompanyScope`, así que cuando Sanctum resuelve el
 * token busca a su dueño con el scope aplicado. Si el contexto sobreviviera de
 * una petición a la siguiente —Octane, o varias peticiones simuladas en un
 * mismo test—, el usuario de otra empresa no aparecería y su token válido
 * respondería `401`.
 *
 * Limpiar aquí no basta para aislar: eso lo hace `ResolveCurrentCompany`
 * (alias `tenant`), que va detrás de `auth:sanctum` porque necesita al usuario
 * ya resuelto. Este solo garantiza que se parte de cero.
 */
class ForgetCurrentCompany
{
    public function __construct(private CurrentCompany $current) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->current->set(null);

        return $next($request);
    }
}
