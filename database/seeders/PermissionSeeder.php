<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
class PermissionSeeder extends Seeder {
    public function run(): void {
        $permissions = [
            ['name' => 'View Users', 'slug' => 'users.view', 'module' => 'Users'],
            ['name' => 'Manage Users', 'slug' => 'users.manage', 'module' => 'Users'],
            ['name' => 'View Roles', 'slug' => 'roles.view', 'module' => 'Roles'],
            ['name' => 'Manage Roles', 'slug' => 'roles.manage', 'module' => 'Roles'],
            ['name' => 'View Settings', 'slug' => 'settings.view', 'module' => 'Settings'],
            ['name' => 'Edit Settings', 'slug' => 'settings.edit', 'module' => 'Settings'],
        ];
        foreach ($permissions as $p) {
            DB::table('permissions')->updateOrInsert(['slug' => $p['slug']], array_merge($p, ['created_at' => now(), 'updated_at' => now()]));
        }
    }
}
