<?php

namespace App\Http\Requests\Branch;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBranchRequest extends FormRequest
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
            'address' => $this->filled('address') ? trim((string) $this->input('address')) : null,
            'latitude' => $this->filled('latitude') ? (float) $this->input('latitude') : null,
            'longitude' => $this->filled('longitude') ? (float) $this->input('longitude') : null,
            'radius_meters' => $this->filled('radius_meters') ? $this->integer('radius_meters') : 100,
            'timezone' => $this->filled('timezone') ? trim((string) $this->input('timezone')) : 'Asia/Jakarta',
        ];

        if ($this->has('is_active')) {
            $prepared['is_active'] = $this->boolean('is_active');
        }

        $this->merge($prepared);
    }

    public function rules(): array
    {
        $branch = $this->route('branch');

        return [
            'code' => ['required', 'string', 'max:20', Rule::unique('branches', 'code')->ignore($branch)],
            'name' => ['required', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:1000'],
            'latitude' => ['nullable', 'required_with:longitude', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'required_with:latitude', 'numeric', 'between:-180,180'],
            'radius_meters' => ['required', 'integer', 'min:1', 'max:50000'],
            'timezone' => ['required', 'string', 'timezone'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
