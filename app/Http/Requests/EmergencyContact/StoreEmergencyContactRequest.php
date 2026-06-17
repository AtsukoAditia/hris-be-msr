<?php

namespace App\Http\Requests\EmergencyContact;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmergencyContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'relationship' => strtolower(trim((string) $this->input('relationship'))),
            'phone' => trim((string) $this->input('phone')),
            'alternate_phone' => $this->filled('alternate_phone') ? trim((string) $this->input('alternate_phone')) : null,
            'email' => $this->filled('email') ? strtolower(trim((string) $this->input('email'))) : null,
            'address' => $this->filled('address') ? trim((string) $this->input('address')) : null,
            'notes' => $this->filled('notes') ? trim((string) $this->input('notes')) : null,
            'is_primary' => $this->has('is_primary') ? $this->boolean('is_primary') : false,
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'relationship' => ['required', Rule::in(['parent', 'spouse', 'sibling', 'child', 'relative', 'friend', 'other'])],
            'phone' => ['required', 'string', 'max:30'],
            'alternate_phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:2000'],
            'is_primary' => ['required', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
