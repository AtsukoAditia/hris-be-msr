<?php

namespace App\Http\Requests\Payroll;

use App\Models\SalaryComponent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSalaryComponentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => strtoupper(trim((string) $this->input('code'))),
            'name' => trim((string) $this->input('name')),
        ]);
    }

    public function rules(): array
    {
        $component = $this->route('salaryComponent');

        return [
            'code' => ['required', 'string', 'max:50', 'regex:/^[A-Z0-9_-]+$/', Rule::unique('salary_components', 'code')->ignore($component?->id)],
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', Rule::in([SalaryComponent::TYPE_EARNING, SalaryComponent::TYPE_DEDUCTION])],
            'calculation_type' => ['required', Rule::in([SalaryComponent::CALCULATION_FIXED, SalaryComponent::CALCULATION_PERCENTAGE, SalaryComponent::CALCULATION_FORMULA])],
            'default_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999999999.99'],
            'percentage' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'formula' => ['nullable', 'string', 'max:2000'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
