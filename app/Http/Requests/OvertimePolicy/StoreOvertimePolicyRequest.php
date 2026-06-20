<?php

namespace App\Http\Requests\OvertimePolicy;

use Illuminate\Foundation\Http\FormRequest;

class StoreOvertimePolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'               => ['required', 'string', 'max:100', 'unique:overtime_policies,name'],
            'description'        => ['nullable', 'string', 'max:500'],
            'daily_max_minutes'  => ['required', 'integer', 'min:1', 'max:1440'],
            'weekly_max_minutes' => ['required', 'integer', 'min:1', 'max:10080'],
            'rate_multiplier'    => ['required', 'numeric', 'min:1', 'max:10'],
            'is_active'          => ['nullable', 'boolean'],
        ];
    }
}