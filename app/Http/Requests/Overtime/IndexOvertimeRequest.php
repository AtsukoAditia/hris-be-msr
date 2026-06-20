<?php

namespace App\Http\Requests\Overtime;

use Illuminate\Foundation\Http\FormRequest;

class IndexOvertimeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status'      => ['nullable', 'string', 'in:pending,approved,rejected,cancelled'],
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'date_from'   => ['nullable', 'date', 'date_format:Y-m-d'],
            'date_to'     => ['nullable', 'date', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'per_page'    => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}