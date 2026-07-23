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
        $allowedTypes = config('records.attachment.allowed_types', []);
        $allowedExts = config('records.attachment.allowed_mimes', []);

        return [
            'file' => [
                'required',
                'file',
                'max:' . config('records.attachment.max_size_kb'),
                // Custom rule: try MIME type first, then fall back to extension
                // This handles .docx files that servers detect as 'application/zip'
                function ($attribute, $value, $fail) use ($allowedTypes, $allowedExts) {
                    if (!$value instanceof \Illuminate\Http\UploadedFile) {
                        return;
                    }

                    // Try MIME type match first (most accurate)
                    $mime = $value->getMimeType();
                    if (in_array($mime, $allowedTypes, true)) {
                        return; // Valid by MIME
                    }

                    // Fallback: check by extension
                    // Many servers detect .docx as 'application/zip' because it IS a ZIP archive
                    $ext = strtolower($value->getClientOriginalExtension());
                    if (in_array($ext, $allowedExts, true)) {
                        return; // Valid by extension
                    }

                    $fail('Unsupported file type. Allowed types: images (JPG, PNG, GIF, WEBP, BMP), PDF, DOC, DOCX.');
                },
            ],
        ];
    }
    public function messages(): array
    {
        return [
            'file.required' => 'No file was provided.',
            'file.file' => 'The uploaded data is not a valid file.',
            'file.max' => 'File exceeds the maximum allowed size of ' . ($this->maxFileSizeMb()) . ' MB.',
        ];
    }
    private function maxFileSizeMb(): string
    {
        $kb = (int) config('records.attachment.max_size_kb', 10240);
        return number_format($kb / 1024, 1);
    }
}