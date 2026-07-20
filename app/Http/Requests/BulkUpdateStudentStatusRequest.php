<?php

namespace App\Http\Requests;

use App\Models\Student;
use Illuminate\Foundation\Http\FormRequest;

class BulkUpdateStudentStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'student_ids' => 'required|array|min:1',
            'student_ids.*' => 'integer',
            'status' => 'required|string',
            'note' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'student_ids.required' => 'At least one student must be selected.',
            'student_ids.array' => 'Student IDs must be an array.',
            'student_ids.min' => 'At least one student must be selected.',
            'student_ids.*.exists' => 'One or more selected students do not exist.',
            'status.required' => 'The new status is required.',
            'status.in' => 'The selected status is invalid. Valid options: Pending, Enrolled, Rejected, Graduated, Dropped.',
            'note.max' => 'The note must not exceed 500 characters.',
        ];
    }
}
