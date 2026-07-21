<?php

namespace App\Services;

use App\Models\CardTemplate;
use App\Models\Student;
use App\Models\StudentCard;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;

class StudentCardService
{
    /**
     * Increment the printed_count for a student card by 1.
     * If no card exists for the given student, a new one is created.
     *
     * Accepts either a StudentCard ID or a Student ID.
     *
     * @throws ModelNotFoundException if the given ID matches neither a StudentCard nor a Student
     */
    public function reprint(int $id): StudentCard
    {
        $card = StudentCard::find($id);

        if (!$card) {
            $card = StudentCard::where('student_id', $id)->first();
        }

        if (!$card) {
            // No card exists yet — verify the student exists before creating
            $student = Student::find($id);

            if (!$student) {
                throw (new ModelNotFoundException)->setModel(StudentCard::class, $id);
            }

            $card = StudentCard::create([
                'student_id'    => $student->id,
                'template_id'   => CardTemplate::where('is_default', true)->value('id') ?? 1,
                'card_number'   => $this->generateCardNumber(),
                'qr_token'      => Str::random(config('cards.qr_token_length', 32)),
                'issued_at'     => now(),
                'printed_count' => 0,
            ]);
        }

        /** @var StudentCard $card */
        $card->increment('printed_count');

        return $card->fresh();
    }

    /**
     * Generate a unique card number.
     */
    protected function generateCardNumber(): string
    {
        $prefix = 'STU';
        $year = date('y');
        $random = strtoupper(Str::random(6));
        return "{$prefix}{$year}-{$random}";
    }
}
