<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AuthTestSeeder extends Seeder
{
    public function run(): void
    {
        // Create admin role
        $adminRole = Role::create([
            'name' => 'admin',
            'display_name' => 'Administrator',
            'description' => 'System administrator with full access',
            'is_active' => true,
        ]);

        // Create permissions
        $permissions = [
            ['name' => 'user.create', 'display_name' => 'Create User', 'group' => 'users'],
            ['name' => 'user.update', 'display_name' => 'Update User', 'group' => 'users'],
            ['name' => 'user.delete', 'display_name' => 'Delete User', 'group' => 'users'],
            ['name' => 'user.view', 'display_name' => 'View User', 'group' => 'users'],
        ];

        $permissionIds = [];
        foreach ($permissions as $permission) {
            $perm = Permission::create($permission);
            $permissionIds[] = $perm->id;
        }

        // Attach permissions to role
        $adminRole->permissions()->attach($permissionIds);

        // Create test user with admin role
        User::create([
            'role_id' => $adminRole->id,
            'name' => 'Test Admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
    }
}
