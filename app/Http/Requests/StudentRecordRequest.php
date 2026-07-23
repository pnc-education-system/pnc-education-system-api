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
            'category' => ['required', 'string', Rule::in(StudentRecord::CATEGORIES)],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'record_date' => ['required', 'date'],
        ];
    }
}
