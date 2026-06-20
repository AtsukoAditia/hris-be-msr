<?php

namespace App\Http\Requests\Overtime;

use Illuminate\Foundation\Http\FormRequest;

class ApproveOvertimeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [];
    }
}