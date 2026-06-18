<?php

namespace App\Http\Requests\EmployeeProfile;

use App\Models\EmployeeProfileChangeRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexProfileChangeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $values = [];

        foreach (['status', 'search', 'sort'] as $field) {
            if ($this->has($field) && is_string($this->input($field))) {
                $values[$field] = trim($this->input($field));
            }
        }

        $this->merge($values);
    }

    public function rules(): array
    {
        return [
            'status' => [
                'sometimes',
                'nullable',
                Rule::in([
                    EmployeeProfileChangeRequest::STATUS_PENDING,
                    EmployeeProfileChangeRequest::STATUS_APPROVED,
                    EmployeeProfileChangeRequest::STATUS_REJECTED,
                    EmployeeProfileChangeRequest::STATUS_CANCELLED,
                ]),
            ],
            'employee_id' => ['sometimes', 'nullable', 'integer', 'exists:employees,id'],
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'date_from' => ['sometimes', 'nullable', 'date'],
            'date_to' => ['sometimes', 'nullable', 'date', 'after_or_equal:date_from'],
            'sort' => ['sometimes', 'nullable', Rule::in(['newest', 'oldest'])],
            'per_page' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
