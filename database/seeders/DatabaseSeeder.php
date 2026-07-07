<?php

namespace Database\Seeders;

<<<<<<< HEAD
=======
use App\Models\User;
>>>>>>> 4fc27d8d1c3a96455f2b552493b66ceb6692b860
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
<<<<<<< HEAD
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            PermissionSeeder::class,
            RolePermissionSeeder::class,
            UserSeeder::class,
            SettingSeeder::class,
=======

    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            UserSeeder::class,
            EvaluationCategorySeeder::class,
            EvaluationTemplateSeeder::class,
            CardTemplateSeeder::class,
            SelectionBatchSeeder::class,
>>>>>>> 4fc27d8d1c3a96455f2b552493b66ceb6692b860
        ]);
    }
}
