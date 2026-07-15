<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            PermissionSeeder::class,
            // Ensure we use the correctly-updated seeder (avoid casing mismatches).
            RolePermissionSeeder::class,
            UserSeeder::class,
            SettingSeeder::class,
        ]);
    }
}

