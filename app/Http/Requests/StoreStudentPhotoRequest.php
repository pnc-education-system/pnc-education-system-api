<?php 

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreStudentPhotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }
    public function rules(): array
    {
        return[
            'photo' =>[
                'required',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:4096'],
        ];
    }
    public function messages(): array
    {
        return[
            'photo.required'=>'No photo was provided.',
            'photo.image' => 'The file must be an image.',
            'photo.mimes'=>'Photo must be a JPG, JPEG, PNG, or WEBP file.',
            'photo.max'=>'Photo must not be larger than 4MB.'
        ];
    }
}