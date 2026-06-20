<?php

namespace App\Http\Requests\OvertimePolicy;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOvertimePolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'               => ['sometimes', 'string', 'max:100', Rule::unique('overtime_policies', 'name')->ignore($this->route('overtime_policy'))],
            'description'        => ['nullable', 'string', 'max:500'],
            'daily_max_minutes'  => ['sometimes', 'integer', 'min:1', 'max:1440'],
            'weekly_max_minutes' => ['sometimes', 'integer', 'min:1', 'max:10080'],
            'rate_multiplier'    => ['sometimes', 'numeric', 'min:1', 'max:10'],
            'is_active'          => ['nullable', 'boolean'],
        ];
    }
}