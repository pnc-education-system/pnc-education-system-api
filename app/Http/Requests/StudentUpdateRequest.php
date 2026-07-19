<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StudentUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'student_id_no' => [
                'required',
                'string',
                'max:255',
                Rule::unique('students', 'student_id_no')->ignore($this->route('id')),
            ],
            'full_name' => ['required', 'string', 'max:255'],
            'gender' => ['required', Rule::in(['Male', 'Female'])],
            'dob' => ['nullable', 'date'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'province' => ['nullable', 'string', 'max:255'],
            'high_school' => ['nullable', 'string', 'max:255'],
            'selection_batch_id' => ['required', 'exists:selection_batches,id'],
            'enrollment_status' => ['required', Rule::in(['Pending', 'Enrolled', 'Rejected', 'Graduated', 'Dropped'])],
            'intake_year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'enrolled_at' => ['nullable', 'date'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ];
    }

    public function messages(): array
    {
        return [
            'student_id_no.required' => 'Student ID is required',
            'student_id_no.unique' => 'Student ID already exists',
            'full_name.required' => 'Full name is required',
            'gender.required' => 'Gender is required',
            'gender.in' => 'Gender must be Male or Female',
            'selection_batch_id.required' => 'Selection batch is required',
            'selection_batch_id.exists' => 'Selected batch does not exist',
            'enrollment_status.required' => 'Enrollment status is required',
            'enrollment_status.in' => 'Invalid enrollment status',
            'email.email' => 'Email must be a valid email address',
            'photo.image' => 'Student photo must be an image',
            'photo.mimes' => 'Student photo must be a JPG, JPEG, PNG, or WEBP file',
            'photo.max' => 'Student photo must not be larger than 4MB',
        ];
    }
}
