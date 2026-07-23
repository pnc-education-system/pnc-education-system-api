<?php

namespace Database\Factories;

use App\Models\CardTemplate;
use App\Models\Student;
use App\Models\StudentCard;
use Illuminate\Database\Eloquent\Factories\Factory;

class StudentCardFactory extends Factory
{
    protected $model = StudentCard::class;

    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'template_id' => CardTemplate::factory(),
            'card_number' => 'CARD-' . str_pad(fake()->unique()->numberBetween(1, 999999), 6, '0', STR_PAD_LEFT),
            'qr_token' => null,
            'issued_at' => fake()->dateTime(),
            'printed_count' => fake()->numberBetween(0, 5),
            'pdf_path' => null,
        ];
    }
}
