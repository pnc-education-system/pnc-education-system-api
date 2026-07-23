<?php

namespace App\Http\Requests;

use App\Models\StudentRecord;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StudentRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Accept either `category` (backend) or `record_type` (frontend)
            'category' => ['sometimes', 'required_without:record_type', 'string', Rule::in(StudentRecord::CATEGORIES)],
            'record_type' => ['sometimes', 'required_without:category', 'string', Rule::in(StudentRecord::CATEGORIES)],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['sometimes', 'string', 'nullable'],
            // Accept either `record_date` (backend) or `recorded_at` (frontend)
            'record_date' => ['sometimes', 'required_without:recorded_at', 'date'],
            'recorded_at' => ['sometimes', 'required_without:record_date', 'date'],
            // Accept `recorded_by` (frontend) mapped to `created_by`
            'recorded_by' => ['sometimes', 'integer', 'exists:users,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'category.in' => 'The selected category is invalid. Valid categories: ' . implode(', ', StudentRecord::CATEGORIES),
            'record_type.in' => 'The selected record type is invalid. Valid types: ' . implode(', ', StudentRecord::CATEGORIES),
        ];
    }
}
