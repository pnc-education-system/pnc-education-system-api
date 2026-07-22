<?php

namespace App\Services\Student;

use App\Models\Student;
use App\Models\StudentCard;
use Illuminate\Support\Str;

class StudentCardService
{
    public function createStudentCard(array $data): StudentCard
    {
        $student = Student::findOrFail($data['student_id']);

        $card = StudentCard::create([
            'student_id' => $student->id,
            'template_id' => $data['template_id'] ?? null,
            'card_number' => $data['card_number'] ?? null,
            'qr_token' => $this->generateUniqueQrToken(),
            'issued_date' => $data['issued_date'] ?? now()->toDateString(),
            'expired_date' => $data['expired_date'] ?? null,
        ]);

        return $card;
    }

    public function generateUniqueQrToken(): string
    {
        do {
            $token = (string) Str::uuid();
        } while ($this->qrTokenExists($token));

        return $token;
    }

    private function qrTokenExists(string $token): bool
    {
        return StudentCard::where('qr_token', $token)->exists();
    }

    public function findStudentByQrToken(string $qrToken): ?Student
    {
        $card = StudentCard::where('qr_token', $qrToken)->first();

        if ($card) {
            return $card->student;
        }

        return null;
    }

    public function isValidQrToken(string $qrToken): bool
    {
        return $this->findStudentByQrToken($qrToken) !== null;
    }

    public function findStudentByStudentIdNo(string $studentIdNo): ?Student
    {
        return Student::where('student_id_no', $studentIdNo)->first();
    }

    public function findStudentById(int $studentId): ?Student
    {
        return Student::find($studentId);
    }
}
