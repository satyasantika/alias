<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Baseline permissions; `php artisan shield:generate --all` expands these.
        $baselinePermissions = [
            'ViewAny:User',
            'View:User',
            'Create:User',
            'Update:User',
            'Delete:User',
            'DeleteAny:User',
            'ViewAny:Role',
            'View:Role',
            'Create:Role',
            'Update:Role',
            'Delete:Role',
            'DeleteAny:Role',
        ];

        foreach ($baselinePermissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        $superAdmin = Role::firstOrCreate([
            'name' => 'Super Admin',
            'guard_name' => 'web',
        ]);
        $superAdmin->syncPermissions(Permission::query()->pluck('name'));

        $userRole = Role::firstOrCreate([
            'name' => 'User',
            'guard_name' => 'web',
        ]);
        $userRole->syncPermissions([
            'ViewAny:User',
            'View:User',
        ]);

        $superAdminUser = User::firstOrCreate(
            ['email' => 'superadmin@alias.test'],
            [
                'name' => 'Super Administrator',
                'password' => Hash::make('siliwangi'),
                'email_verified_at' => now(),
            ],
        );
        $superAdminUser->syncRoles(['Super Admin']);

        $demoUser = User::firstOrCreate(
            ['email' => 'user@alias.test'],
            [
                'name' => 'Pengguna Demo',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ],
        );
        $demoUser->syncRoles(['User']);
    }
}
