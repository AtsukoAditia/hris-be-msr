<?php

namespace App\Http\Requests\ShiftSchedule;

use Illuminate\Foundation\Http\FormRequest;

class CopyWeekRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()->role, ['admin', 'hr', 'manager']);
    }

    public function rules(): array
    {
        return [
            'source_start_date' => 'required|date',
            'target_start_date' => 'required|date|after:source_start_date',
            'employee_ids' => 'nullable|array',
            'employee_ids.*' => 'required|integer|exists:employees,id',
        ];
    }
}