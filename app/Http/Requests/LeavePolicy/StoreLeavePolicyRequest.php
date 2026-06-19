<?php

namespace App\Http\Requests\LeavePolicy;

use App\Models\LeavePolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLeavePolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() || $this->user()?->isHR();
    }

    public function rules(): array
    {
        return [
            'leave_type_id' => ['required', 'integer', 'exists:leave_types,id'],
            'year' => ['required', 'integer', 'min:2020', 'max:2100'],
            'policy_name' => ['nullable', 'string', 'max:255'],
            'default_quota' => ['required', 'integer', 'min:0', 'max:366'],
            'min_service_months' => ['sometimes', 'integer', 'min:0', 'max:120'],
            'accrual_type' => ['sometimes', Rule::in(['yearly', 'monthly', 'one_time'])],
            'accrual_amount' => ['sometimes', 'integer', 'min:0', 'max:366'],
            'max_carry_forward_days' => ['nullable', 'integer', 'min:0', 'max:366'],
            'carry_forward_expiry_month' => ['nullable', 'integer', 'min:1', 'max:12'],
            'carry_forward_expiry_day' => ['nullable', 'integer', 'min:1', 'max:31'],
            'carry_forward_expiry_months' => ['nullable', 'integer', 'min:1', 'max:12'],
            'is_active' => ['boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $query = LeavePolicy::query()
                ->where('leave_type_id', $this->input('leave_type_id'))
                ->where('year', $this->input('year'))
                ->where('policy_name', $this->input('policy_name'));

            if ($query->exists()) {
                $validator->errors()->add('policy_name', 'Policy for this leave type, year, and policy name already exists.');
            }
        });
    }
}
