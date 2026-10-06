<?php

namespace Database\Seeders;

use App\Enums\Izin;
use App\Enums\Peran;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

class PeranDanIzinSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (Izin::cases() as $izin) {
            Permission::firstOrCreate(['name' => $izin->value, 'guard_name' => 'web']);
        }

        foreach (Peran::cases() as $peran) {
            $role = Role::firstOrCreate(['name' => $peran->value, 'guard_name' => 'web']);
            $role->syncPermissions(array_map(fn (Izin $i) => $i->value, $peran->izin()));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
