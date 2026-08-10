<?php

namespace App\Modules\Warehouses\Models;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transfer extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'product_id', 'from_warehouse_id', 'to_warehouse_id', 'cantidad_base', 'user_id',
    ];

    protected function casts(): array
    {
        return ['cantidad_base' => 'float', 'created_at' => 'datetime'];
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
