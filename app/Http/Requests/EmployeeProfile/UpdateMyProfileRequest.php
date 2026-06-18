<?php

namespace App\Http\Requests\EmployeeProfile;

use App\Support\EmployeeProfileFieldPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\Validator;

class UpdateMyProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(EmployeeProfileFieldPolicy::normalize($this->all()));
    }

    public function rules(): array
    {
        $profileId = $this->user()?->employee?->profile()->value('id');

        return EmployeeProfileFieldPolicy::rules($profileId);
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $employee = $this->user()?->employee;

                if (! $employee) {
                    return;
                }

                $currentValues = EmployeeProfileFieldPolicy::currentValues(
                    $employee,
                    EmployeeProfileFieldPolicy::APPROVAL_REQUIRED_FIELDS,
                );

                foreach (EmployeeProfileFieldPolicy::APPROVAL_REQUIRED_FIELDS as $field) {
                    if (! $this->exists($field) || $validator->errors()->has($field)) {
                        continue;
                    }

                    $requested = EmployeeProfileFieldPolicy::normalize(
                        [$field => $this->input($field)],
                        [$field],
                    )[$field] ?? null;

                    if ($requested !== ($currentValues[$field] ?? null)) {
                        $validator->errors()->add(
                            $field,
                            'Perubahan data ini harus diajukan melalui permintaan perubahan profil.',
                        );
                    }
                }
            },
        ];
    }

    public function directUpdates(): array
    {
        return Arr::only(
            $this->validated(),
            EmployeeProfileFieldPolicy::DIRECT_SELF_SERVICE_FIELDS,
        );
    }
}
