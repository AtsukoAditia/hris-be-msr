<?php

namespace App\Http\Requests\EmployeeDocument;

use Illuminate\Foundation\Http\FormRequest;

class ReplaceEmployeeDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
        ];
    }
}
