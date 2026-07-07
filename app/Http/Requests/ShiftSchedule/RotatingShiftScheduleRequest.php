<?php

namespace App\Http\Requests\ShiftSchedule;

use Illuminate\Foundation\Http\FormRequest;

class RotatingShiftScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()->role, ['admin', 'hr', 'manager']);
    }

    public function rules(): array
    {
        return [
            'employee_ids' => 'required|array|min:1',
            'employee_ids.*' => 'integer|exists:employees,id',
            'shift_pattern' => 'required|array|min:1',
            'shift_pattern.*' => 'nullable|integer|exists:shifts,id',
            'start_date' => 'required|date',
            'weeks' => 'sometimes|integer|min:1|max:52',
        ];
    }
}
