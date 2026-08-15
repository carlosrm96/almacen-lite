<?php

namespace App\Modules\Catalog\Policies;

use App\Models\User;

/**
 * Las monedas son de solo lectura vía API: las tasas se administran por seeder
 * o consola. El vendedor también las consulta —un precio sin moneda no es un
 * precio—, así que basta con `products.view`.
 */
class CurrencyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('products.view');
    }
}
