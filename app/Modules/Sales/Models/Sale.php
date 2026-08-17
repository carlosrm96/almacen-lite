<?php

namespace App\Modules\Sales\Models;

use App\Models\User;
use App\Modules\Tenancy\Models\Concerns\BelongsToCompany;
use App\Modules\Warehouses\Models\Warehouse;
use Database\Factories\SaleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    /** @use HasFactory<SaleFactory> */
    use BelongsToCompany, HasFactory;

    protected $fillable = ['warehouse_id', 'user_id', 'total'];

    protected function casts(): array
    {
        return ['total' => 'float'];
    }

    /** @return HasMany<SaleItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    /** @return BelongsTo<Warehouse, $this> */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected static function newFactory(): SaleFactory
    {
        return SaleFactory::new();
    }
}
