<?php

namespace App\Modules\Catalog\Models;

use Database\Factories\UnitFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Unit extends Model
{
    /** @use HasFactory<UnitFactory> */
    use HasFactory;

    protected $fillable = ['nombre', 'factor'];

    protected function casts(): array
    {
        return ['factor' => 'float'];
    }

    public function esBase(): bool
    {
        return abs($this->factor - 1.0) < 0.0001;
    }

    /** @return HasMany<ProductUnit, $this> */
    public function productUnits(): HasMany
    {
        return $this->hasMany(ProductUnit::class);
    }

    protected static function newFactory(): UnitFactory
    {
        return UnitFactory::new();
    }
}
