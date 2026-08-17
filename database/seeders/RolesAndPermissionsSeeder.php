<?php

namespace Database\Seeders;

use App\Modules\Access\Enums\Role as RoleEnum;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Permisos por rol. Fuente única de RBAC del proyecto.
     *
     * @var array<string, list<string>>
     */
    private const ROLE_PERMISSIONS = [
        RoleEnum::Admin->value => [
            'company.view', 'company.update',
            'users.view', 'users.create', 'users.update', 'users.delete',
            'warehouses.view', 'warehouses.create', 'warehouses.update', 'warehouses.delete',
            'units.view', 'units.create', 'units.update', 'units.delete',
            'products.view', 'products.create', 'products.update', 'products.delete',
            'stock.set',
            'transfers.view', 'transfers.create',
            'sales.view', 'sales.create',
            'metrics.view', 'metrics.full',
            'audit.view',
        ],
        RoleEnum::Vendedor->value => [
            // Ver el negocio al que pertenece, no cambiarlo.
            'company.view',
            'products.view',
            'sales.view', 'sales.create',
            'metrics.view',
        ],
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::ROLE_PERMISSIONS as $roleName => $permissions) {
            foreach ($permissions as $permission) {
                Permission::findOrCreate($permission, 'web');
            }

            Role::findOrCreate($roleName, 'web')->syncPermissions($permissions);
        }
    }
}
