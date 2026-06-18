<?php

namespace App\Http\Requests\EmployeeProfile;

use App\Support\EmployeeProfileFieldPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\Validator;

class StoreProfileChangeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $changes = is_array($this->input('changes'))
            ? EmployeeProfileFieldPolicy::normalize(
                $this->input('changes'),
                EmployeeProfileFieldPolicy::APPROVAL_REQUIRED_FIELDS,
            )
            : $this->input('changes');

        $reason = $this->input('reason');

        $this->merge([
            'changes' => $changes,
            'reason' => is_string($reason) ? trim($reason) : $reason,
        ]);
    }

    public function rules(): array
    {
        $employee = $this->user()?->employee;
        $profileId = $employee?->profile()->value('id');
        $allowedFields = EmployeeProfileFieldPolicy::APPROVAL_REQUIRED_FIELDS;
        $rules = [
            'changes' => ['required', 'array:'.implode(',', $allowedFields), 'min:1', 'max:'.count($allowedFields)],
            'reason' => ['required', 'string', 'max:1000'],
        ];

        foreach (EmployeeProfileFieldPolicy::rules($profileId) as $field => $fieldRules) {
            if (! in_array($field, $allowedFields, true)) {
                continue;
            }

            $rules['changes.'.$field] = $fieldRules;
        }

        return $rules;
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $employee = $this->user()?->employee;
                $inputChanges = $this->input('changes');

                if (! $employee || ! is_array($inputChanges)) {
                    return;
                }

                $changes = Arr::only(
                    $inputChanges,
                    EmployeeProfileFieldPolicy::APPROVAL_REQUIRED_FIELDS,
                );
                $currentValues = EmployeeProfileFieldPolicy::currentValues(
                    $employee,
                    array_keys($changes),
                );
                $changedFields = [];

                foreach ($changes as $field => $value) {
                    if ($validator->errors()->has('changes.'.$field)) {
                        continue;
                    }

                    if ($value === ($currentValues[$field] ?? null)) {
                        $validator->errors()->add(
                            'changes.'.$field,
                            'Nilai yang diajukan sama dengan data profil saat ini.',
                        );

                        continue;
                    }

                    $changedFields[] = $field;
                }

                if ($changes !== [] && $changedFields === [] && ! $validator->errors()->has('changes')) {
                    $validator->errors()->add('changes', 'Tidak ada perubahan data yang dapat diajukan.');
                }
            },
        ];
    }

    public function validatedChanges(): array
    {
        $validated = $this->validated();

        return Arr::only(
            is_array($validated['changes'] ?? null) ? $validated['changes'] : [],
            EmployeeProfileFieldPolicy::APPROVAL_REQUIRED_FIELDS,
        );
    }
}
