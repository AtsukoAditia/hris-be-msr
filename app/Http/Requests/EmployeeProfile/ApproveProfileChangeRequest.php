<?php

namespace App\Http\Requests\EmployeeProfile;

use Illuminate\Foundation\Http\FormRequest;

class ApproveProfileChangeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $reviewNote = $this->input('review_note');

        if (is_string($reviewNote)) {
            $this->merge(['review_note' => trim($reviewNote)]);
        }
    }

    public function rules(): array
    {
        return [
            'review_note' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
