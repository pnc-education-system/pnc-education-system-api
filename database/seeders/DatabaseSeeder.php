<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CardtemplateSeeder::class,
            RolepermissionSeeder::class,
            UserSeeder::class,
            SettingSeeder::class,
            SelectionBatchSeeder::class,
            EvaluationTemplateSeeder::class,
        ]);
    }
}
