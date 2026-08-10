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
        $this->call(RolesAndPermissionsSeeder::class);
    }
}
