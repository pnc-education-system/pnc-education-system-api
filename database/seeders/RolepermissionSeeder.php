<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RolepermissionSeeder extends Seeder
{
    public function run(): void
    {
        if (!DB::getSchemaBuilder()->hasTable('role_permission')) {
            $this->command->warn('Skipping RolepermissionSeeder: table role_permission does not exist.');
            return;
        }
    }
}

