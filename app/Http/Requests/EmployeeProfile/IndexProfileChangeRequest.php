<?php

namespace App\Http\Requests\EmployeeProfile;

use Illuminate\Foundation\Http\FormRequest;

class IndexProfileChangeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [];
    }
}
