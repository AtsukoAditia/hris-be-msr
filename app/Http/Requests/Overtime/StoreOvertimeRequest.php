<?php

namespace App\Http\Requests\Overtime;

use Illuminate\Foundation\Http\FormRequest;

class StoreOvertimeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'overtime_policy_id' => ['required', 'integer', 'exists:overtime_policies,id'],
            'overtime_date'      => ['required', 'date', 'date_format:Y-m-d'],
            'planned_start_time' => ['required', 'date_format:H:i'],
            'planned_end_time'   => ['required', 'date_format:H:i'],
            'reason'             => ['required', 'string', 'max:500'],
            'attachment'         => ['nullable', 'string', 'max:255'],
        ];
    }
}