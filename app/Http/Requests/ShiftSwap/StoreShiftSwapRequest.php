<?php

namespace App\Http\Requests\ShiftSwap;

use Illuminate\Foundation\Http\FormRequest;

class StoreShiftSwapRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'target_id' => 'required|integer|exists:employees,id',
            'requester_schedule_id' => 'required|integer|exists:shift_schedules,id',
            'target_schedule_id' => 'nullable|integer|exists:shift_schedules,id',
            'reason' => 'nullable|string|max:500',
        ];
    }
}
