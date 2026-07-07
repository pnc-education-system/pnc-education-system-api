<?php

namespace Database\Seeders;

use App\Models\SelectionBatch;
use Illuminate\Database\Seeder;

class SelectionBatchSeeder extends Seeder
{
    public function run(): void
    {
        SelectionBatch::firstOrCreate(
            ['name' => 'Batch 2025'],
            ['year' => 2025, 'is_active' => true]
        );
    }
}
