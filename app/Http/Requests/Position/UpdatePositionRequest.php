<?php

namespace App\Http\Requests\Position;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePositionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $prepared = [
            'department_id' => $this->filled('department_id') ? $this->integer('department_id') : null,
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
        $position = $this->route('position');

        return [
            'department_id' => [
                'required',
                'integer',
                Rule::exists('departments', 'id')->where(function ($query) {
                    $query->where('is_active', true)->whereNull('deleted_at');
                }),
            ],
            'code' => [
                'required',
                'string',
                'max:20',
                Rule::unique('positions', 'code')->ignore($position),
            ],
            'name' => ['required', 'string', 'max:100'],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
