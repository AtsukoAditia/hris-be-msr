<?php

namespace App\Http\Requests\Payroll;

use App\Models\PayrollPeriod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePayrollPeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['name' => trim((string) $this->input('name'))]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'cutoff_start_date' => ['required', 'date'],
            'cutoff_end_date' => ['required', 'date', 'after_or_equal:cutoff_start_date'],
            'status' => ['sometimes', Rule::in([PayrollPeriod::STATUS_OPEN, PayrollPeriod::STATUS_CLOSED])],
        ];
    }
}
