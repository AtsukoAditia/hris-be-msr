<?php

namespace App\Http\Requests\ShiftSchedule;

use Illuminate\Foundation\Http\FormRequest;

class BulkStoreShiftScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_ids' => 'required|array|min:1',
            'employee_ids.*' => 'required|integer|exists:employees,id',
            'schedules' => 'required|array|min:1',
            'schedules.*.date' => 'required|date',
            'schedules.*.shift_id' => 'nullable|integer|exists:shifts,id',
            'schedules.*.is_day_off' => 'sometimes|boolean',
            'schedules.*.notes' => 'nullable|string|max:255',
        ];
    }
}
