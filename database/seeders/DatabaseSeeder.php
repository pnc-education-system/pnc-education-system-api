<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolepermissionSeeder::class,
            UserSeeder::class,
            SettingSeeder::class,
            SelectionBatchSeeder::class,
        ]);
    }
}
