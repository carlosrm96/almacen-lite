<?php

namespace App\Modules\Warehouses\Exceptions;

use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class InsufficientStockException extends RuntimeException implements ShouldntReport
{
    /**
     * @param  list<array{product_id: int, nombre: string, solicitado: string, disponible: string}>  $faltantes
     */
    public function __construct(private readonly array $faltantes)
    {
        parent::__construct('Stock insuficiente');
    }

    /**
     * @return list<array{product_id: int, nombre: string, solicitado: string, disponible: string}>
     */
    public function getFaltantes(): array
    {
        return $this->faltantes;
    }

    public function render(Request $request): JsonResponse
    {
        $cuantos = count($this->faltantes);

        return new JsonResponse([
            'message' => 'Stock insuficiente',
            'errors' => [
                'items' => ["Stock insuficiente para {$cuantos} producto".($cuantos === 1 ? '' : 's').'.'],
            ],
            'productos_afectados' => $this->faltantes,
        ], Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
