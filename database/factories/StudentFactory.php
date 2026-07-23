<?php

namespace Database\Factories;

use App\Models\SelectionBatch;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class StudentFactory extends Factory
{
    protected $model = Student::class;

    public function definition(): array
    {
        return [
            'student_id_no' => 'PNC-' . fake()->year() . '-' . str_pad(fake()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
            'full_name' => fake()->name(),
            'gender' => fake()->randomElement(['Male', 'Female']),
            'dob' => fake()->date(),
            'phone' => fake()->phoneNumber(),
            'email' => fake()->email(),
            'province' => fake()->city(),
            'high_school' => fake()->company(),
            'selection_batch_id' => SelectionBatch::factory(),
            'enrollment_status' => 'Pending',
            'is_confirmed' => false,
            'intake_year' => fake()->year(),
            'enrolled_at' => null,
            'photo_path' => null,
            'created_by' => User::factory(),
        ];
    }
}
