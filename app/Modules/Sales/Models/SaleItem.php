<?php

namespace App\Modules\Sales\Models;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Unit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleItem extends Model
{
    protected $fillable = [
        'sale_id', 'product_id', 'unit_id', 'cantidad', 'cantidad_base',
        'precio_venta_unit', 'precio_compra_unit', 'subtotal',
    ];

    protected function casts(): array
    {
        return [
            'cantidad' => 'float',
            'cantidad_base' => 'float',
            'precio_venta_unit' => 'float',
            'precio_compra_unit' => 'float',
            'subtotal' => 'float',
        ];
    }

    /** @return BelongsTo<Sale, $this> */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<Unit, $this> */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}
