<?php

namespace App\Rules\Student;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class StudentIdFormat implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!preg_match('/^ST\d{4,6}$/', $value)) {
            $fail('Student ID must follow the format ST followed by 4-6 digits (e.g., ST0001).');
        }
    }
}
