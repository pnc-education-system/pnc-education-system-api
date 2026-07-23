<?php

namespace App\Services\Student;

use App\Models\Student;
use Illuminate\Support\Facades\DB;

class StudentIdGenerator
{
    private string $prefix = 'PNC';

    public function generateNextId(int $intakeYear): string
    {
        $maxId = $this->getMaxExistingId($intakeYear);
        $nextNumber = $maxId + 1;
        
        return sprintf('%s%d-%03d', $this->prefix, $intakeYear, $nextNumber);
    }

    public function generateMultipleIds(int $count, int $intakeYear): array
    {
        $ids = [];
        $maxId = $this->getMaxExistingId($intakeYear);
        
        for ($i = 1; $i <= $count; $i++) {
            $nextNumber = $maxId + $i;
            $ids[] = sprintf('%s%d-%03d', $this->prefix, $intakeYear, $nextNumber);
        }
        
        return $ids;
    }

    private function getMaxExistingId(int $intakeYear): int
    {
        $pattern = $this->prefix . $intakeYear . '-%';
        
        $offset = strlen($this->prefix) + strlen((string) $intakeYear) + 2;
        
        $maxId = Student::where('student_id_no', 'like', $pattern)
            ->select(DB::raw('MAX(CAST(SUBSTRING(student_id_no, ' . $offset . ') AS UNSIGNED)) as max_id'))
            ->value('max_id');

        return (int) ($maxId ?? 0);
    }

    public function idExists(string $studentId): bool
    {
        return Student::where('student_id_no', $studentId)->exists();
    }
}
