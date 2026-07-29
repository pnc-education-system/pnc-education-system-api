<?php

namespace App\Rules\Student;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class StudentIdFormat implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Accept old format (ST followed by 3-6 digits) and new format (PNC{year}-XXX)
        if (!preg_match('/^(ST\d{3,6}|PNC\d{4}-\d{3})$/', $value)) {
            $fail('Student ID must follow the format by PNC{year}-XXX (e.g., PNC2026-001).');
        }
    }
}
