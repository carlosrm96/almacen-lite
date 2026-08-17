<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Sin `WithoutModelEvents`: ese trait envuelve el seeding en
     * `Model::withoutEvents()`, lo que suprime también los listeners
     * `static::saved()`/`static::deleted()` con los que
     * spatie/laravel-permission invalida su caché de permisos
     * (`RefreshesPermissionCache`). Con los eventos apagados,
     * `Role::syncPermissions()` lee una caché de permisos obsoleta (vacía) y
     * falla con `PermissionDoesNotExist` aunque los permisos ya existan en
     * la base de datos — justo lo que hace `RolesAndPermissionsSeeder`.
     */
    public function run(): void
    {
        // Solo lo global de la instalación. Las monedas ya no van aquí: son de
        // cada empresa y se siembran al registrarse (ver
        // docs/superpowers/specs/2026-08-17-multi-empresa-y-registro-design.md).
        $this->call(RolesAndPermissionsSeeder::class);
    }
}
