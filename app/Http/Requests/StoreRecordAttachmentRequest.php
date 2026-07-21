<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRecordAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'mimes:' . implode(',', config('records.attachment.allowed_mimes')),
                'max:' . config('records.attachment.max_size_kb'),
            ],
        ];
    }
    public function messages(): array
    {
        return [
            'file.required' => 'No file was provided.',
            'file.file' => 'The uploaded data is not a valid file.',
            'file.mimes' => 'Unsupported file type. Allowed types: images (JPG, PNG, GIF, WEBP, BMP), PDF, DOC, DOCX.',
            'file.max' => 'File exceeds the maximum allowed size of ' . ($this->maxFileSizeMb()) . ' MB.',
        ];
    }
    private function maxFileSizeMb(): string
    {
        $kb = (int) config('records.attachment.max_size_kb', 10240);
        return number_format($kb / 1024, 1);
    }
}