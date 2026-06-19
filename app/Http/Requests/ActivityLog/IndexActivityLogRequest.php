<?php

namespace App\Http\Requests\ActivityLog;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexActivityLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $values = [];

        foreach (['module', 'action', 'user_role', 'search'] as $field) {
            if ($this->has($field) && is_string($this->input($field))) {
                $values[$field] = trim($this->input($field));
            }
        }

        $this->merge($values);
    }

    public function rules(): array
    {
        return [
            'module' => ['sometimes', 'nullable', 'string', 'max:100'],
            'action' => ['sometimes', 'nullable', 'string', 'max:30'],
            'user_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'user_role' => ['sometimes', 'nullable', Rule::in(['admin', 'hr', 'manager', 'employee'])],
            'date_from' => ['sometimes', 'nullable', 'date'],
            'date_to' => ['sometimes', 'nullable', 'date', 'after_or_equal:date_from'],
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'per_page' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
