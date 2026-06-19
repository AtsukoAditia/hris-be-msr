<?php

namespace App\Http\Requests\Leave;

use Illuminate\Foundation\Http\FormRequest;

class RejectLeaveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled by controller
    }

    public function rules(): array
    {
        return [
            'rejection_reason' => 'required|string|min:10|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'rejection_reason.required' => 'Alasan penolakan wajib diisi.',
            'rejection_reason.string' => 'Alasan penolakan harus berupa teks.',
            'rejection_reason.min' => 'Alasan penolakan minimal 10 karakter.',
            'rejection_reason.max' => 'Alasan penolakan maksimal 500 karakter.',
        ];
    }
}
