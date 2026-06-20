<?php

namespace App\Http\Requests\Payroll;

use Illuminate\Foundation\Http\FormRequest;

class StoreEmployeeSalaryProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $currency = strtoupper(trim((string) $this->input('currency', 'IDR')));
        $this->merge(['currency' => $currency]);
    }

    public function rules(): array
    {
        return [
            'basic_salary' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'is_active' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'components' => ['sometimes', 'array', 'max:100'],
            'components.*.salary_component_id' => ['required', 'integer', 'distinct', 'exists:salary_components,id'],
            'components.*.amount' => ['nullable', 'numeric', 'min:0'],
            'components.*.percentage' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'components.*.formula' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
