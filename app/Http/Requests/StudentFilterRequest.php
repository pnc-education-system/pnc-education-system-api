<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StudentFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => 'nullable|string|in:Pending,Enrolled,Rejected,Graduated,Dropped',
            'batch' => 'nullable|integer|exists:selection_batches,id',
            'province' => 'nullable|string|max:100',
            'search' => 'nullable|string|max:255',
        ];
    }
}
