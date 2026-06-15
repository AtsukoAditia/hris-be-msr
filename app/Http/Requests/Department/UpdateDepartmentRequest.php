<?php

namespace App\Http\Requests\Department;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $prepared = [
            'code' => strtoupper(trim((string) $this->input('code'))),
            'name' => trim((string) $this->input('name')),
        ];

        if ($this->has('description')) {
            $prepared['description'] = $this->filled('description')
                ? trim((string) $this->input('description'))
                : null;
        }

        if ($this->has('is_active')) {
            $prepared['is_active'] = $this->boolean('is_active');
        }

        $this->merge($prepared);
    }

    public function rules(): array
    {
        $department = $this->route('department');

        return [
            'code' => [
                'required',
                'string',
                'max:20',
                Rule::unique('departments', 'code')->ignore($department),
            ],
            'name' => ['required', 'string', 'max:100'],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
