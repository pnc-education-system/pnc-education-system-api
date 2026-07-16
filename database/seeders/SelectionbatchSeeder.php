<?php

namespace Database\Seeders;

use App\Models\SelectionBatch;
use App\Models\User;
use Illuminate\Database\Seeder;

class SelectionBatchSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('email', 'admin@pnc.edu.kh')->first();

        SelectionBatch::firstOrCreate(
            ['name' => 'Batch 2025'],
            [
                'year' => 2025,
                'created_by' => $admin?->id ?? 1,
            ]
        );

        SelectionBatch::firstOrCreate(
            ['name' => 'Batch 2024'],
            [
                'year' => 2024,
                'created_by' => $admin?->id ?? 1,
            ]
        );

        SelectionBatch::firstOrCreate(
            ['name' => 'Batch 2023'],
            [
                'year' => 2023,
                'created_by' => $admin?->id ?? 1,
            ]
        );

        SelectionBatch::firstOrCreate(
            ['name' => 'Batch 2026'],
            [
                'year' => 2026,
                'created_by' => $admin?->id ?? 1,
            ]
        );

        SelectionBatch::firstOrCreate(
            ['name' => 'Batch 2027'],
            [
                'year' => 2027,
                'created_by' => $admin?->id ?? 1,
            ]
        );

        SelectionBatch::firstOrCreate(
            ['name' => 'Spring 2026'],
            [
                'year' => 2026,
                'created_by' => $admin?->id ?? 1,
            ]
        );

        SelectionBatch::firstOrCreate(
            ['name' => 'Fall 2026'],
            [
                'year' => 2026,
                'created_by' => $admin?->id ?? 1,
            ]
        );
    }
}
