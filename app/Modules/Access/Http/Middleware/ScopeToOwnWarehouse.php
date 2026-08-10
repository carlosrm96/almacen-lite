<?php

namespace App\Modules\Access\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fuerza `warehouse_id` al almacén del vendedor.
 *
 * No rechaza la petición cuando llega otro almacén: lo sobrescribe. Así el
 * vendedor nunca puede operar fuera de su almacén, venga lo que venga en el
 * cuerpo o en la query string. El admin pasa sin cambios.
 */
class ScopeToOwnWarehouse
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && $user->isVendedor()) {
            $request->merge(['warehouse_id' => $user->warehouse_id]);
            $request->query->set('warehouse_id', (string) $user->warehouse_id);
        }

        return $next($request);
    }
}
