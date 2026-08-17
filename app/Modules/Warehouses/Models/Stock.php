<?php

namespace App\Modules\Warehouses\Models;

use App\Modules\Catalog\Models\Product;
use App\Modules\Tenancy\Models\Concerns\BelongsToCompany;
use Database\Factories\StockFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Stock extends Model
{
    /** @use HasFactory<StockFactory> */
    use BelongsToCompany, HasFactory;

    protected $fillable = ['product_id', 'warehouse_id', 'cantidad', 'minimo'];

    protected function casts(): array
    {
        return ['cantidad' => 'float', 'minimo' => 'float'];
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<Warehouse, $this> */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    protected static function newFactory(): StockFactory
    {
        return StockFactory::new();
    }
}
