<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class BulkConfirmStudentsRequest extends FormRequest
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
            'selection_batch_id' => 'nullable|exists:selection_batches,id',
            'confirmation_status' => 'required|string|in:confirmed,rejected',];
    }

    public function messages(): array
    {
        return [
            'student_ids.required' => 'At least one student must be selected.',
            'student_ids.array' => 'Student IDs must be an array.',
            'student_ids.min' => 'At least one student must be selected.',
        ];
    }
}
