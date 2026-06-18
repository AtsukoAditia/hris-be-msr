<?php

namespace App\Http\Requests\AttendanceCorrection;

use App\Models\AttendanceCorrectionRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexAttendanceCorrectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $values = [];

        foreach (['status', 'search', 'sort', 'correction_type'] as $field) {
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
                    AttendanceCorrectionRequest::STATUS_PENDING,
                    AttendanceCorrectionRequest::STATUS_APPROVED,
                    AttendanceCorrectionRequest::STATUS_REJECTED,
                    AttendanceCorrectionRequest::STATUS_CANCELLED,
                ]),
            ],
            'correction_type' => [
                'sometimes',
                'nullable',
                Rule::in([
                    AttendanceCorrectionRequest::TYPE_CHECK_IN,
                    AttendanceCorrectionRequest::TYPE_CHECK_OUT,
                    AttendanceCorrectionRequest::TYPE_BOTH,
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
