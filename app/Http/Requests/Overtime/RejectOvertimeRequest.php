<?php

namespace App\Http\Requests\Overtime;

use Illuminate\Foundation\Http\FormRequest;

class RejectOvertimeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'rejection_reason' => ['required', 'string', 'max:500'],
        ];
    }
}