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
            'code' => ['sometimes', 'required', 'string', 'max:50', Rule::unique('leave_types', 'code')->ignore($leaveTypeId), 'regex:/^[A-Z0-9_-]+$/'],
            'name' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('leave_types', 'name')->ignore($leaveTypeId)],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_paid' => ['boolean'],
            'requires_attachment' => ['boolean'],
            'requires_balance' => ['boolean'],
            'max_consecutive_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'max_days_per_year' => ['nullable', 'integer', 'min:1', 'max:366'],
            'default_days_per_year' => ['sometimes', 'required', 'integer', 'min:0', 'max:366'],
            'min_service_months' => ['nullable', 'integer', 'min:0', 'max:120'],
            'gender_restriction' => ['nullable', Rule::in(['male', 'female', 'all'])],
            'carry_forward_enabled' => ['boolean'],
            'max_carry_forward_days' => ['nullable', 'integer', 'min:1', 'max:366'],
            'is_active' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.regex' => 'Kode hanya boleh berisi huruf besar, angka, dash, dan underscore.',
            'name.unique' => 'Nama leave type sudah digunakan.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $maxDays = $this->input('max_days_per_year') ?? $this->route('leave_type')?->max_days_per_year;
            $defaultDays = $this->input('default_days_per_year') ?? $this->route('leave_type')?->default_days_per_year;

            if ($maxDays !== null && $defaultDays !== null && $defaultDays > $maxDays) {
                $validator->errors()->add('default_days_per_year', 'Default hari per tahun tidak boleh lebih dari maksimum hari per tahun.');
            }

            $maxCarry = $this->input('max_carry_forward_days') ?? $this->route('leave_type')?->max_carry_forward_days;
            if ($maxCarry !== null && $maxDays !== null && $maxCarry > $maxDays) {
                $validator->errors()->add('max_carry_forward_days', 'Maksimum carry forward tidak boleh lebih dari maksimum hari per tahun.');
            }
        });
    }
}
