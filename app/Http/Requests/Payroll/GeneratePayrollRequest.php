<?php

namespace App\Http\Requests\Payroll;

use Illuminate\Foundation\Http\FormRequest;

class GeneratePayrollRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_ids' => ['nullable', 'array', 'max:500'],
            'employee_ids.*' => ['integer', 'distinct', 'exists:employees,id'],
        ];
    }
}
