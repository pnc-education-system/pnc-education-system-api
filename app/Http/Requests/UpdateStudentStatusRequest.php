<?php

namespace App\Http\Requests;

use App\Models\Student;
use Illuminate\Foundation\Http\FormRequest;

class UpdateStudentStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => 'required|string',
            'note'   => 'nullable|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'The new status is required.',
            'status.in'       => 'The selected status is invalid. Valid options: Pending, Enrolled, Rejected, Graduated, Dropped.',
            'note.max'        => 'The note must not exceed 500 characters.',
        ];
    }
}
