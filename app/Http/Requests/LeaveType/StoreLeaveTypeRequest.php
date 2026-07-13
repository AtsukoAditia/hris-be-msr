<?php

namespace App\Http\Requests\LeaveType;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLeaveTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() || $this->user()?->isHR();
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50', 'unique:leave_types,code', 'regex:/^[A-Z0-9_-]+$/'],
            'name' => ['required', 'string', 'max:255', 'unique:leave_types,name'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_paid' => ['required', 'boolean'],
            'requires_attachment' => ['boolean'],
            'requires_balance' => ['boolean'],
            'max_consecutive_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'max_days_per_year' => ['nullable', 'integer', 'min:1', 'max:366'],
            'default_days_per_year' => ['required', 'integer', 'min:0', 'max:366'],
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
            'max_days_per_year.min' => 'Maksimum hari per tahun minimal 1.',
            'default_days_per_year.max' => 'Default hari per tahun tidak boleh lebih dari 366.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $maxDays = $this->input('max_days_per_year');
            $defaultDays = $this->input('default_days_per_year');

            if ($maxDays !== null && $defaultDays !== null && $defaultDays > $maxDays) {
                $validator->errors()->add('default_days_per_year', 'Default hari per tahun tidak boleh lebih dari maksimum hari per tahun.');
            }

            $maxCarry = $this->input('max_carry_forward_days');
            if ($maxCarry !== null && $maxDays !== null && $maxCarry > $maxDays) {
                $validator->errors()->add('max_carry_forward_days', 'Maksimum carry forward tidak boleh lebih dari maksimum hari per tahun.');
            }
        });
    }
}
