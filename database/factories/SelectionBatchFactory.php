<?php

namespace Database\Factories;

use App\Models\SelectionBatch;
use Illuminate\Database\Eloquent\Factories\Factory;

class SelectionBatchFactory extends Factory
{
    protected $model = SelectionBatch::class;

    public function definition(): array
    {
        return [
            'name' => 'Batch ' . fake()->year() . ' - ' . fake()->randomElement(['Spring', 'Summer', 'Fall', 'Winter']),
            'year' => fake()->year(),
            'created_by' => null,
        ];
    }
}
