<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Http\Resources\CurrencyResource;
use App\Modules\Catalog\Models\Currency;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * @group Catálogo · Monedas
 *
 * @authenticated
 */
class CurrencyController extends Controller
{
    use AuthorizesRequests;

    /**
     * Listar monedas.
     *
     * Solo lectura: las tasas se administran por seeder o consola, no por API.
     * La moneda base va primero y siempre tiene tasa 1.
     *
     * @queryParam solo_activas boolean Devolver únicamente las monedas activas. Example: true
     */
    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Currency::class);

        $currencies = Currency::query()
            ->when(request()->boolean('solo_activas'), fn (Builder $q) => $q->where('activo', true))
            ->orderByDesc('es_base')
            ->orderBy('codigo')
            ->get();

        return CurrencyResource::collection($currencies);
    }
}
