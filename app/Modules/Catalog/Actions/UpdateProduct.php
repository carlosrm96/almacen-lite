<?php

namespace App\Modules\Catalog\Actions;

use App\Models\User;
use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Catalog\Models\Product;
use Illuminate\Support\Facades\DB;

class UpdateProduct
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $datos
     */
    public function handle(User $user, Product $product, array $datos): Product
    {
        return DB::transaction(function () use ($user, $product, $datos): Product {
            $cambios = [];

            // El formato se elige por el nombre del campo, no por `is_numeric` del
            // valor: un producto llamado "1000" no es un precio y no debe quedar
            // registrado como "1000.00".
            $precios = ['precio_compra', 'precio_venta'];

            foreach ($datos as $campo => $nuevo) {
                $antes = $product->getAttribute($campo);
                $formatea = fn (mixed $v): string => in_array($campo, $precios, true)
                    ? number_format((float) $v, 2, '.', '')
                    : (string) $v;

                if ($formatea($antes) !== $formatea($nuevo)) {
                    $cambios[$campo] = ['antes' => $formatea($antes), 'despues' => $formatea($nuevo)];
                }
            }

            $product->update($datos);

            if ($cambios !== []) {
                $this->audit->log($user, AuditLogger::ACCION_PRODUCTO_ACTUALIZADO, $product, null, $cambios);
            }

            return $product->refresh();
        });
    }
}
