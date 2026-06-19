<?php

namespace App\Http\Requests\LeaveType;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLeaveTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() || $this->user()?->isHR();
    }

    public function rules(): array
    {
        $leaveTypeId = $this->route('leave_type')?->id ?? $this->route('leave_type');

        return [
            'code' => ['sometimes', 'required', 'string', 'max:50', Rule::unique('leave_types', 'code')->ignore($leaveTypeId)],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_paid' => ['boolean'],
            'requires_attachment' => ['boolean'],
            'requires_balance' => ['boolean'],
            'max_consecutive_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'max_days_per_year' => ['nullable', 'integer', 'min:1', 'max:366'],
            'default_days_per_year' => ['sometimes', 'required', 'integer', 'min:0', 'max:366'],
            'min_service_months' => ['integer', 'min:0', 'max:120'],
            'gender_restriction' => ['nullable', Rule::in(['male', 'female', 'all'])],
            'carry_forward_enabled' => ['boolean'],
            'max_carry_forward_days' => ['nullable', 'integer', 'min:1', 'max:366'],
            'is_active' => ['boolean'],
        ];
    }
}
