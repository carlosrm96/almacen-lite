<?php

namespace App\Modules\Tenancy\Models;

use App\Models\User;
use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * El negocio dueño de sus almacenes, su catálogo y sus ventas.
 *
 * Es la raíz del aislamiento: todo lo demás cuelga de ella por `company_id`.
 * No lleva `CompanyScope` —sería circular—; a la empresa se llega siempre
 * desde el usuario autenticado.
 */
class Company extends Model
{
    /** @use HasFactory<CompanyFactory> */
    use HasFactory;

    protected $fillable = ['nombre', 'activo'];

    /** @var array<string, mixed> */
    protected $attributes = [
        'activo' => true,
    ];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    /** @return HasMany<User, $this> */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    protected static function newFactory(): CompanyFactory
    {
        return CompanyFactory::new();
    }
}
