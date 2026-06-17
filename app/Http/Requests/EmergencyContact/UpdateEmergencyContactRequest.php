<?php

namespace App\Http\Requests\EmergencyContact;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEmergencyContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $values = [];

        foreach (['name', 'relationship', 'phone', 'alternate_phone', 'email', 'address', 'notes'] as $field) {
            if (! $this->has($field)) {
                continue;
            }

            $value = $this->input($field);
            $values[$field] = $value === null || trim((string) $value) === ''
                ? null
                : trim((string) $value);
        }

        if (isset($values['relationship'])) {
            $values['relationship'] = strtolower($values['relationship']);
        }

        if (isset($values['email'])) {
            $values['email'] = strtolower($values['email']);
        }

        if ($this->has('is_primary')) {
            $values['is_primary'] = $this->boolean('is_primary');
        }

        $this->merge($values);
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'relationship' => ['sometimes', 'required', Rule::in(['parent', 'spouse', 'sibling', 'child', 'relative', 'friend', 'other'])],
            'phone' => ['sometimes', 'required', 'string', 'max:30'],
            'alternate_phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'address' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'is_primary' => ['sometimes', 'boolean'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }
}
