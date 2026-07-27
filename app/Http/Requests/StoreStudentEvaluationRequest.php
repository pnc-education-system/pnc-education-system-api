<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreStudentEvaluationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'evaluation_form_id' => 'required|integer|exists:evaluation_forms,id',
            'evaluation_period'  => 'required|string|max:255',
            'answers'            => 'required|array|min:1',
            'answers.*.question_id' => 'required|integer|exists:evaluation_question,id',
            'answers.*.score'    => 'required|numeric|min:0',
            'answers.*.comment'  => 'nullable|string|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'answers.required'        => 'At least one answer is required.',
            'answers.min'             => 'At least one answer is required.',
            'answers.*.question_id.required' => 'Each answer must have a question_id.',
            'answers.*.question_id.exists'   => 'One or more questions do not exist.',
            'answers.*.score.required'       => 'Each answer must have a score.',
            'answers.*.score.numeric'        => 'Each score must be a number.',
            'answers.*.score.min'            => 'Each score must be at least 0.',
        ];
    }
}
