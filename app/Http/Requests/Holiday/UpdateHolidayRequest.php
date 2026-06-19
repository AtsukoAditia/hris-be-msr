<?php

namespace App\Http\Requests\Holiday;

use Illuminate\Foundation\Http\FormRequest;

class UpdateHolidayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() || $this->user()?->isHR();
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'date' => ['sometimes', 'required', 'date', 'date_format:Y-m-d'],
            'is_recurring' => ['boolean'],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }
}
