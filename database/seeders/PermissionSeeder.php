<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            // User management
            ['name' => 'View Users',   'slug' => 'users.view',   'module' => 'Users'],
            ['name' => 'Create Users', 'slug' => 'users.create', 'module' => 'Users'],
            ['name' => 'Edit Users',   'slug' => 'users.edit',   'module' => 'Users'],
            ['name' => 'Delete Users', 'slug' => 'users.delete', 'module' => 'Users'],

            // Role management
            ['name' => 'View Roles',   'slug' => 'roles.view',   'module' => 'Roles'],
            ['name' => 'Create Roles', 'slug' => 'roles.create', 'module' => 'Roles'],
            ['name' => 'Edit Roles',   'slug' => 'roles.edit',   'module' => 'Roles'],
            ['name' => 'Delete Roles', 'slug' => 'roles.delete', 'module' => 'Roles'],

            // Settings
            ['name' => 'View Settings', 'slug' => 'settings.view', 'module' => 'Settings'],
            ['name' => 'Edit Settings', 'slug' => 'settings.edit', 'module' => 'Settings'],
        ];

        foreach ($permissions as $permission) {
            DB::table('permissions')->updateOrInsert(['slug' => $permission['slug']], array_merge($permission, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }
}
