<?php

namespace App\Http\Requests\LeavePolicy;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLeavePolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() || $this->user()?->isHR();
    }

    public function rules(): array
    {
        $policyId = $this->route('leave_policy')?->id ?? $this->route('leave_policy');

        return [
            'leave_type_id' => ['sometimes', 'required', 'integer', 'exists:leave_types,id'],
            'year' => ['sometimes', 'required', 'integer', 'min:2020', 'max:2100'],
            'policy_name' => ['sometimes', 'required', 'string', 'max:255'],
            'default_quota' => ['sometimes', 'required', 'integer', 'min:0', 'max:366'],
            'min_service_months' => ['sometimes', 'integer', 'min:0'],
            'accrual_type' => ['sometimes', 'required', 'string', 'in:yearly,monthly,none'],
            'accrual_amount' => ['nullable', 'integer', 'min:0'],
            'max_carry_forward_days' => ['nullable', 'integer', 'min:0'],
            'carry_forward_expiry_month' => ['nullable', 'integer', 'min:1', 'max:12'],
            'carry_forward_expiry_day' => ['nullable', 'integer', 'min:1', 'max:31'],
            'carry_forward_expiry_months' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['boolean'],
        ];
    }
}
