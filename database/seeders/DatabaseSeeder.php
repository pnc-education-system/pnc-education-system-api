<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\UserSeeder;


class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            UserSeeder::class
        ]);
    }
}
