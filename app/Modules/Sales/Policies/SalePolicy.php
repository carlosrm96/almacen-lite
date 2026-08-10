<?php

namespace App\Modules\Sales\Policies;

use App\Models\User;
use App\Modules\Sales\Models\Sale;

class SalePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('sales.view');
    }

    /** El vendedor solo ve las ventas de su propio almacén. */
    public function view(User $user, Sale $sale): bool
    {
        if (! $user->can('sales.view')) {
            return false;
        }

        return $user->isAdmin() || $user->warehouse_id === $sale->warehouse_id;
    }

    public function create(User $user): bool
    {
        return $user->can('sales.create');
    }
}
