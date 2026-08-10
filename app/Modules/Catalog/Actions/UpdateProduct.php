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

            foreach ($datos as $campo => $nuevo) {
                $antes = $product->getAttribute($campo);
                $formatea = fn (mixed $v): string => is_float($v) || is_numeric($v)
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
