<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkUpdateStudentsRequest extends FormRequest
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
            'data' => 'required|array',
            'data.*' => 'array',
            'data.*.field' => 'required|string',
            'data.*.value' => 'required',
        ];
    }
}
