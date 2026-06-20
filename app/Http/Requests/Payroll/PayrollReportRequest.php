<?php

namespace App\Http\Requests\Payroll;

use App\Models\Payroll;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PayrollReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'payroll_period_id' => ['nullable', 'integer', 'exists:payroll_periods,id'],
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'status' => ['nullable', Rule::in([
                Payroll::STATUS_DRAFT,
                Payroll::STATUS_REVIEWED,
                Payroll::STATUS_FINALIZED,
                Payroll::STATUS_PAID,
                Payroll::STATUS_CANCELLED,
            ])],
            'search' => ['nullable', 'string', 'max:120'],
            'format' => ['nullable', Rule::in(['csv', 'pdf'])],
        ];
    }
}
