<?php

namespace App\Modules\Catalog\Actions;

use App\Models\User;
use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Catalog\Models\Product;
use Illuminate\Support\Facades\DB;

class DeleteProduct
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** Borrado lógico: el producto se marca como eliminado y queda constancia de quién lo hizo. */
    public function handle(User $user, Product $product): void
    {
        DB::transaction(function () use ($user, $product): void {
            $this->audit->log($user, AuditLogger::ACCION_PRODUCTO_ELIMINADO, $product, null, [
                'nombre' => $product->nombre,
            ]);

            $product->delete();
        });
    }
}
